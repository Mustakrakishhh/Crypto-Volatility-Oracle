<?php

namespace App\Http\Controllers;

use App\Actions\GetOracleDashboardData;
use Inertia\Inertia;
use Inertia\Response;

class OracleDashboardController extends Controller
{
    public function __invoke(GetOracleDashboardData $getDashboardData): Response
    {
        return Inertia::render('OracleDashboard', $getDashboardData->handle());
    }
}
