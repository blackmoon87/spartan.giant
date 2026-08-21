<?php

declare(strict_types=1);

namespace App\Models;

use Spartan\Model;

class Comment extends Model
{
    protected string $table = 'comments';
    protected bool $timestamps = false;
}
