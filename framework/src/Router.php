<?php

declare(strict_types=1);

namespace Spartan;

class Router
{
    protected Request $request;
    protected Response $response;
    protected array $routes = [];
    protected array $middlewareGroups = [];
    protected array $csrfExclusions = [];
    protected array $middlewareAliases = [];
    protected array $globalMiddlewares = [];

    /** @var array{callback: callable|array, middlewares: array}|null */
    protected ?array $fallbackHandler = null;

    /** Memoised {param} -> regex compilations, keyed by route path. */
    protected array $patternCache = [];

    /** Memoised attribute scan results, keyed by "Class::method". */
    protected static array $authAttributeCache = [];

    /**
     * Dynamic ({placeholder}) routes bucketed by [method][first static segment].
     * Lets resolve() test only the routes that could plausibly match the
     * incoming path's first segment instead of scanning every dynamic route
     * for the method.
     */
    protected array $dynamicBuckets = [];

    /**
     * Dynamic routes whose own first segment is itself a {placeholder} (e.g.
     * "/{slug}") — there is no static prefix to bucket on, so these must be
     * checked against every path regardless of its first segment.
     */
    protected array $wildcardBuckets = [];

    /** Methods whose bucket index is currently built and valid. */
    protected array $bucketsBuilt = [];



    public function __construct(Request $request, Response $response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    /**
     * Set global middlewares that run on every request.
     */
    public function setGlobalMiddlewares(array $middlewares): void
    {
        $this->globalMiddlewares = $middlewares;
    }

    /**
     * Define a middleware alias.
     */
    public function aliasMiddleware(string $name, string $class): void
    {
        $this->middlewareAliases[$name] = $class;
    }

    /**
     * Dynamically swap requests.
     */
    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    /**
     * Dynamically swap responses.
     */
    public function setResponse(Response $response): void
    {
        $this->response = $response;
    }

    /**
     * Define a middleware group.
     */
    public function middlewareGroup(string $name, array $middlewares): void
    {
        $this->middlewareGroups[$name] = $middlewares;
    }

    /**
     * Define CSRF exclusions.
     */
    public function excludeCsrf(string ...$paths): void
    {
        $this->csrfExclusions = array_merge($this->csrfExclusions, $paths);
    }

    /**
     * Get CSRF exclusions.
     */
    public function getCsrfExclusions(): array
    {
        return $this->csrfExclusions;
    }

    /**
     * Define a GET route.
     */
    public function get(string $path, array|callable $callback, array $middlewares = []): void
    {
        $this->routes['GET'][$path] = [
            'callback' => $callback,
            'middlewares' => $middlewares
        ];
        unset($this->bucketsBuilt['GET']);
    }

    /**
     * Define a POST route.
     */
    public function post(string $path, array|callable $callback, array $middlewares = []): void
    {
        $this->routes['POST'][$path] = [
            'callback'    => $callback,
            'middlewares' => $middlewares,
        ];
        unset($this->bucketsBuilt['POST']);
    }

    /**
     * Define a PUT route.
     */
    public function put(string $path, array|callable $callback, array $middlewares = []): void
    {
        $this->routes['PUT'][$path] = [
            'callback'    => $callback,
            'middlewares' => $middlewares,
        ];
        unset($this->bucketsBuilt['PUT']);
    }

    /**
     * Define a PATCH route.
     */
    public function patch(string $path, array|callable $callback, array $middlewares = []): void
    {
        $this->routes['PATCH'][$path] = [
            'callback'    => $callback,
            'middlewares' => $middlewares,
        ];
        unset($this->bucketsBuilt['PATCH']);
    }

    /**
     * Define a DELETE route.
     */
    public function delete(string $path, array|callable $callback, array $middlewares = []): void
    {
        $this->routes['DELETE'][$path] = [
            'callback'    => $callback,
            'middlewares' => $middlewares,
        ];
        unset($this->bucketsBuilt['DELETE']);
    }

    /**
     * Register a redirect from one path to another.
     * Only GET requests are redirected; other methods should be handled explicitly.
     */
    public function redirect(string $from, string $to, int $status = 302): void
    {
        $response = $this->response;
        $this->get($from, static function () use ($response, $to, $status): void {
            $response->setStatusCode($status);
            $response->redirect($to);
        });
    }

    /**
     * Register a fallback handler for unmatched routes.
     * Executes instead of the default 404 page when no route matches.
     */
    public function fallback(callable|array $callback, array $middlewares = []): void
    {
        $this->fallbackHandler = [
            'callback'    => $callback,
            'middlewares' => $middlewares,
        ];
    }

    /**
     * Resolve the current HTTP request to its registered callback or controller action.
     */
    public function resolve(): mixed
    {
        $this->response->reset();
        $path   = $this->request->getPath();
        $method = $this->request->getMethod();

        $routeData = $this->routes[$method][$path] ?? false;
        $params    = [];
        $routePath = $path;

        // If direct match not found, try dynamic pattern matching.
        // Static routes are skipped — the exact-match lookup above already
        // ruled them out, so only routes carrying {placeholders} are scanned.
        // Method + first-path-segment bucketing narrows that scan to just the
        // dynamic routes that could plausibly match this path, instead of
        // every dynamic route registered for the method.
        if ($routeData === false) {
            if (!isset($this->bucketsBuilt[$method])) {
                $this->buildBuckets($method);
            }

            $firstSegment = explode('/', ltrim($path, '/'), 2)[0];
            $candidates = array_merge(
                $this->dynamicBuckets[$method][$firstSegment] ?? [],
                $this->wildcardBuckets[$method] ?? []
            );

            foreach ($candidates as $routeKey) {
                $data = $this->routes[$method][$routeKey] ?? null;
                if ($data === null) {
                    continue;
                }

                $pattern = $this->patternCache[$routeKey]
                    ??= '#^' . preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $routeKey) . '$#';

                if (preg_match($pattern, $path, $matches)) {
                    $params    = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                    $routeData = $data;
                    $routePath = $routeKey;
                    break;
                }
            }
        }

        // Global middlewares run on EVERY request — including unmatched ones,
        // so security headers and rate limits still apply to 404 traffic.
        if ($this->runMiddlewares($this->globalMiddlewares)) {
            return $this->response;
        }

        if ($routeData === false) {
            if ($this->fallbackHandler !== null) {
                if ($this->runMiddlewares($this->fallbackHandler['middlewares'])) {
                    return $this->response;
                }
                return $this->executeCallback($this->fallbackHandler['callback'], []);
            }
            $this->response->setStatusCode(404);
            return Application::$app->view->render('error_404', ['message' => 'The page you requested was not found.']);
        }

        // Execute route middlewares
        if ($this->runMiddlewares($routeData['middlewares'])) {
            return $this->response;
        }

        // Route params arrive keyed by placeholder name. Arguments are always
        // passed positionally (named-argument dispatch is both slower and
        // fatal when a closure's parameter names differ), so work out the
        // order once per route and cache it on the route entry — never in a
        // shared map, where two callbacks under the same path could collide.
        // Controller [class, action] callbacks are bound by reflection further
        // down and skip this entirely.
        if ($params !== [] && is_callable($routeData['callback'])) {
            if (!array_key_exists('argmap', $routeData)) {
                $routeData['argmap'] = $this->resolveArgumentOrder($routeData['callback'], $params);
                $this->routes[$method][$routePath]['argmap'] = $routeData['argmap'];
            }

            if ($routeData['argmap'] === null) {
                $params = array_values($params);
            } else {
                $ordered = [];
                foreach ($routeData['argmap'] as $name) {
                    $ordered[] = $params[$name] ?? null;
                }
                $params = $ordered;
            }
        }

        // Execute Callback
        return $this->executeCallback($routeData['callback'], $params);
    }

