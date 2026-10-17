<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Requests\CreateTokenRequest;
use App\Models\ApiToken;

/** Personal API tokens for the JSON API (routes/api.php). */
class AccountController extends AppController
{
    public function tokens(): string
    {
        return $this->render('account/tokens', [
            'tokens'   => (new ApiToken())->forUser((int) $this->user()->id),
            // Shown once, right after it is created; only its hash is stored.
            'newToken' => $this->session->getFlash('new_token'),
        ]);
    }

    public function createToken(CreateTokenRequest $request): void
    {
        $plain = (new ApiToken())->issue((int) $this->user()->id, trim((string) $request->post('name')));
        $this->session->setFlash('new_token', $plain);
        $this->session->setFlash('success', trans('account.token_created'));
        $this->redirect('/account/tokens');
    }

    public function revokeToken(string $id): void
    {
        $revoked = self::isId($id) && (new ApiToken())->revoke((int) $id, (int) $this->user()->id);
        $this->session->setFlash($revoked ? 'success' : 'error', trans($revoked ? 'account.token_revoked' : 'app.errors.not_found'));
        $this->redirect('/account/tokens');
    }
}
