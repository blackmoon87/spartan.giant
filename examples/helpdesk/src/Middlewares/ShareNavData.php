<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Models\Notification;
use Spartan\Application;
use Spartan\Middleware;
use Spartan\Request;
use Spartan\Response;

/**
 * Global middleware: gives every view the current path (active nav link)
 * and the logged-in user's unread notification count (nav badge). Views never query the database
 * themselves — data reaches them from here or from the controller.
 */
class ShareNavData extends Middleware
{
    public function execute(Request $request, Response $response): void
    {
        $userId = Application::$app->session->get('user_id');
        $unread = 0;
        if ($userId !== null && !str_starts_with($request->getPath(), '/api/')) {
            $unread = (new Notification())->unreadCount((int) $userId);
        }
        Application::$app->view->share('unreadCount', $unread);
        Application::$app->view->share('currentPath', $request->getPath());
    }
}
