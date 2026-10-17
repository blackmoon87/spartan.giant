<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Ticket;

/**
 * Who may do what with one ticket. Registered in public/index.php with
 * Gate::policy(Ticket::class, TicketPolicy::class); checked with
 * Gate::allows('view', $ticket) and @can('reply', $ticket).
 */
class TicketPolicy
{
    /** Owners see their own tickets; staff see every ticket. */
    public function view(?object $user, Ticket $ticket): bool
    {
        if ($user === null) {
            return false;
        }
        return (int) $ticket->user_id === (int) $user->id || $user->hasPermission('view_all_tickets');
    }

    /** Anyone who can see an open ticket can answer it. */
    public function reply(?object $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket) && $ticket->status !== 'closed';
    }

    /** Status, assignee and internal notes are staff-only. */
    public function manage(?object $user, Ticket $ticket): bool
    {
        return $user !== null && $user->hasPermission('manage_tickets');
    }
}
