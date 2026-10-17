<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\Requests\StoreTicketRequest;
use App\Models\Reply;
use App\Models\Ticket;
use App\Services\TicketService;
use Spartan\Controller;
use Spartan\Gate;

/**
 * Token-authenticated ticket API. The same model scoping and policies as the
 * web pages apply: customers see their own tickets, staff see every ticket.
 * Responses carry an explicit field list — never whole user rows (which
 * hold password hashes).
 */
class TicketApiController extends Controller
{
    public function __construct(private TicketService $service)
    {
        parent::__construct();
    }

    /** GET /api/v1/tickets?status=&q=&sort=&page= */
    public function index(): void
    {
        $user = $this->tokenUser();
        $page = (new Ticket())->search(
            [
                'status' => (string) $this->request->get('status', ''),
                'q'      => trim((string) $this->request->get('q', '')),
                'sort'   => (string) $this->request->get('sort', 'newest'),
            ],
            $user->hasPermission('view_all_tickets') ? null : (int) $user->id,
            null,
            max(1, (int) $this->request->get('page', 1)),
            min(50, max(1, (int) $this->request->get('per_page', 15)))
        );

        $page['data'] = array_map(fn (array $t): array => $this->summary($t), $page['data']);
        $this->json($page);
    }

    /** GET /api/v1/tickets/{id} */
    public function show(string $id): void
    {
        $ticket = ctype_digit($id) ? (new Ticket())->findInstance((int) $id) : null;
        if ($ticket === null) {
            $this->json(['error' => trans('app.errors.not_found')], 404);
            return;
        }
        if (Gate::denies('view', $ticket)) {
            $this->json(['error' => trans('app.errors.forbidden')], 403);
            return;
        }

        $row = $ticket->toArray();
        $row['owner']    = (new Ticket())->owner()->for($row);
        $row['assignee'] = $row['assignee_id'] !== null ? (new Ticket())->assignee()->for($row) : null;

        $data = $this->summary($row) + ['body' => $row['body']];
        $data['replies'] = array_map(fn (array $r): array => [
            'id'          => (int) $r['id'],
            'body'        => $r['body'],
            'internal'    => (bool) $r['is_internal'],
            'author'      => $this->person($r['author']),
            'created_at'  => $r['created_at'],
        ], (new Reply())->forTicket((int) $id, Gate::allows('manage', $ticket)));

        $this->json(['data' => $data]);
    }

    /** POST /api/v1/tickets  {subject, body, priority} */
    public function store(StoreTicketRequest $request): void
    {
        $id = $this->service->open($this->tokenUser(), $request->getBody(), null);
        $this->json(['message' => trans('tickets.created', ['id' => $id]), 'data' => ['id' => $id]], 201);
    }

    private function tokenUser(): object
    {
        $user = Gate::resolveUser();                    // set by ApiTokenAuth
        if ($user === null) {
            throw new \LogicException('This action requires the api.auth middleware.');
        }
        return $user;
    }

    private function summary(array $t): array
    {
        return [
            'id'         => (int) $t['id'],
            'subject'    => $t['subject'],
            'status'     => $t['status'],
            'priority'   => $t['priority'],
            'owner'      => $this->person($t['owner'] ?? null),
            'assignee'   => $this->person($t['assignee'] ?? null),
            'created_at' => $t['created_at'],
            'updated_at' => $t['updated_at'],
        ];
    }

    /** @return array{id:int, name:string}|null */
    private function person(?array $user): ?array
    {
        return $user === null ? null : ['id' => (int) $user['id'], 'name' => (string) $user['name']];
    }
}
