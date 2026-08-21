<?php

declare(strict_types=1);

namespace App\Models;

use Spartan\Model;

class ActivityLog extends Model
{
    protected string $table = 'activity_logs';
    protected bool $timestamps = false;
}
