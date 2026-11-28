<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Candle;
use App\Models\CandleType;

/** Gestão das velas: moderação, confirmação do Pix, prolongar/apagar/remover e exportação. */
final class CandleController extends Controller
{
    private const PER_PAGE = 25;

    public function index(Request $request): Response
    {
        $filters = [
            'status' => $request->query('status'), 'q' => $request->query('q'), 'type' => $request->query('tipo'),
            'category' => $request->query('categoria'), 'mass' => $request->query('missa'),
        ];
        $result = $this->app->model(Candle::class)->adminList($filters, $this->pageParam($request), self::PER_PAGE);
        return $this->adminView('candles/index', $result + [
            'title' => 'Velas',
            'filters' => $filters,
            'types' => $this->app->model(CandleType::class)->all(),
            'base' => '/admin/velas',
            'params' => ['status' => $filters['status'], 'q' => $filters['q'], 'tipo' => $filters['type'], 'categoria' => $filters['category'], 'missa' => $filters['mass']],
        ]);
    }

    public function show(Request $request): Response
    {
        $c = $this->candle($request);
        $this->app->audit('vela_vista', '#' . $c['id']); // dados pessoais (e-mail) ficam registrados
        return $this->adminView('candles/show', ['title' => 'Vela #' . $c['id'], 'c' => $c, 'p' => Candle::present($c)]);
    }

    public function confirmPayment(Request $request): Response
    {
        $c = $this->candle($request);
        $model = $this->app->model(Candle::class);
        if ($model->activate((int) $c['id'], 'pix')) {
            $fresh = $model->findByCode($c['code']);
            $this->app->notifier()->paymentConfirmed($fresh, $model->rotateToken((int) $c['id']));
            $this->app->audit('pagamento_confirmado', '#' . $c['id'] . ' ' . money((int) $c['amount']));
            $this->flash('success', 'Pagamento confirmado: a vela foi acesa e a pessoa foi avisada (se informou e-mail).');
        } else {
            $this->flash('warning', 'Esta vela não estava aguardando pagamento.');
        }
        return $this->back('/admin/velas?status=pending_payment');
    }

    public function cancelPayment(Request $request): Response
    {
        $c = $this->candle($request);
        if ($c['status'] === 'pending_payment') {
            $this->app->model(Candle::class)->setStatus((int) $c['id'], 'cancelled');
            $this->app->audit('pedido_cancelado', '#' . $c['id']);
            $this->flash('success', 'Pedido cancelado.');
        }
        return $this->back('/admin/velas?status=pending_payment');
    }

    public function approve(Request $request): Response
    {
        $c = $this->candle($request);
        $this->app->model(Candle::class)->setModStatus((int) $c['id'], 'approved');
        $this->app->audit('texto_aprovado', '#' . $c['id']);
        $this->flash('success', 'Texto aprovado e publicado no mural.');
        return $this->back('/admin/velas?status=moderation');
    }

    public function reject(Request $request): Response
    {
        $c = $this->candle($request);
        $this->app->model(Candle::class)->setModStatus((int) $c['id'], 'rejected');
        $this->app->audit('texto_recusado', '#' . $c['id']);
        $this->flash('success', 'Texto recusado: a vela continua acesa, mas de forma anônima e sem o texto.');
        return $this->back('/admin/velas?status=moderation');
    }

    public function extend(Request $request): Response
    {
        $c = $this->candle($request);
        $hours = $request->int('hours', 0);
        if ($hours < 1 || $hours > 8760) {
            $this->flash('error', 'Informe de 1 a 8760 horas.');
        } else {
            $this->app->model(Candle::class)->extend((int) $c['id'], $hours);
            $this->app->audit('vela_prolongada', "#{$c['id']} +{$hours}h");
            $this->flash('success', 'Vela prolongada em ' . hours_human($hours) . '.');
        }
        return $this->redirect('/admin/velas/' . $c['id']);
    }

    public function extinguish(Request $request): Response
    {
        $c = $this->candle($request);
        $this->app->model(Candle::class)->extinguish((int) $c['id']);
        $this->app->audit('vela_apagada', '#' . $c['id']);
        $this->flash('success', 'Vela apagada (vai para o memorial).');
        return $this->redirect('/admin/velas/' . $c['id']);
    }