    /**
     * Bucket a method's dynamic routes by their first static path segment.
     * A route whose own first segment is a {placeholder} has no static
     * prefix to key on and goes into the wildcard bucket instead, since it
     * could match any incoming first segment.
     */
    protected function buildBuckets(string $method): void
    {
        $this->dynamicBuckets[$method]  = [];
        $this->wildcardBuckets[$method] = [];

        foreach ($this->routes[$method] ?? [] as $routeKey => $data) {
            if (!str_contains($routeKey, '{')) {
                continue;
            }

            $segment = explode('/', ltrim($routeKey, '/'), 2)[0];
            if ($segment === '' || str_contains($segment, '{')) {
                $this->wildcardBuckets[$method][] = $routeKey;
            } else {
                $this->dynamicBuckets[$method][$segment][] = $routeKey;
            }
        }

        $this->bucketsBuilt[$method] = true;
    }

    /**
     * Run a middleware stack using the onion pipeline pattern.
     *
     * Builds a nested callable chain from inside-out: the innermost
     * callable is a no-op, and each middleware wraps the next via its
     * handle($request, $response, $next) method.
     *
     * Returns true when the chain terminated the request (redirect, content,
     * or an error status), meaning the route callback must NOT run.
     *
     * Backward-compatible: middlewares that only override execute() work
     * unchanged because Middleware::handle() calls execute() then $next().
     */
    protected function runMiddlewares(array $middlewares): bool
    {
        if ($middlewares === []) {
            return false;
        }

        $resolved = $this->resolveMiddlewares($middlewares);
        $request  = $this->request;
        $response = $this->response;

        // The innermost handler is a no-op — it just returns null to let
        // the route callback run after the pipeline completes.
        $pipeline = function () {
            return null;
        };

        // Build the onion from inside-out: the LAST middleware in the list
        // wraps the core, and the FIRST middleware is the outermost shell.
        foreach (array_reverse($resolved) as $middlewareInfo) {
            $prev = $pipeline;
            $pipeline = function () use ($middlewareInfo, $request, $response, $prev) {
                $middlewareClass = $middlewareInfo['class'];
                $args            = $middlewareInfo['args'];

                if (!class_exists($middlewareClass)) {
                    throw new \InvalidArgumentException("Middleware [{$middlewareClass}] does not exist.");
                }

                // Argument-less middlewares go through the container so they can
                // declare constructor dependencies; parameterised ones are built
                // directly (no reflection cost on the hot path).
                $middleware = $args === [] && Application::$app->container->has($middlewareClass)
                    ? Application::$app->container->make($middlewareClass)
                    : new $middlewareClass(...$args);

                return $middleware->handle($request, $response, $prev);
            };
        }

        // Execute the pipeline
        $pipeline();

        // Check if any middleware terminated the request
        if ($response->getRedirectUrl() !== null
            || $response->getContent() !== null
            || $response->getStatusCode() >= 400) {
            return true;
        }

        return false;
    }

