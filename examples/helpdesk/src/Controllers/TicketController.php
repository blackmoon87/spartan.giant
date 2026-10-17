<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Requests\StoreReplyRequest;
use App\Controllers\Requests\StoreTicketRequest;
use App\Controllers\Requests\UpdateTicketRequest;
use App\Models\Activity;
use App\Models\Attachment;
use App\Models\Reply;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AttachmentRejected;
use App\Services\TicketService;
use Spartan\Gate;

class TicketController extends AppController
{
    public function __construct(private TicketService $service)
    {
        parent::__construct();
    }

    public function index(): string
    {
        $user    = $this->user();
        $isStaff = $user->hasPermission('view_all_tickets');
        $filters = [
            'status' => (string) $this->request->get('status', ''),
            'q'      => trim((string) $this->request->get('q', '')),
            'sort'   => (string) $this->request->get('sort', 'newest'),
        ];
        $mine = $isStaff && $this->request->get('mine') === '1';

        $page = (new Ticket())->search(
            $filters,
            $isStaff ? null : (int) $user->id,
            $mine ? (int) $user->id : null,
            max(1, (int) $this->request->get('page', 1))
        );

        return $this->render('tickets/index', [
            'page'    => $page,
            'filters' => $filters,
            'mine'    => $mine,
            'isStaff' => $isStaff,
            // Kept in pagination links so page 2 has the same filters.
            'query'   => array_filter($filters + ['mine' => $mine ? '1' : ''], fn ($v) => $v !== ''),
        ]);
    }

    public function create(): string
    {
        return $this->render('tickets/create');
    }

    public function store(StoreTicketRequest $request): void
    {
        $data = $request->getBody();
        try {
            $id = $this->service->open($this->user(), $data, $request->upload('attachment'));
        } catch (AttachmentRejected $e) {
            $this->session->setFlash('error', trans($e->getMessage(), ['max' => '2 MB']));
            $this->session->setFlash('old_input', [
                'subject'  => $data['subject'],
                'body'     => $data['body'],
                'priority' => $data['priority'],
            ]);
            $this->redirect('/tickets/create');
            return;
        }

        $this->session->setFlash('success', trans('tickets.created', ['id' => $id]));
        $this->redirect('/tickets/' . $id);
    }

    public function show(string $id): string
    {
        $ticket = $this->ticketFor($id, 'view');
        if (is_string($ticket)) {
            return $ticket;
        }
        $canManage = Gate::allows('manage', $ticket);
        $row       = $ticket->toArray();

        return $this->render('tickets/show', [
            'ticket'      => $row,
            'ticketModel' => $ticket,                         // for @can('reply', $ticketModel)
            'owner'       => (new Ticket())->owner()->for($row),
            'assignee'    => $row['assignee_id'] !== null ? (new Ticket())->assignee()->for($row) : null,
            'replies'     => (new Reply())->forTicket((int) $id, $canManage),
            'attachments' => (new Attachment())->forTicket((int) $id),
            'activities'  => $canManage ? (new Activity())->forTicket((int) $id) : [],
            'staff'       => $canManage ? (new User())->staff() : [],
            'canManage'   => $canManage,
        ]);
    }

    /** PATCH /tickets/{id}: status and assignee (staff). */
    public function update(string $id, UpdateTicketRequest $request): string
    {
        $ticket = $this->ticketFor($id, 'manage');
        if (is_string($ticket)) {
            return $ticket;
        }

        $assignee = $request->post('assignee_id');
        $assigneeId = ($assignee === null || $assignee === '') ? null : (int) $assignee;
        if ($assigneeId !== null && !array_key_exists($assigneeId, (new User())->staff())) {
            $assigneeId = null;                                // only staff can be assigned
        }

        $this->service->update($ticket, $this->user(), (string) $request->post('status'), $assigneeId);
        $this->session->setFlash('success', trans('tickets.updated'));
        $this->redirect('/tickets/' . $id);
        return '';
    }

    /** POST /tickets/{id}/replies */
    public function reply(string $id, StoreReplyRequest $request): string
    {
        $ticket = $this->ticketFor($id, 'reply');
        if (is_string($ticket)) {
            return $ticket;
        }

        $this->service->reply($ticket, $this->user(), (string) $request->post('body'), $request->post('internal') === '1');
        $this->session->setFlash('success', trans('tickets.reply_posted'));
        $this->redirect('/tickets/' . $id . '#replies');
        return '';
    }
}