    public function remove(Request $request): Response
    {
        $c = $this->candle($request);
        $this->app->model(Candle::class)->setStatus((int) $c['id'], 'removed');
        $this->app->audit('vela_removida', '#' . $c['id']);
        $this->flash('success', 'Vela removida do site.');
        return $this->redirect('/admin/velas/' . $c['id']);
    }

    public function restore(Request $request): Response
    {
        $c = $this->candle($request);
        if (in_array($c['status'], ['removed', 'cancelled'], true)) {
            $model = $this->app->model(Candle::class);
            $model->setStatus((int) $c['id'], $c['lit_at'] ? 'active' : 'pending_payment');
            $this->app->audit('vela_restaurada', '#' . $c['id']);
            $this->flash('success', 'Vela restaurada.');
        }
        return $this->redirect('/admin/velas/' . $c['id']);
    }

    public function note(Request $request): Response
    {
        $c = $this->candle($request);
        $this->app->model(Candle::class)->setNote((int) $c['id'], mb_substr($request->input('admin_note'), 0, 255));
        $this->flash('success', 'Observação salva.');
        return $this->redirect('/admin/velas/' . $c['id']);
    }

    public function massToggle(Request $request): Response
    {
        $c = $this->candle($request);
        $this->app->model(Candle::class)->setMassDone((int) $c['id'], $c['mass_done_at'] === null);
        return $this->back('/admin/velas/' . $c['id']);
    }

    /** Excluir de vez (LGPD / pedido do titular): só administradores. */
    public function delete(Request $request): Response
    {
        if (!$this->auth->isAdmin()) {
            throw new HttpException(403);
        }
        $c = $this->candle($request);
        $this->app->model(Candle::class)->delete((int) $c['id']);
        $this->app->audit('vela_excluida', '#' . $c['id']);
        $this->flash('success', 'Vela excluída definitivamente.');
        return $this->redirect('/admin/velas');
    }

    /** CSV das velas (contém e-mails: só administradores; registrado na auditoria). */
    public function export(Request $request): Response
    {
        if (!$this->auth->isAdmin()) {
            throw new HttpException(403);
        }
        $from = $this->dateOr($request->query('de'), date('Y-m-01'));
        $to = $this->dateOr($request->query('ate'), date('Y-m-d'));
        $this->app->audit('velas_exportadas', "{$from} a {$to}");
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Código', 'Criada em', 'Situação', 'Tipo', 'Duração (h)', 'Valor', 'Pago em', 'Acesa até', 'Motivo', 'Nome', 'Por quem', 'Intenção', 'Mostrar nome', 'Mostrar intenção', 'Missa', 'E-mail', 'Orações'], ';');
        foreach ($this->app->model(Candle::class)->exportRows($from, $to) as $r) {
            fputcsv($out, array_map([$this, 'csvSafe'], [
                $r['code'], $r['created_at'], Candle::STATUS[$r['status']] ?? $r['status'], $r['type_name'], $r['hours'],
                cents_input((int) $r['amount']), $r['paid_at'], $r['expires_at'], Candle::CATEGORIES[$r['category']][0] ?? $r['category'],
                $r['name'], $r['for_whom'], $r['intention'], $r['show_name'] ? 'sim' : 'não', $r['show_intention'] ? 'sim' : 'não',
                $r['mass_intention'] ? 'sim' : 'não', $r['email'], $r['prayer_count'],
            ]), ';');
        }
        rewind($out);
        return Response::download((string) stream_get_contents($out), "velas-{$from}-a-{$to}.csv", 'text/csv; charset=UTF-8');
    }

    private function candle(Request $request): array
    {
        return $this->notFoundIfNull($this->db->fetch(
            'SELECT c.*, t.name AS type_name, t.color AS type_color FROM candles c JOIN candle_types t ON t.id = c.type_id WHERE c.id = ?',
            [$this->idParam($request)]
        ));
    }

    private function dateOr(string $value, string $default): string
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return ($d && $d->format('Y-m-d') === $value) ? $value : $default;
    }

    /** Evita "injeção de fórmula" em planilhas (=, +, -, @ no início do texto). */
    private function csvSafe(mixed $v): string
    {
        $s = (string) ($v ?? '');
        return ($s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true)) ? "'" . $s : $s;
    }
}
