<?php

declare(strict_types=1);

namespace App\Controllers;

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
        $dbStatus = Application::$app->db !== null ? 'Connected' : 'Not Connected (Check your .env settings)';
        
        $params = [
            'title' => 'Home Page',
            'dbStatus' => $dbStatus,
            'userCount' => 0
        ];

        // Fetch user counts if DB is connected
        if (Application::$app->db !== null) {
            try {
                $userModel = new User();
                $params['userCount'] = $userModel->getCount();
            } catch (\Exception $e) {
                $params['dbStatus'] = 'Database Connection Failed/Error: ' . $e->getMessage();
            }
        }

        return $this->render('home', $params);
    }

    /**
     * Render dynamic profile page using URL capture variable.
     */
    public function profile(string $id): string
    {
        return $this->render('profile', [
            'title' => 'User Profile',
            'userId' => $id
        ]);
    }

    /**
     * Render contact form and process form submissions.
     */
    public function contact(): string
    {
        $request = Application::$app->request;
        $message = '';
        $body = [];

        if ($request->isPost()) {
            $body = $request->getBody();
            
            // Basic input check
            $name = $body['name'] ?? '';
            $email = $body['email'] ?? '';
            $msgContent = $body['message'] ?? '';

            if (empty($name) || empty($email) || empty($msgContent)) {
                $message = 'Error: Please fill out all fields.';
            } else {
                $message = "Success: Thank you {$name}! Your message has been received.";
            }
        }

        return $this->render('contact', [
            'title' => 'Contact Us',
            'message' => $message,
            'body' => $body
        ]);
    }
}
