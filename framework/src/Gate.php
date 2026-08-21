<?php

declare(strict_types=1);

namespace Spartan;

class Gate
{
    public static array $abilities = [];
    public static array $policies = [];

    /**
     * Define a dynamic ability callback.
     */
    public static function define(string $ability, callable $callback): void
    {
        self::$abilities[$ability] = $callback;
    }

    /**
     * Map a model class to its policy class.
     */
    public static function policy(string $modelClass, string $policyClass): void
    {
        self::$policies[$modelClass] = $policyClass;
    }

    /**
     * Check if the authenticated user has the given ability.
     */
    public static function check(string $ability, mixed ...$arguments): bool
    {
        $user = self::resolveUser();
        return self::inspect($user, $ability, ...$arguments);
    }

    /**
     * Helper check: returns true if allowed.
     */
    public static function allows(string $ability, mixed ...$arguments): bool
    {
        return self::check($ability, ...$arguments);
    }

    /**
     * Helper check: returns true if denied.
     */
    public static function denies(string $ability, mixed ...$arguments): bool
    {
        return !self::allows($ability, ...$arguments);
    }

    /**
     * Create an evaluator instance for a specific user.
     */
    public static function forUser(?object $user): GateEvaluator
    {
        return new GateEvaluator($user);
    }

    /**
     * Inspect a specific user for an ability.
     */
    public static function inspect(?object $user, string $ability, mixed ...$arguments): bool
    {
        // 1. Direct Ability Callback Check
        if (isset(self::$abilities[$ability])) {
            return (bool) call_user_func(self::$abilities[$ability], $user, ...$arguments);
        }

        // 2. Policy-Based Model Check
        if (!empty($arguments)) {
            $firstArg = $arguments[0];
            $modelClass = is_object($firstArg) ? get_class($firstArg) : (is_string($firstArg) ? $firstArg : null);

            if ($modelClass && isset(self::$policies[$modelClass])) {
                $policyClass = self::$policies[$modelClass];
                $policyInstance = new $policyClass();
                
                if (method_exists($policyInstance, $ability)) {
                    return (bool) call_user_func([$policyInstance, $ability], $user, ...$arguments);
                }
            }
        }

        return false;
    }

    /**
     * Resolve the active authenticated user.
     */
    public static function resolveUser(): ?object
    {
        if (!isset(Application::$app)) {
            return null;
        }

        if (Application::$app->container->has('auth_user')) {
            return Application::$app->container->make('auth_user');
        }

        if (Application::$app->container->has(AuthInterface::class)) {
            return Application::$app->container->make(AuthInterface::class)->user();
        }

        return null;
    }
}

