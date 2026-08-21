<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use Spartan\Controller;
use App\Services\AnalyticsService;

class AnalyticsApiController extends Controller
{
    public function __construct(private AnalyticsService $analyticsService)
    {
        parent::__construct();
    }

    public function summary(): void
    {
        $summary = $this->analyticsService->getSummary();

        $this->json([
            'status' => 'success',
            'data'   => $summary,
        ]);
    }
}
