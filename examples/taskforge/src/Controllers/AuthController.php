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
            'title' => 'Login — TaskForge',
        ]);
    }

    public function processLogin(): void
    {
        $email    = trim((string)$this->request->post('email', ''));
        $password = (string)$this->request->post('password', '');

        $user = (new User())->findInstanceBy('email', $email);

        if (!$user || !password_verify($password, (string)$user->password)) {
            $this->session->setFlash('warning', 'Invalid credentials.');
            $this->redirect('/login');
            return;
        }

        $this->session->regenerate();
        $this->session->set('user_id', (int)$user->id);

        $roles = $user->getRoles();
        $this->session->set('role', $roles[0] ?? 'developer');

        $this->session->setFlash('success', "Welcome back, {$user->name}!");
        $this->redirect('/dashboard');
    }

    public function logout(): void
    {
        $this->session->remove('user_id');
        $this->session->remove('role');
        $this->session->setFlash('success', 'Logged out successfully.');
        $this->redirect('/');
    }
}
