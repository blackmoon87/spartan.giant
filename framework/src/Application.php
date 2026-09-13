<?php

declare(strict_types=1);

namespace Spartan;

/**
 * App Orchestrator
 * 
 * @property \PDO|null $db Database connection instance
 */
class Application
{
    /**
     * Readonly after first assignment — prevents any external code from
     * overwriting the global application instance post-boot.
     * PHP 8.1+ readonly enforcement.
     */
    public static Application $app;
    public ConnectionManager $connections;
    public Logger    $logger;
    public Router    $router;
    public Request   $request;
    public Response  $response;
    public ViewInterface     $view;
    public SessionInterface  $session;
    public AuthInterface     $auth;
    public Container       $container;
    public EventDispatcher $events;
    private ?\PDO $dbInstance = null;
    public array $config;

    public function __construct(array $config)
    {
        require_once __DIR__ . '/helpers.php';
        // Enforce single instantiation — prevents accidental double-boot
        if (isset(self::$app)) {
            throw new \LogicException('Application has already been instantiated. Only one instance is allowed per process.');
        }

        self::$app = $this;
        $this->config    = $config;

        // The framework lives in vendor/ and cannot infer the project root from
        // its own location — the application declares it here.
        if (!empty($config['base_path'])) {
            Paths::setBase($config['base_path']);
        }

        // Client-controlled forwarding headers are only honoured for these peers.
        Request::setTrustedProxies($config['app']['trusted_proxies'] ?? []);
        $this->container   = new Container();
        $this->connections = new ConnectionManager();
        $this->logger      = new Logger();

        // Register the primary database connection config
        if (!empty($config['db'])) {
            $this->connections->addConnection('default', $config['db']);
            $this->connections->setMaxLifetime($config['db']['max_lifetime'] ?? 3600);

            // Register read replicas when splitting is enabled
            if (($config['db']['read_write_split'] ?? false) === true) {
                $this->connections->enableSplit(true);
                foreach ($config['db']['read'] ?? [] as $replica) {
                    $this->connections->addReadReplica($replica);
                }
            }
        }

        // Register ConnectionManager in the container
        $this->container->singleton(ConnectionManager::class, fn() => $this->connections);
        
        // Register logger in container
        $this->container->singleton(Logger::class, fn() => $this->logger);

        $this->events    = new EventDispatcher();
        $this->request   = new Request();
        $this->session   = new Session($this->request);
        $this->auth      = new Auth($this->session);
        $this->response  = new Response();
        $this->view      = new View();
        $this->router    = new Router($this->request, $this->response);

        // Register AuthInterface in container
        $this->container->singleton(AuthInterface::class, fn() => $this->auth);

        // Boot cache driver
        Cache::boot($config['cache'] ?? []);

        // Boot Translation Engine
        $translator = Translation\Translator::getInstance();
        $localeConfig = $config['locale'] ?? [];
        $defaultLocale = $localeConfig['default'] ?? 'en';
        $translator->setLocale($this->session->get('locale', $defaultLocale));
        $translator->setFallback($localeConfig['fallback'] ?? 'en');
        if (!empty($localeConfig['rtl'])) {
            $translator->setRtlLocales($localeConfig['rtl']);
        }
        $this->container->singleton(Translation\Translator::class, fn() => $translator);

        // Generate a cryptographically secure CSRF token if not already in session
        if (!$this->session->get('_csrf_token')) {
            $this->session->set('_csrf_token', bin2hex(random_bytes(32)));
        }
    }

    public function run(): void
    {
        try {
            $result = $this->router->resolve();
            if ($result instanceof Response) {
                $result->send();
            } else {
                if ($result !== null && $result !== '') {
                    $this->response->send();
                    echo $result;
                } else {
                    $this->response->send();
                }
            }
        } catch (\Throwable $e) {
            $handler = $this->container->has(ExceptionHandler::class)
                ? $this->container->make(ExceptionHandler::class)
                : new ExceptionHandler();
                
            $handler->handle($e, $this->request, $this->response, $this->config);
        } finally {
            // Automatically clean flash messages at the end of execution
            $this->session->removeFlashMessages();

            // Release the session file lock so concurrent requests from the
            // same browser (AJAX polling, navigation) are never blocked.
            if (method_exists($this->session, 'close')) {
                $this->session->close();
            }
        }
    }

