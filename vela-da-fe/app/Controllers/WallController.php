<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Candle;
use App\Models\CandleType;

/** Mural de velas: acesas agora e "memorial" (as que se apagaram nos últimos 30 dias). */
final class WallController extends Controller
{
    private const PER_PAGE = 24;

    public function index(Request $request): Response
    {
        $memorialOn = $this->app->settings()->bool('wall_show_memorial');
        $tab = $request->query('aba') === 'memorial' && $memorialOn ? 'memorial' : 'acesas';
        $filters = [
            'category' => $request->query('categoria'),
            'type' => $request->query('tipo'),
            'sort' => array_key_exists($request->query('ordem'), Candle::SORTS) ? $request->query('ordem') : 'recent',
        ];
        $result = $this->app->model(Candle::class)->wall($tab === 'memorial' ? 'memorial' : 'lit', $filters, $this->pageParam($request), self::PER_PAGE);
        return $this->view('candle/wall', $result + [
            'title' => $tab === 'memorial' ? 'Memorial de velas' : 'Mural de velas',
            'metaDescription' => 'Veja as velas de fé acesas agora e reze pelas intenções da nossa comunidade.',
            'tab' => $tab,
            'memorialOn' => $memorialOn,
            'filters' => $filters,
            'types' => $this->app->model(CandleType::class)->active(),
            'base' => '/velas',
            'params' => ['aba' => $tab === 'memorial' ? 'memorial' : '', 'categoria' => $filters['category'], 'tipo' => $filters['type'], 'ordem' => $filters['sort'] === 'recent' ? '' : $filters['sort']],
        ]);
    }
}
