<?php

declare(strict_types=1);

namespace App\Models;

use Spartan\Model;

class AuditLog extends Model
{
    protected string $table = 'audit_logs';
}
