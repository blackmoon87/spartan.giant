<?php

declare(strict_types=1);

namespace Spartan;

interface AuthInterface
{
    /**
     * Get the authenticated user instance.
     */
    public function user(): ?object;

    /**
     * Get the authenticated user's ID.
     */
    public function id(): int|string|null;

    /**
     * Check if the current user is authenticated.
     */
    public function check(): bool;
}
