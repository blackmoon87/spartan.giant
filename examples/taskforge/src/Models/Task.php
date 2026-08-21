<?php

declare(strict_types=1);

namespace App\Models;

use Spartan\Model;
use Spartan\RelationQuery;

class Task extends Model
{
    protected string $table = 'tasks';
    protected bool $timestamps = true;

    /**
     * A task belongs to a project.
     */
    public function project(): RelationQuery
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * A task has many comments.
     */
    public function comments(): RelationQuery
    {
        return $this->hasMany(Comment::class, 'task_id');
    }

    /**
     * A task has one assignee (user).
     */
    public function assignee(): RelationQuery
    {
        return $this->hasOne(User::class, 'id', 'assigned_to');
    }
}
