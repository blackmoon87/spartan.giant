<?php

declare(strict_types=1);

namespace Spartan\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Spartan\Application;
use Spartan\Cache;
use Spartan\Gate;
use Spartan\QueryBuilder;
use Spartan\Request;
use Spartan\Response;
use Spartan\Router;

abstract class TestCase extends BaseTestCase
{
    protected Application $app;
    protected string $work;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app  = $GLOBALS['SPARTAN_TEST_APP'];
        $this->work = $GLOBALS['SPARTAN_TEST_WORKDIR'];

        $this->resetRequestState();
        $this->seedFixtures();
    }

    protected function tearDown(): void
    {
        // Never let identity or gate state leak into the next test.
        $this->app->container->forget('auth_user');
        $this->app->auth->forgetUser();
        $this->app->session->remove('user_id');
        Gate::$abilities = [];
        Gate::$policies  = [];
        Request::setTrustedProxies([]);

        parent::tearDown();
    }

    /**
     * Put the superglobals back to a known GET / request.
     */
    protected function resetRequestState(): void
    {
        $_SERVER = [
            'REQUEST_URI'    => '/',
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME'    => '/index.php',
            'REMOTE_ADDR'    => '127.0.0.1',
        ];
        $_GET = $_POST = $_FILES = [];
    }

    /**
     * Build a Request for the given method/URI, with optional body and headers.
     */
    protected function request(string $method = 'GET', string $uri = '/', array $post = [], array $server = []): Request
    {
        $_SERVER = array_merge([
            'REQUEST_URI'    => $uri,
            'REQUEST_METHOD' => $method,
            'SCRIPT_NAME'    => '/index.php',
            'REMOTE_ADDR'    => '127.0.0.1',
        ], $server);
        $_POST = $post;

        return new Request();
    }

    /**
     * A router bound to a freshly built request/response pair.
     *
     * @return array{0:Router,1:Request,2:Response}
     */
    protected function router(string $method = 'GET', string $uri = '/', array $server = []): array
    {
        $request  = $this->request($method, $uri, [], $server);
        $response = new Response();

        return [new Router($request, $response), $request, $response];
    }

    protected function table(string $table): QueryBuilder
    {
        return new QueryBuilder($this->app->db, $table);
    }

    /**
     * Reset the fixture rows so each test sees identical data.
     */
    protected function seedFixtures(): void
    {
        $this->app->db->exec("DELETE FROM t_users; DELETE FROM t_posts; DELETE FROM jobs;");
        $this->app->db->exec("
            INSERT INTO t_users (id,name,email,role,active,score) VALUES
                (1,'Ada','ada@example.com','admin',1,90),
                (2,'Linus','linus@example.com','user',1,70),
                (3,'Grace','grace@example.com','user',0,55);
            INSERT INTO t_posts (id,user_id,title,views) VALUES
                (1,1,'Kernel Design',120),(2,1,'On Compilers',80),(3,2,'Version Control',300);
        ");
    }

    protected function cacheKeyForget(string ...$keys): void
    {
        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }
}
