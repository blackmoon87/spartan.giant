<?php

declare(strict_types=1);

namespace App\Models;

use Spartan\Model;
use Spartan\QueryBuilder;
use Spartan\RelationQuery;

/**
 * Columns of a hydrated ticket (findInstance()).
 *
 * @property int|string      $id
 * @property int|string      $user_id
 * @property int|string|null $assignee_id
 * @property string          $subject
 * @property string          $body
 * @property string          $status
 * @property string          $priority
 * @property string|null     $created_at
 * @property string|null     $updated_at
 */
class Ticket extends Model
{
    protected string $table = 'tickets';
    protected bool $timestamps = true;

    public const STATUSES   = ['open', 'pending', 'resolved', 'closed'];
    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    /** Sort options offered to users → the real ORDER BY column (allowlist). */
    private const SORTS = [
        'newest'   => ['created_at', 'DESC'],
        'oldest'   => ['created_at', 'ASC'],
        'updated'  => ['updated_at', 'DESC'],
    ];

    public function owner(): RelationQuery
    {
        return $this->belongsTo(User::class, foreignKey: 'user_id');
    }

    public function assignee(): RelationQuery
    {
        return $this->belongsTo(User::class, foreignKey: 'assignee_id');
    }

    public function replies(): RelationQuery
    {
        return $this->hasMany(Reply::class, foreignKey: 'ticket_id');
    }

    /**
     * Filtered, sorted, paginated ticket list. $ownerId limits the list to one
     * customer's tickets; null means every ticket (staff).
     *
     * @param array{status?:string, q?:string, sort?:string} $filters
     * @return array{data:array, total:int, per_page:int, current_page:int, last_page:int}
     */
    public function search(array $filters, ?int $ownerId, ?int $assigneeId, int $page, int $perPage = 10): array
    {
        [$column, $direction] = self::SORTS[$filters['sort'] ?? ''] ?? self::SORTS['newest'];

        $query = $this->scoped($ownerId);
        if (in_array($filters['status'] ?? '', self::STATUSES, true)) {
            $query->where('status', $filters['status']);
        }
        if (($filters['q'] ?? '') !== '') {
            $like = '%' . $filters['q'] . '%';
            $query->where(function (QueryBuilder $w) use ($like): void {
                $w->where('subject', $like, 'LIKE')->orWhere('body', $like, 'LIKE');
            });
        }
        if ($assigneeId !== null) {
            $query->where('assignee_id', $assigneeId);
        }

        $result = $query->orderBy($column, $direction)->orderBy('id', 'DESC')->paginate($perPage, $page);

        // One query each for owners and assignees, not one per row.
        $result['data'] = $this->owner()->loadFor($result['data'], 'owner');
        $result['data'] = $this->assignee()->loadFor($result['data'], 'assignee');

        return $result;
    }

    /** @return array<string, int> status => count, every status present */
    public function countsByStatus(?int $ownerId): array
    {
        $counts = array_fill_keys(self::STATUSES, 0);
        $rows = $this->scoped($ownerId)
            ->select('status', 'COUNT(*) as total')
            ->groupBy('status')
            ->get();
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }
        return $counts;
    }

    public function countUnassignedOpen(): int
    {
        return $this->table()->whereNull('assignee_id')->where('status', ['open', 'pending'])->count();
    }

    public function recent(?int $ownerId, int $limit = 5): array
    {
        $rows = $this->scoped($ownerId)->orderBy('updated_at', 'DESC')->limit($limit)->get();
        return $this->owner()->loadFor($rows, 'owner');
    }

    private function scoped(?int $ownerId): QueryBuilder
    {
        $query = $this->table();
        return $ownerId === null ? $query : $query->where('user_id', $ownerId);
    }
}
