<?php

declare(strict_types=1);

namespace App\Models;

use Spartan\Model;
use Spartan\Traits\HasAuthorization;

class User extends Model
{
    use HasAuthorization;

    protected string $table = 'users';
    protected bool $timestamps = true;
}
