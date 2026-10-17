<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Requests\ContactRequest;
use Spartan\Controller;
use Spartan\Application;
use App\Models\User;

class HomeController extends Controller
{
    /**
     * Render the home page action.
     */
    public function index(): string
    {
        // A translation key suffix (site.home.db_*), never a raw driver message:
        // connection errors go to the log, not to the visitor.
        $dbStatus  = 'missing';
        $userCount = 0;

        if (Application::$app->db !== null) {
            try {
                $userCount = (new User())->getCount();
                $dbStatus  = 'connected';
            } catch (\Throwable $e) {
                error_log('Home page database check failed: ' . $e->getMessage());
                $dbStatus = 'error';
            }
        }

        return $this->render('home', [
            'active'    => 'home',
            'dbStatus'  => $dbStatus,
            'userCount' => $userCount,
        ]);
    }

    /**
     * Render dynamic profile page using URL capture variable.
     */
    public function profile(string $id): string
    {
        return $this->render('profile', [
            'active' => 'profile',
            'userId' => $id,
        ]);
    }

    /**
     * Render the contact form.
     */
    public function contact(): string
    {
        return $this->render('contact', ['active' => 'contact']);
    }

    /**
     * Handle a contact form submission. ContactRequest validates the input
     * first and redirects back with errors() and old() on failure.
     */
    public function sendContact(ContactRequest $request): void
    {
        $name = (string) $request->post('name');

        $this->session->setFlash('success', trans('site.contact.sent', ['name' => $name]));
        $this->redirect('/contact');
    }
}
