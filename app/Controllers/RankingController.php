<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Stats;

final class RankingController extends Controller
{
    public function index(Request $request): Response
    {
        $stats = $this->app->model(Stats::class);
        $years = $stats->years();
        $q = $request->query('ano');
        $year = $q === 'geral' ? null : (preg_match('/^\d{4}$/', $q) ? (int) $q : (int) date('Y'));
        return $this->view('members/ranking', [
            'title' => $year ? "Ranking {$year}" : 'Ranking geral',
            'metaDescription' => 'Ranking dos trilheiros da Ducks Offroad: trilhas completadas e quilômetros rodados.',
            'ranking' => $stats->ranking($year),
            'year' => $year,
            'years' => $years,
        ]);
    }
}
