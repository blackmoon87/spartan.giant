<?php

declare(strict_types=1);

namespace App\Controllers;

use Spartan\Controller;
use App\Models\User;

class AuthController extends Controller
{
    public function showLogin(): string
    {
        return $this->render('auth/login', [
            'title' => 'Login — Spartan Blogger',
        ]);
    }

    public function processLogin(): void
    {
        $email    = trim((string)$this->request->post('email', ''));
        $password = (string)$this->request->post('password', '');

        $user = (new User())->findInstanceBy('email', $email);

        if (!$user || !password_verify($password, (string)$user->password)) {
            $this->session->setFlash('warning', 'Invalid credentials specified.');
            $this->redirect('/login');
            return;
        }

        // Regenerate session to prevent fixation
        $this->session->regenerate();

        // Authenticate user
        $this->session->set('user_id', (int)$user->id);

        // Resolve user role from DB (via HasAuthorization trait)
        $roles = $user->getRoles();
        $role  = $roles[0] ?? 'author';
        $this->session->set('role', $role);

        $this->session->setFlash('success', "Welcome back, {$user->name}!");
        $this->redirect('/author/posts');
    }

    public function logout(): void
    {
        $this->session->remove('user_id');
        $this->session->remove('role');
        $this->session->setFlash('success', 'You have been logged out successfully.');
        $this->redirect('/');
    }
}
