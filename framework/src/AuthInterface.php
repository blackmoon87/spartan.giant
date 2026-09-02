<?php

declare(strict_types=1);

namespace Spartan;

interface AuthInterface
{
    /**
     * Get the authenticated user instance.
     * Pass $fresh = true to bypass per-request identity cache and re-query the database.
     */
    public function user(bool $fresh = false): ?object;

    /**
     * Get the authenticated user's ID.
     */
    public function id(): int|string|null;

    /**
     * Check if the current user is authenticated.
     */
    public function check(): bool;
}
