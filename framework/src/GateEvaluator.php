<?php

declare(strict_types=1);

namespace Spartan;

/**
 * GateEvaluator — Per-user authorization evaluator.
 * Returned by Gate::forUser($user) to check abilities for a specific user
 * without changing the global authenticated user context.
 */
class GateEvaluator
{
    private ?object $user;

    public function __construct(?object $user)
    {
        $this->user = $user;
    }

    public function check(string $ability, mixed ...$arguments): bool
    {
        return Gate::inspect($this->user, $ability, ...$arguments);
    }

    public function allows(string $ability, mixed ...$arguments): bool
    {
        return $this->check($ability, ...$arguments);
    }

    public function denies(string $ability, mixed ...$arguments): bool
    {
        return !$this->allows($ability, ...$arguments);
    }
}
