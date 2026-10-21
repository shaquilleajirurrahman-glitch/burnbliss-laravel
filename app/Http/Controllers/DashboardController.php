<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Dashboard sementara per role (Fase 2). Hak akses diatur oleh middleware "role" di routes/web.php.
 */
class DashboardController extends Controller
{
    public function admin(): View
    {
        return view('dashboard.admin');
    }

    public function koordinator(): View
    {
        return view('dashboard.koordinator');
    }

    public function staf(): View
    {
        return view('dashboard.staf');
    }
}
