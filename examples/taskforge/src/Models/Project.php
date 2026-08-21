<?php

declare(strict_types=1);

namespace App\Models;

use Spartan\Model;
use Spartan\RelationQuery;

class Project extends Model
{
    protected string $table = 'projects';
    protected bool $timestamps = true;

    /**
     * A project has many tasks.
     */
    public function tasks(): RelationQuery
    {
        return $this->hasMany(Task::class, 'project_id');
    }

    /**
     * A project belongs to a user (owner).
     */
    public function owner(): RelationQuery
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