    /**
     * Handle incoming request for Worker Mode (FrankenPHP, RoadRunner, Swoole)
     * Resets transient per-request state while retaining booted services.
     */
    public function handleRequest(?Request $request = null): void
    {
        if ($request !== null) {
            $this->request = $request;
        } else {
            // Superglobals have been repopulated by the worker runtime: rebuild
            // the Request so memoised state (JSON body, path) is not reused.
            $this->request = new Request();
        }
        $this->router->setRequest($this->request);

        $this->response = new Response();
        $this->router->setResponse($this->response);

        // Re-open the session for THIS request (it was closed after the last one).
        if (method_exists($this->session, 'start')) {
            $this->session->start($this->request);
        }

        // A fresh CSRF token for brand-new sessions, mirroring boot behaviour.
        if (!$this->session->get('_csrf_token')) {
            $this->session->set('_csrf_token', bin2hex(random_bytes(32)));
        }

        $this->run();

        // Reset transient state post execution to prevent memory accumulation
        $this->resetPerRequestState();
    }

    /**
     * Reset per-request state to prevent state bleeding between requests
     * (and memory growth) in worker mode.
     *
     * The identity cache is the critical one: 'auth_user' was previously left
     * in the container, so the NEXT request — including an anonymous one —
     * resolved the PREVIOUS request's user through Gate::resolveUser().
     */
    public function resetPerRequestState(): void
    {
        $this->session->removeFlashMessages();

        // SessionInterface does not mandate the worker-mode lifecycle hooks,
        // so a custom implementation may legitimately lack them.
        if (method_exists($this->session, 'close')) {
            $this->session->close();
        }

        // Drop the resolved-identity cache — never share it across requests.
        $this->container->forget('auth_user');

        if (method_exists($this->auth, 'forgetUser')) {
            $this->auth->forgetUser();
        }

        Model::forgetMemoize();

        if (method_exists($this->view, 'resetState')) {
            $this->view->resetState();
        }

        $this->request->resetState();

        // Reset translator loaded translations and re-read locale from session
        Translation\Translator::getInstance()->resetState();

        // Health-check open connections instead of tearing them all down: a
        // dropped or over-age connection is recycled, but a healthy one is
        // left exactly as-is. This is what keeps the database connection warm
        // across requests in worker mode instead of reconnecting every time.
        $this->connections->recycleStale();
        if (!$this->connections->isConnected('default')) {
            $this->dbInstance = null;
        }
    }

    /**
     * Magic getter to support lazy-loading of the database connection.
     */
    public function __get(string $name)
    {
        if ($name === 'db') {
            if ($this->dbInstance === null && !empty($this->config['db']['database'])) {
                try {
                    // Use ConnectionManager when available, fallback to direct Database
                    $this->dbInstance = $this->connections->hasConnection('default')
                        ? $this->connections->connection('default')
                        : Database::getInstance($this->config['db']);
                } catch (\PDOException $e) {
                    error_log("Database connection failed during lazy boot: " . $e->getMessage());
                }
            }
            return $this->dbInstance;
        }
        return null;
    }

    /**
     * Magic setter to allow swapping the db instance (useful in tests).
     */
    public function __set(string $name, mixed $value): void
    {
        if ($name === 'db') {
            $this->dbInstance = $value;
        }
    }

    /**
     * Magic isset to check if database is connected or configured.
     */
    public function __isset(string $name): bool
    {
        if ($name === 'db') {
            return $this->dbInstance !== null || !empty($this->config['db']['database']);
        }
        return false;
    }
}
