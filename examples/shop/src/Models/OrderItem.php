<?php

declare(strict_types=1);

namespace App\Models;

use Spartan\Model;
use Spartan\RelationQuery;

class OrderItem extends Model
{
    protected string $table = 'order_items';

    public function order(): RelationQuery
    {
        return $this->belongsTo(Order::class, foreignKey: 'order_id');
    }

    public function product(): RelationQuery
    {
        return $this->belongsTo(Product::class, foreignKey: 'product_id');
    }
}