    /**
     * Helper to resolve middleware groups and class names to a flat array.
     *
     * @param list<string> $seenGroups Guards against self-referencing groups.
     */
    protected function resolveMiddlewares(array $middlewares, array $seenGroups = []): array
    {
        $resolved = [];
        foreach ($middlewares as $middleware) {
            if (is_string($middleware)) {
                $parts = explode(':', $middleware, 2);
                $name = $parts[0];
                $argsString = $parts[1] ?? '';
                // Cast string arguments to proper PHP types if numeric
                $args = $argsString !== '' ? array_map(function ($arg) {
                    if (is_numeric($arg)) {
                        return str_contains($arg, '.') ? (float) $arg : (int) $arg;
                    }
                    return $arg;
                }, explode(',', $argsString)) : [];

                if (isset($this->middlewareGroups[$name])) {
                    if (in_array($name, $seenGroups, true)) {
                        throw new \LogicException(
                            "Circular middleware group reference detected: '{$name}' includes itself."
                        );
                    }
                    $resolved = array_merge(
                        $resolved,
                        $this->resolveMiddlewares($this->middlewareGroups[$name], [...$seenGroups, $name])
                    );
                } else {
                    $class = $this->middlewareAliases[$name] ?? $name;
                    $resolved[] = [
                        'class' => $class,
                        'args' => $args
                    ];
                }
            } elseif (is_array($middleware) && isset($middleware['class'])) {
                $resolved[] = $middleware;
            } else {
                $class = is_object($middleware) ? get_class($middleware) : (string)$middleware;
                $resolved[] = [
                    'class' => $class,
                    'args' => []
                ];
            }
        }
        return $resolved;
    }

