<?php

declare(strict_types=1);

namespace Spartan;

abstract class Middleware
{
    /**
     * Handle the request through this middleware — onion pipeline pattern.
     *
     * Override this method when you need to wrap the response (e.g.,
     * response caching, compression, timing). Call $next() to pass
     * control to the next middleware or route handler.
     *
     * The default implementation delegates to the legacy execute() method
     * and then invokes $next(), so existing middlewares that only override
     * execute() keep working without any changes.
     *
     * @param callable(): mixed $next Invokes the next middleware or the route handler.
     * @return mixed The response from the inner middleware / route handler.
     */
    public function handle(Request $request, Response $response, callable $next): mixed
    {
        $this->execute($request, $response);
        return $next();
    }

    /**
     * Execute the middleware logic — legacy fire-and-forget hook.
     *
     * Override this for simple guard middlewares that only inspect or reject
     * a request. The base implementation is a no-op so that middlewares that
     * only override handle() do not need to provide an empty execute().
     */
    public function execute(Request $request, Response $response): void {}
}

