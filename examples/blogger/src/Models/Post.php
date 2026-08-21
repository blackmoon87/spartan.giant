<?php

declare(strict_types=1);

namespace App\Models;

use Spartan\Model;
use Spartan\RelationQuery;

class Post extends Model
{
    protected string $table = 'posts';
    protected bool $timestamps = true;

    public function user(): RelationQuery
    {
        return $this->belongsTo(User::class, foreignKey: 'user_id');
    }

    public function category(): RelationQuery
    {
        return $this->belongsTo(Category::class, foreignKey: 'category_id');
    }

    public function comments(): RelationQuery
    {
        return $this->hasMany(Comment::class, foreignKey: 'post_id');
    }
}
