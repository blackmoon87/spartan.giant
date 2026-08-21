<?php

declare(strict_types=1);

namespace App\Models;

use Spartan\Model;
use Spartan\RelationQuery;

class Category extends Model
{
    protected string $table = 'categories';

    public function posts(): RelationQuery
    {
        return $this->hasMany(Post::class, foreignKey: 'category_id');
    }
}
