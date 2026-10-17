<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\Requests\IssueTokenRequest;
use App\Models\ApiToken;
use App\Models\User;
use Spartan\Application;
use Spartan\Controller;

class TokenController extends Controller
{
    /** POST /api/v1/tokens  {email, password, name} → {token} (shown once). */
    public function issue(IssueTokenRequest $request): void
    {
        $email = strtolower(trim((string) $request->post('email')));
        $user  = (new User())->findInstanceBy('email', $email);

        if ($user === null || !password_verify((string) $request->post('password'), (string) $user->password)) {
            $this->json(['error' => trans('auth.failed')], 401);
            return;
        }

        $plain = (new ApiToken())->issue((int) $user->id, trim((string) $request->post('name')));
        $this->json(['token' => $plain, 'type' => 'Bearer', 'message' => trans('api.token_issued')], 201);
    }

    /** DELETE /api/v1/tokens/current — revoke the token used for this request. */
    public function revokeCurrent(): void
    {
        $tokenId = (int) Application::$app->container->make('api_token_id');
        $userId  = (int) \Spartan\Gate::resolveUser()?->id;
        (new ApiToken())->revoke($tokenId, $userId);
        $this->json(['message' => trans('api.token_revoked')]);
    }
}
