<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Stats;

/** Financeiro: arrecadação com velas pagas por Pix (confirmadas pela equipe). Só administradores. */
final class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        [$from, $to] = $this->range($request);
        $rows = (new Stats($this->db))->revenue($from, $to);
        return $this->adminView('report', [
            'title' => 'Financeiro',
            'rows' => $rows,
            'from' => $from, 'to' => $to,
            'total' => array_sum(array_column($rows, 'total')),
            'count' => array_sum(array_column($rows, 'n')),
        ]);
    }

    public function export(Request $request): Response
    {
        [$from, $to] = $this->range($request);
        $this->app->audit('financeiro_exportado', "{$from} a {$to}");
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Data', 'Velas pagas', 'Total (R$)'], ';');
        foreach ((new Stats($this->db))->revenue($from, $to) as $r) {
            fputcsv($out, [$r['d'], $r['n'], cents_input((int) $r['total'])], ';');
        }
        rewind($out);
        return Response::download((string) stream_get_contents($out), "financeiro-{$from}-a-{$to}.csv", 'text/csv; charset=UTF-8');
    }

    /** @return array{0:string,1:string} */
    private function range(Request $request): array
    {
        $valid = static function (string $v, string $default): string {
            $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);
            return ($d && $d->format('Y-m-d') === $v) ? $v : $default;
        };
        return [$valid($request->input('de'), date('Y-m-01')), $valid($request->input('ate'), date('Y-m-d'))];
    }
}
