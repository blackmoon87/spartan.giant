<?php

declare(strict_types=1);

namespace Spartan;

interface CacheDriverInterface
{
    public function put(string $key, mixed $value, int $ttl): void;
    public function get(string $key, mixed $default = null): mixed;
    public function has(string $key): bool;
    public function forget(string $key): void;
    public function flush(): void;
}
