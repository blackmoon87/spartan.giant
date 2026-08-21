<?php

declare(strict_types=1);

namespace App\Models;

use Spartan\Model;
use Spartan\RelationQuery;

class Comment extends Model
{
    protected string $table = 'comments';

    public function post(): RelationQuery
    {
        return $this->belongsTo(Post::class, foreignKey: 'post_id');
    }
}
