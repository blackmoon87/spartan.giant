<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Requests\LoginRequest;
use App\Controllers\Requests\RegisterRequest;
use App\Models\User;
use Spartan\Application;

class AuthController extends AppController
{
    /** The seeded demo users (database/seed.sql), listed on the login page. */
    private const DEMO_ACCOUNTS = [
        ['email' => 'admin@helpdesk.test', 'role' => 'admin'],
        ['email' => 'agent@helpdesk.test', 'role' => 'agent'],
        ['email' => 'maria@helpdesk.test', 'role' => 'customer'],
    ];

    public function showLogin(): string
    {
        if ($this->auth->check()) {
            $this->redirect('/dashboard');
            return '';
        }
        return $this->render('auth/login', ['demoAccounts' => self::DEMO_ACCOUNTS]);
    }

    public function login(LoginRequest $request): void
    {
        $email    = strtolower(trim((string) $request->post('email')));
        $password = (string) $request->post('password');
        $log      = Application::$app->logger->channel('auth');

        $user = (new User())->findInstanceBy('email', $email);

        if ($user === null || !password_verify($password, (string) $user->password)) {
            $log->warning('Failed login for {email} from {ip}', ['email' => $email, 'ip' => $request->getIp()]);
            $this->session->setFlash('error', trans('auth.failed'));
            $this->session->setFlash('old_input', ['email' => $email]);
            $this->redirect('/login');
            return;
        }

        if (password_needs_rehash((string) $user->password, PASSWORD_DEFAULT)) {
            (new User())->save($user->id, ['password' => password_hash($password, PASSWORD_DEFAULT)]);
        }

        $this->session->regenerate();                 // new session id on privilege change
        $this->session->set('user_id', (int) $user->id);
        $log->info('User {id} logged in', ['id' => $user->id]);
        $this->session->setFlash('success', trans('auth.welcome_back', ['name' => $user->name]));
        $this->redirect('/dashboard');
    }

    public function showRegister(): string
    {
        if ($this->auth->check()) {
            $this->redirect('/dashboard');
            return '';
        }
        return $this->render('auth/register');
    }

    public function register(RegisterRequest $request): void
    {
        $data = $request->getBody();

        $id = (int) (new User())->create([
            'name'     => trim((string) $data['name']),
            'email'    => strtolower(trim((string) $data['email'])),
            'password' => password_hash((string) $data['password'], PASSWORD_DEFAULT),
        ]);
        (new User())->findInstance($id)?->assignRole('customer');

        $this->session->regenerate();
        $this->session->set('user_id', $id);
        $this->session->setFlash('success', trans('auth.registered'));
        $this->redirect('/dashboard');
    }

    public function logout(): void
    {
        $this->session->remove('user_id');
        $this->session->regenerate();
        $this->session->setFlash('success', trans('auth.logged_out'));
        $this->redirect('/login');
    }
}
