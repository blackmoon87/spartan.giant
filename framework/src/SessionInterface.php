<?php

declare(strict_types=1);

namespace Spartan;

interface SessionInterface
{
    public function set(string $key, mixed $value): void;
    public function get(string $key, mixed $default = null): mixed;
    public function remove(string $key): void;
    public function setFlash(string $key, mixed $message): void;
    public function getFlash(string $key, mixed $default = null): mixed;
    public function removeFlashMessages(): void;
    public function destroy(): void;
    public function regenerate(): void;

    /**
     * Persist and close the session, releasing its file lock.
     * Called automatically at the end of the request lifecycle.
     */
    public function close(): void;
}
