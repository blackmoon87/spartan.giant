<?php

declare(strict_types=1);

namespace App\Models;

use Spartan\Model;
use Spartan\RelationQuery;

class Order extends Model
{
    protected string $table = 'orders';
    protected bool $timestamps = true;

    public function user(): RelationQuery
    {
        return $this->belongsTo(User::class, foreignKey: 'user_id');
    }

    public function items(): RelationQuery
    {
        return $this->hasMany(OrderItem::class, foreignKey: 'order_id');
    }
}
