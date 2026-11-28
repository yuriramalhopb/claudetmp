<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Candle;

/** Intenções para a Santa Missa: lista do período, folha para imprimir e marcação de "já rezadas". */
final class MassController extends Controller
{
    public function index(Request $request): Response
    {
        [$from, $to, $pending] = $this->range($request);
        return $this->adminView('mass/index', [
            'title' => 'Intenções para a missa',
            'items' => $this->app->model(Candle::class)->massIntentions($from, $to, $pending),
            'from' => $from, 'to' => $to, 'pending' => $pending,
        ]);
    }

    public function printSheet(Request $request): Response
    {
        [$from, $to, $pending] = $this->range($request);
        $items = $this->app->model(Candle::class)->massIntentions($from, $to, $pending);
        $this->app->audit('missa_impressa', "{$from} a {$to}");
        return $this->view('admin/mass/print', ['title' => 'Intenções da missa', 'items' => $items, 'from' => $from, 'to' => $to], 200, 'layouts/plain');
    }

    /** Marca como lidas todas as intenções do período. */
    public function markAll(Request $request): Response
    {
        [$from, $to] = $this->range($request);
        $model = $this->app->model(Candle::class);
        $n = 0;
        foreach ($model->massIntentions($from, $to, true) as $c) {
            $model->setMassDone((int) $c['id'], true);
            $n++;
        }
        $this->app->audit('missa_marcadas', "{$n} intenções ({$from} a {$to})");
        $this->flash('success', "{$n} intenção(ões) marcada(s) como rezada(s).");
        return $this->redirect('/admin/missas?de=' . $from . '&ate=' . $to);
    }

    /** @return array{0:string,1:string,2:bool} */
    private function range(Request $request): array
    {
        $valid = static function (string $v, string $default): string {
            $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);
            return ($d && $d->format('Y-m-d') === $v) ? $v : $default;
        };
        $from = $valid($request->input('de'), date('Y-m-d', strtotime('-7 days')));
        $to = $valid($request->input('ate'), date('Y-m-d'));
        $pending = $request->input('todas') !== '1';
        return [$from, $to, $pending];
    }
}
