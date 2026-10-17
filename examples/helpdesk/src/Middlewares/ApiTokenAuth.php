<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Models\ApiToken;
use App\Models\User;
use Spartan\Application;
use Spartan\Middleware;
use Spartan\Request;
use Spartan\Response;

/**
 * Bearer-token guard for /api routes (alias 'api.auth').
 * The token's user becomes the request's user for Gate::resolveUser(),
 * policies and #[RequireRole] — the session is not involved.
 */
class ApiTokenAuth extends Middleware
{
    public function execute(Request $request, Response $response): void
    {
        $header = (string) $request->header('Authorization');
        $plain  = str_starts_with($header, 'Bearer ') ? trim(substr($header, 7)) : '';

        $token = (new ApiToken())->findByPlain($plain);
        $user  = $token === null ? null : (new User())->findInstance($token['user_id']);

        if ($user === null) {
            $response->json(['error' => trans('api.unauthenticated')], 401);
            return;
        }

        Application::$app->container->instance('auth_user', $user);
        Application::$app->container->instance('api_token_id', (int) $token['id']);
    }
}
