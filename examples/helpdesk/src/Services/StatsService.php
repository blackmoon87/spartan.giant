<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ticket;
use Spartan\Cache;

/**
 * Dashboard numbers, cached for five minutes under the "tickets" tag.
 * TicketService flushes the tag whenever a ticket changes, so the cache
 * never shows stale counts for long — and the dashboard costs no queries
 * on a cache hit.
 */
class StatsService
{
    private const TTL = 300;

    public function __construct(private Ticket $tickets) {}

    /** @return array{counts: array<string,int>, unassigned: ?int} */
    public function forDashboard(?int $ownerId): array
    {
        $scope = $ownerId === null ? 'all' : 'user:' . $ownerId;

        return Cache::tags(['tickets'])->remember("stats:{$scope}", self::TTL, fn (): array => [
            'counts'     => $this->tickets->countsByStatus($ownerId),
            'unassigned' => $ownerId === null ? $this->tickets->countUnassignedOpen() : null,
        ]);
    }

    public static function flush(): void
    {
        Cache::tags(['tickets'])->flush();
    }
}