    /**
     * Execute closures or Controller action mappings.
     */
    protected function executeCallback(mixed $callback, array $params = []): mixed
    {
        if (is_callable($callback)) {
            // resolve() normally hands us a plain list already; this guards the
            // path for callers invoking executeCallback() directly.
            if ($params !== [] && !array_is_list($params)) {
                $order  = $this->resolveArgumentOrder($callback, $params);
                $params = $order === null
                    ? array_values($params)
                    : array_map(fn(string $name) => $params[$name] ?? null, $order);
            }
            return call_user_func($callback, ...$params);
        }

        if (is_array($callback)) {
            $controllerClass = $callback[0];
            $action = $callback[1];
            
            if (!class_exists($controllerClass)) {
                throw new \InvalidArgumentException("Controller class [{$controllerClass}] does not exist.");
            }

            // Verify the action BEFORE reflecting on attributes — otherwise a
            // typo'd route surfaced as a raw ReflectionException instead of
            // this explicit error.
            if (!method_exists($controllerClass, $action)) {
                throw new \BadMethodCallException("Method [{$action}] does not exist on controller [{$controllerClass}].");
            }

            if (!$this->checkAuthorizationAttributes($controllerClass, $action)) {
                return $this->response;
            }

            $controller = Application::$app->container->make($controllerClass);

            // Inspect action parameters for Request or FormRequest dependencies
            $reflection = new \ReflectionMethod($controllerClass, $action);
            $actionArgs = [];
            
            foreach ($reflection->getParameters() as $parameter) {
                $type = $parameter->getType();
                if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                    $typeName = $type->getName();
                    if ($typeName === \Spartan\Request::class || is_subclass_of($typeName, \Spartan\Request::class)) {
                        if (is_subclass_of($typeName, \Spartan\FormRequest::class)) {
                            /** @var \Spartan\FormRequest $formRequest */
                            $formRequest = new $typeName();
                            $formRequest->validate();
                            $actionArgs[] = $formRequest;
                        } else {
                            $actionArgs[] = Application::$app->request;
                        }
                        continue;
                    }
                }
                
                $name = $parameter->getName();
                if (isset($params[$name])) {
                    $actionArgs[] = $params[$name];
                } elseif (!empty($params)) {
                    $actionArgs[] = array_shift($params);
                } elseif ($parameter->isDefaultValueAvailable()) {
                    $actionArgs[] = $parameter->getDefaultValue();
                } elseif ($type === null || $parameter->allowsNull()) {
                    $actionArgs[] = null;
                } else {
                    // Previously injected null here, surfacing as an opaque
                    // TypeError deep inside the controller.
                    throw new \InvalidArgumentException(
                        "Route parameter [\${$name}] required by {$controllerClass}::{$action}() "
                        . "was not supplied by the matched route pattern."
                    );
                }
            }
            
            return call_user_func_array([$controller, $action], $actionArgs);
        }

