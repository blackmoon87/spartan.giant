<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Ticket;
use App\Services\StatsService;

class DashboardController extends AppController
{
    public function __construct(private StatsService $stats)
    {
        parent::__construct();
    }

    /** Landing page for guests; logged-in users go straight to their dashboard. */
    public function home(): string
    {
        if ($this->auth->check()) {
            $this->redirect('/dashboard');
            return '';
        }
        return $this->render('home');
    }

    public function index(): string
    {
        $user    = $this->user();
        $isStaff = $user->hasPermission('view_all_tickets');
        $ownerId = $isStaff ? null : (int) $user->id;

        return $this->render('dashboard/index', [
            'stats'   => $this->stats->forDashboard($ownerId),
            'recent'  => (new Ticket())->recent($ownerId, 6),
            'isStaff' => $isStaff,
        ]);
    }
}
