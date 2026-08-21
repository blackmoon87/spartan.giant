<?php

declare(strict_types=1);

namespace Spartan\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class RequirePermission
{
    public array $permissions;

    public function __construct(string ...$permissions)
    {
        $this->permissions = $permissions;
    }
}