        throw new \RuntimeException("Invalid route callback type.");
    }

    /**
     * Work out how to hand route parameters to a callable, positionally.
     *
     * Returns null when the placeholders already line up with the callback's
     * parameters (pass them in declaration order), or the list of placeholder
     * names in the callback's own parameter order when the callback names them
     * in a different sequence. Callers cache the result per route.
     *
     * @param array<string, mixed> $params
     * @return list<string>|null
     */
    protected function resolveArgumentOrder(mixed $callback, array $params): ?array
    {
        try {
            $ref = $callback instanceof \Closure || is_string($callback)
                ? new \ReflectionFunction($callback)
                : new \ReflectionMethod(...(is_array($callback) ? $callback : [$callback, '__invoke']));
        } catch (\ReflectionException) {
            return null;
        }

        $declared = [];
        foreach ($ref->getParameters() as $parameter) {
            $declared[] = $parameter->getName();
        }

        $placeholders = array_keys($params);

        // The callback must name every placeholder for reordering to be
        // meaningful; otherwise fall back to positional order.
        foreach ($placeholders as $name) {
            if (!in_array($name, $declared, true)) {
                return null;
            }
        }

        $order = array_values(array_intersect($declared, $placeholders));

        return $order === $placeholders ? null : $order;
    }

    /**
     * Scan and verify authorization attributes on the controller class and action method.
     */
    protected function checkAuthorizationAttributes(string $controllerClass, string $action): bool
    {
        // Reflection is expensive and the result never changes for a given
        // class/method, so the requirement set is resolved once per process.
        $cacheKey = $controllerClass . '::' . $action;
        if (!isset(self::$authAttributeCache[$cacheKey])) {
            $classRef  = new \ReflectionClass($controllerClass);
            $methodRef = new \ReflectionMethod($controllerClass, $action);

            $requiredRoles = [];
            foreach (array_merge(
                $classRef->getAttributes(\Spartan\Attributes\RequireRole::class),
                $methodRef->getAttributes(\Spartan\Attributes\RequireRole::class)
            ) as $attr) {
                $requiredRoles = array_merge($requiredRoles, $attr->newInstance()->roles);
            }

            $requiredPermissions = [];
            foreach (array_merge(
                $classRef->getAttributes(\Spartan\Attributes\RequirePermission::class),
                $methodRef->getAttributes(\Spartan\Attributes\RequirePermission::class)
            ) as $attr) {
                $requiredPermissions = array_merge($requiredPermissions, $attr->newInstance()->permissions);
            }

            self::$authAttributeCache[$cacheKey] = [$requiredRoles, $requiredPermissions];
        }

        [$requiredRoles, $requiredPermissions] = self::$authAttributeCache[$cacheKey];

        if (empty($requiredRoles) && empty($requiredPermissions)) {
            return true;
        }

        // Resolve authenticated user
        $user = null;
        if (Application::$app->container->has('auth_user')) {
            $user = Application::$app->container->make('auth_user');
        } else {
            $userId = Application::$app->session->get('user_id');
            if ($userId) {
                $userClass = 'App\\Models\\User';
                if (class_exists($userClass)) {
                    try {
                        $userModel = new $userClass();
                        $user = $userModel->findInstance($userId);
                        if ($user) {
                            Application::$app->container->instance('auth_user', $user);
                        }
                    } catch (\Throwable $e) {
                    }
                }
            }
        }

        if (!$user) {
            $this->response->setStatusCode(401);
            if ($this->request->isAjax()) {
                $this->response->json(['error' => 'Unauthorized.'], 401);
            } else {
                Application::$app->session->setFlash('error', 'You must be logged in to access this page.');
                $this->response->redirect('/login');
            }
            return false;
        }

        // Verify Roles
        if (!empty($requiredRoles)) {
            if (!method_exists($user, 'hasRole') || !$user->hasRole(...$requiredRoles)) {
                $this->abortForbidden();
                return false;
            }
        }

        // Verify Permissions
        if (!empty($requiredPermissions)) {
            if (!method_exists($user, 'hasPermission')) {
                $this->abortForbidden();
                return false;
            }
            foreach ($requiredPermissions as $permission) {
                if (!$user->hasPermission($permission)) {
                    $this->abortForbidden();
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Set Response content for forbidden access.
     */
    protected function abortForbidden(): void
    {
        $this->response->setStatusCode(403);
        if ($this->request->isAjax()) {
            $this->response->json(['error' => 'Forbidden.'], 403);
        } else {
            try {
                $rendered = Application::$app->view->render('error_403', ['message' => 'You do not have the required permissions to access this page.']);
                $this->response->setContent($rendered);
            } catch (\Throwable $e) {
                $this->response->setContent("<h1>403 Forbidden</h1><p>You do not have the required permissions to access this page.</p>");
            }
        }
    }

    /**
     * Load cached routes if enabled and file exists.
     */
    public function loadCache(): bool
    {
        $config = Application::$app->config['router'] ?? [];
        $enabled = $config['cache_enabled'] ?? false;
        $file = $config['cache_file'] ?? null;

        if ($enabled && $file && file_exists($file)) {
            $data = require $file;
            if (is_array($data)) {
                $this->routes = $data['routes'] ?? [];
                $this->middlewareGroups = $data['middlewareGroups'] ?? [];
                $this->csrfExclusions = $data['csrfExclusions'] ?? [];
                $this->patternCache = [];
                $this->bucketsBuilt = [];
                return true;
            }
        }
        return false;
    }

    /**
     * Save current routes map to cache file.
     */
    public function saveCache(): bool
    {
        $config = Application::$app->config['router'] ?? [];
        $enabled = $config['cache_enabled'] ?? false;
        $file = $config['cache_file'] ?? null;

        if (!$enabled || !$file) {
            return false;
        }

        // We must check if any route callback is a Closure (since Closures cannot be exported)
        foreach ($this->routes as $method => $routes) {
            foreach ($routes as $path => $data) {
                if ($data['callback'] instanceof \Closure) {
                    throw new \LogicException("Cannot cache routes because route '{$method} {$path}' uses a Closure.");
                }
            }
        }

        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $data = [
            'routes' => $this->routes,
            'middlewareGroups' => $this->middlewareGroups,
            'csrfExclusions' => $this->csrfExclusions,
        ];

        $content = "<?php\n\n// Auto-generated route cache file\nreturn " . var_export($data, true) . ";\n";

        // Write atomically — a concurrent request must never `require` a
        // half-written cache file.
        $tmp = $file . '.' . getmypid() . '.tmp';
        if (file_put_contents($tmp, $content, LOCK_EX) === false) {
            return false;
        }
        if (!rename($tmp, $file)) {
            @unlink($tmp);
            return false;
        }
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($file, true);
        }
        return true;
    }

    /**
     * Get all registered routes.
     *
     * @return array<string, array<string, array{callback: mixed, middlewares: array}>>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
