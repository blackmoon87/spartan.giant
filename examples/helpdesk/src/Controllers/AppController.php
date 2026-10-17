<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Ticket;
use Spartan\Controller;
use Spartan\Gate;

/** Helpers shared by the web controllers. */
abstract class AppController extends Controller
{
    /** The logged-in user (routes using it carry the 'auth' middleware). */
    protected function user(): object
    {
        $user = $this->auth->user();
        if ($user === null) {
            throw new \LogicException('This action requires the auth middleware.');
        }
        return $user;
    }

    protected static function isId(string $value): bool
    {
        return $value !== '' && ctype_digit($value) && strlen($value) < 10;
    }

    protected function notFound(): string
    {
        $this->response->setStatusCode(404);
        return $this->render('error_404', ['message' => trans('app.errors.not_found')]);
    }

    protected function forbidden(): string
    {
        $this->response->setStatusCode(403);
        return $this->render('error_403', ['message' => trans('app.errors.forbidden')]);
    }

    /**
     * Load a ticket the current user may $ability, or answer 404/403.
     * Returns the response body instead of the ticket when access fails.
     * $id is the raw route segment: anything but digits is a 404, not a 500.
     */
    protected function ticketFor(string $id, string $ability): Ticket|string
    {
        $ticket = self::isId($id) ? (new Ticket())->findInstance((int) $id) : null;
        if ($ticket === null) {
            return $this->notFound();
        }
        if (Gate::denies($ability, $ticket)) {
            return $this->forbidden();
        }
        return $ticket;
    }
}
