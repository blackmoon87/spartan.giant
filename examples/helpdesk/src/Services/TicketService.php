<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Attachment;
use App\Models\Reply;
use App\Models\Ticket;
use App\Models\User;
use Spartan\Application;
use Spartan\UploadedFile;

/**
 * Every change to a ticket goes through here: the database write (in a
 * transaction when it spans tables), the activity log, notifications and the
 * stats cache. Controllers — web and API — stay thin and identical.
 *
 * Events dispatched:
 *   ticket.activity  (sync)  → RecordActivity:   the ticket's history
 *   ticket.notify    (async) → SendNotification: queued, run by `php spartan worker`
 */
class TicketService
{
    public function __construct(
        private Ticket $tickets,
        private Reply $replies,
        private Attachment $attachments,
        private User $users,
        private AttachmentService $files,
    ) {}

    /**
     * Open a new ticket. The attachment (optional) is checked and stored
     * first, so a rejected file never leaves a ticket behind.
     *
     * @throws AttachmentRejected
     */
    public function open(object $user, array $input, ?UploadedFile $upload): int
    {
        $file = $upload !== null ? $this->files->store($upload) : null;

        try {
            $ticketId = (int) $this->tickets->transaction(function () use ($user, $input, $file): int {
                $id = (int) $this->tickets->create([
                    'user_id'  => (int) $user->id,
                    'subject'  => trim((string) $input['subject']),
                    'body'     => trim((string) $input['body']),
                    'priority' => (string) $input['priority'],
                    'status'   => 'open',
                ]);
                if ($file !== null) {
                    $this->attachments->add($id, (int) $user->id, $file);
                }
                return $id;
            });
        } catch (\Throwable $e) {
            if ($file !== null) {
                $this->files->discard($file['stored_name']);
            }
            throw $e;
        }

        $this->activity($ticketId, (int) $user->id, 'created');
        $this->notify(
            array_diff($this->users->staffIds(), [(int) $user->id]),
            $ticketId,
            'notifications.ticket_created',
            ['subject' => $input['subject'], 'name' => $user->name],
            "created:{$ticketId}"
        );
        Application::$app->logger->channel('tickets')->info('Ticket {id} opened by user {user}', ['id' => $ticketId, 'user' => $user->id]);
        StatsService::flush();

        return $ticketId;
    }

    /** Add a reply; staff may mark it internal (hidden from the customer). */
    public function reply(Ticket $ticket, object $user, string $body, bool $internal): int
    {
        $isStaff  = $user->hasPermission('manage_tickets');
        $internal = $internal && $isStaff;

        $replyId = (int) $this->replies->create([
            'ticket_id'   => (int) $ticket->id,
            'user_id'     => (int) $user->id,
            'body'        => trim($body),
            'is_internal' => $internal ? 1 : 0,
        ]);
        $this->tickets->save($ticket->id, []);            // bump updated_at

        $this->activity((int) $ticket->id, (int) $user->id, $internal ? 'noted' : 'replied');

        if (!$internal) {
            if ($isStaff) {
                $recipients = [(int) $ticket->user_id];
                $key = 'notifications.agent_replied';
            } else {
                $recipients = $ticket->assignee_id !== null ? [(int) $ticket->assignee_id] : $this->users->staffIds();
                $key = 'notifications.customer_replied';
            }
            $this->notify(
                array_diff($recipients, [(int) $user->id]),
                (int) $ticket->id,
                $key,
                ['subject' => $ticket->subject, 'name' => $user->name],
                "reply:{$replyId}"
            );
        }

        StatsService::flush();
        return $replyId;
    }

    /** Change status and/or assignee (staff only — checked by the caller's policy). */
    public function update(Ticket $ticket, object $user, string $status, ?int $assigneeId): void
    {
        $changes = [];
        if ($status !== $ticket->status) {
            $changes['status'] = $status;
        }
        if ($assigneeId !== ($ticket->assignee_id === null ? null : (int) $ticket->assignee_id)) {
            $changes['assignee_id'] = $assigneeId;
        }
        if ($changes === []) {
            return;
        }

        $this->tickets->save($ticket->id, $changes);
        $stamp = gmdate('YmdHis');

        if (isset($changes['status'])) {
            $this->activity((int) $ticket->id, (int) $user->id, 'status_changed', $status);
            $this->notify(
                array_diff([(int) $ticket->user_id], [(int) $user->id]),
                (int) $ticket->id,
                'notifications.status_changed',
                ['subject' => $ticket->subject, 'status' => $status],
                "status:{$ticket->id}:{$status}:{$stamp}"
            );
        }
        if (array_key_exists('assignee_id', $changes)) {
            $assignee = $assigneeId === null ? null : $this->users->find($assigneeId);
            $this->activity((int) $ticket->id, (int) $user->id, $assignee === null ? 'unassigned' : 'assigned', $assignee['name'] ?? null);
            if ($assigneeId !== null && $assigneeId !== (int) $user->id) {
                $this->notify(
                    [$assigneeId],
                    (int) $ticket->id,
                    'notifications.assigned',
                    ['subject' => $ticket->subject, 'name' => $user->name],
                    "assigned:{$ticket->id}:{$assigneeId}:{$stamp}"
                );
            }
        }

        StatsService::flush();
    }

    private function activity(int $ticketId, ?int $userId, string $action, ?string $details = null): void
    {
        Application::$app->events->dispatch('ticket.activity', [
            'ticket_id' => $ticketId,
            'user_id'   => $userId,
            'action'    => $action,
            'details'   => $details,
        ]);
    }

    /** Queue one notification job for a set of users (payload: plain arrays and ids). */
    private function notify(array $userIds, int $ticketId, string $messageKey, array $params, string $eventKey): void
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if ($userIds === []) {
            return;
        }
        Application::$app->events->dispatch('ticket.notify', [
            'user_ids'    => $userIds,
            'ticket_id'   => $ticketId,
            'message_key' => $messageKey,
            'params'      => $params,
            'event_key'   => $eventKey,
        ]);
    }
}
