<?php

declare(strict_types=1);

namespace App\Models;

use Spartan\Model;
use Spartan\RelationQuery;

class Product extends Model
{
    protected string $table = 'products';
    protected bool $timestamps = true;

    public function category(): RelationQuery
    {
        return $this->belongsTo(Category::class, foreignKey: 'category_id');
    }

    public function orderItems(): RelationQuery
    {
        return $this->hasMany(OrderItem::class, foreignKey: 'product_id');
    }
}
