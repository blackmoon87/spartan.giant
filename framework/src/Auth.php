<?php

declare(strict_types=1);

namespace Spartan;

class Auth implements AuthInterface
{
    protected SessionInterface $session;
    protected ?object $user = null;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    /**
     * Get the authenticated user instance.
     */
    public function user(): ?object
    {
        $userId = $this->session->get('user_id');
        $cachedUserId = $this->user ? ($this->user->id ?? null) : null;

        if ((string)$userId !== (string)$cachedUserId) {
            $this->user = null;
            if ($userId) {
                $userClass = Application::$app->config['auth']['model'] ?? 'App\\Models\\User';
                if (class_exists($userClass)) {
                    try {
                        $userModel = new $userClass();
                        $userInstance = $userModel->findInstance($userId);
                        if ($userInstance) {
                            $this->user = $userInstance;
                        }
                    } catch (\Throwable $e) {
                        // Graceful fallback
                    }
                }
            }
        }

        return $this->user;
    }

    /**
     * Forget the cached user instance (worker mode / after logout).
     */
    public function forgetUser(): void
    {
        $this->user = null;
    }

    /**
     * Get the authenticated user's ID.
     */
    public function id(): int|string|null
    {
        $user = $this->user();
        return $user ? ($user->id ?? null) : null;
    }

    /**
     * Check if the current user is authenticated.
     */
    public function check(): bool
    {
        return $this->user() !== null;
    }
}
