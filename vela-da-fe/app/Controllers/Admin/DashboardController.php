<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Candle;
use App\Models\Stats;

final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $stats = new Stats($this->db);
        $candles = $this->app->model(Candle::class);
        return $this->adminView('dashboard', [
            'title' => 'Painel',
            's' => $stats->dashboard(),
            'perDay' => $stats->perDay(14),
            'byCategory' => $stats->byCategory(),
            'byType' => $stats->byType(),
            'awaiting' => $candles->adminList(['status' => 'pending_payment'], 1, 5)['items'],
            'moderation' => $candles->adminList(['status' => 'moderation'], 1, 5)['items'],
            'pixOk' => $this->app->pix()->isConfigured(),
            'mailDriver' => $this->app->mailer()->driver(),
        ]);
    }
}
