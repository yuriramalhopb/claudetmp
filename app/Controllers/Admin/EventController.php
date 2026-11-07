<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Controllers\EventController as PublicEvents;
use App\Core\Request;
use App\Core\Response;
use App\Models\Event;

final class EventController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->query('status');
        return $this->adminView('events/index', [
            'title' => 'Eventos',
            'events' => $this->app->model(Event::class)->adminList($status),
            'status' => $status,
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->adminView('events/form', ['title' => 'Novo evento', 'event' => null]);
    }

    public function store(Request $request): Response
    {
        $data = $request->only(Event::FIELDS);
        if ($fail = $this->validate($data, PublicEvents::rules(), '/admin/eventos/novo')) {
            return $fail;
        }
        if ($errors = PublicEvents::dateErrors($data)) {
            return $this->failWith($errors, $data, '/admin/eventos/novo');
        }
        $cover = $this->storeCover($request);
        $id = $this->app->model(Event::class)->create($data, $request->bool('is_own'), 'approved', $this->auth->id(), $cover, $request->bool('requires_waiver'));
        $this->handleGpx($request, $id, null);
        $this->app->audit('criar_evento', "#{$id} {$data['title']}");
        $this->flash('success', 'Evento criado.');
        return $this->redirect('/admin/eventos');
    }

    public function edit(Request $request): Response
    {
        $event = $this->notFoundIfNull($this->app->model(Event::class)->find($this->idParam($request)));
        return $this->adminView('events/form', ['title' => 'Editar evento', 'event' => $event]);
    }

    public function update(Request $request): Response
    {
        $events = $this->app->model(Event::class);
        $event = $this->notFoundIfNull($events->find($this->idParam($request)));
        $data = $request->only(Event::FIELDS);
        $back = '/admin/eventos/' . $event['id'] . '/editar';
        if ($fail = $this->validate($data, PublicEvents::rules(), $back)) {
            return $fail;
        }
        if ($errors = PublicEvents::dateErrors($data)) {
            return $this->failWith($errors, $data, $back);
        }
        $cover = $this->storeCover($request);
        if ($cover !== null || $request->bool('remove_cover')) {
            $this->app->uploader()->delete('events', $event['cover']);
            if ($cover === null) {
                $events->removeCover((int) $event['id']);
            }
        }
        $events->updateEvent((int) $event['id'], $data, $request->bool('is_own'), $cover, $request->bool('requires_waiver'));
        $this->handleGpx($request, (int) $event['id'], $event['gpx_file']);
        $this->app->audit('editar_evento', '#' . $event['id']);
        $this->flash('success', 'Evento atualizado.');
        return $this->redirect('/admin/eventos');
    }

    public function approve(Request $request): Response
    {
        $id = $this->idParam($request);
        $this->app->model(Event::class)->approve($id);
        $this->app->audit('aprovar_evento', '#' . $id);
        $this->flash('success', 'Evento aprovado e publicado na agenda.');
        return $this->back('/admin/eventos');
    }

    public function destroy(Request $request): Response
    {
        $events = $this->app->model(Event::class);
        $event = $this->notFoundIfNull($events->find($this->idParam($request)));
        $this->app->uploader()->delete('events', $event['cover']);
        self::deleteGpx($this->app->basePath, $event['gpx_file']);
        foreach ($events->receiptFiles((int) $event['id']) as $receipt) {
            $this->app->privateUploader()->delete('receipts', $receipt);
        }
        $events->delete((int) $event['id']);
        $this->app->audit('excluir_evento', '#' . $event['id'] . ' ' . $event['title']);
        $this->flash('success', 'Evento excluído.');
        return $this->redirect('/admin/eventos');
    }

    // ---------- Ficha de segurança / presença / pagamentos ----------
    public function sheet(Request $request): Response
    {
        $event = $this->notFoundIfNull($this->app->model(Event::class)->find($this->idParam($request)));
        $rows = $this->app->model(Event::class)->safetySheet((int) $event['id']);
        $this->app->audit('ver_ficha_seguranca', 'Evento #' . $event['id']);
        $paid = array_filter($rows, fn ($r) => $r['payment_status'] === 'paid');
        return $this->adminView('events/sheet', [
            'title' => 'Ficha: ' . $event['title'],
            'event' => $event,
            'rows' => $rows,
            'waiverHash' => $this->app->waiver()->hash(),
            'totals' => [
                'confirmed' => count($rows),
                'attended' => count(array_filter($rows, fn ($r) => (string) $r['attended'] === '1')),
                'paid' => count($paid),
                'informed' => count(array_filter($rows, fn ($r) => $r['payment_status'] === 'informed')),
                'amount' => count($paid) * (float) $event['pix_amount'],
            ],
        ]);
    }

    public function sheetCsv(Request $request): Response
    {
        $event = $this->notFoundIfNull($this->app->model(Event::class)->find($this->idParam($request)));
        $rows = $this->app->model(Event::class)->safetySheet((int) $event['id']);
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, "\xEF\xBB\xBF");
        $cols = ['Nome', 'Apelido', 'Telefone', 'Moto', 'Tipo sanguíneo', 'Contato de emergência', 'Telefone de emergência', 'Termo aceito em', 'Pagamento', 'Compareceu'];
        fputcsv($fh, $cols, ';');
        foreach ($rows as $r) {
            $line = [$r['name'], $r['nickname'], $r['phone'], trim($r['bike_brand'] . ' ' . $r['bike_model']), $r['blood_type'], $r['emergency_name'],
                $r['emergency_phone'], $r['waiver_accepted_at'], Event::PAYMENT[$r['payment_status']] ?? '', $r['attended'] === null ? '' : ((int) $r['attended'] ? 'sim' : 'não')];
            fputcsv($fh, array_map(fn ($v) => ($v !== null && $v !== '' && in_array(((string) $v)[0], ['=', '+', '-', '@'], true)) ? "'" . $v : (string) $v, $line), ';');
        }
        rewind($fh);
        $csv = (string) stream_get_contents($fh);
        fclose($fh);
        $this->app->audit('exportar_ficha', 'Evento #' . $event['id']);
        return Response::download($csv, 'ficha-trilha-' . (int) $event['id'] . '.csv', 'text/csv; charset=UTF-8');
    }

    public function attendance(Request $request): Response
    {
        $eventId = $this->idParam($request);
        $userId = (int) $request->param('uid');
        $value = $request->input('attended');
        $this->app->model(Event::class)->setAttended($eventId, $userId, $value === '' ? null : $value === '1');
        return $this->redirect('/admin/eventos/' . $eventId . '/ficha#u' . $userId);
    }

    public function payment(Request $request): Response
    {
        $events = $this->app->model(Event::class);
        $event = $this->notFoundIfNull($events->find($this->idParam($request)));
        $userId = (int) $request->param('uid');
        $status = $request->input('status');
        $rsvp = $events->rsvp((int) $event['id'], $userId);
        if ($rsvp === null || !in_array($status, ['pending', 'paid'], true)) {
            throw new \App\Core\HttpException(400);
        }
        $events->setPayment((int) $event['id'], $userId, $status, $this->auth->id());
        $this->app->audit('pagamento_' . $status, "Evento #{$event['id']} trilheiro #{$userId}");
        if ($status === 'paid' && $rsvp['payment_status'] !== 'paid' && ($user = $this->app->model(\App\Models\User::class)->find($userId))) {
            $this->app->notifier()->paymentConfirmed($user, $event);
        }
        $this->flash('success', $status === 'paid' ? 'Pagamento confirmado.' : 'Pagamento marcado como pendente.');
        return $this->redirect('/admin/eventos/' . $event['id'] . '/ficha#u' . $userId);
    }

    /** Exibe um comprovante (arquivo privado, fora da pasta pública). */
    public function receipt(Request $request): Response
    {
        $file = (string) $request->param('file');
        if (!preg_match('/^[a-f0-9]{32}$/', $file)) {
            throw new \App\Core\HttpException(404);
        }
        $path = $this->app->basePath . '/storage/private/receipts/' . $file . '.jpg';
        if (!is_file($path)) {
            throw new \App\Core\HttpException(404);
        }
        $this->app->audit('ver_comprovante', $file);
        return (new Response((string) file_get_contents($path), 200, ['Content-Type' => 'image/jpeg']))
            ->withHeader('Cache-Control', 'private, no-store')
            ->withHeader('Content-Disposition', 'inline; filename="comprovante.jpg"');
    }

    // ---------- Termo de responsabilidade ----------
    public function waiverForm(Request $request): Response
    {
        return $this->adminView('waiver', ['title' => 'Termo de responsabilidade', 'text' => $this->app->waiver()->text()]);
    }

    public function waiverUpdate(Request $request): Response
    {
        $text = str_replace("\r\n", "\n", $request->input('waiver_text'));
        if ($fail = $this->validate(['waiver_text' => $text], ['waiver_text' => 'required|min:50|max:10000'], '/admin/termo')) {
            return $fail;
        }
        $this->app->waiver()->update($text);
        $this->app->audit('termo_atualizado', substr(hash('sha256', $text), 0, 12));
        $this->flash('success', 'Termo atualizado. Novas confirmações usarão o novo texto; os aceites antigos continuam registrados com a versão anterior.');
        return $this->redirect('/admin/termo');
    }

    /** Processa o upload/remoção do GPX do percurso. */
    private function handleGpx(Request $request, int $eventId, ?string $oldFile): void
    {
        $events = $this->app->model(Event::class);
        $file = $request->file('gpx');
        if ($file === null) {
            if ($request->bool('remove_gpx') && $oldFile) {
                self::deleteGpx($this->app->basePath, $oldFile);
                $events->setTrack($eventId, null, null, null, null);
            }
            return;
        }
        try {
            if ((int) $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
                throw new \RuntimeException('Falha no envio do GPX.');
            }
            $gpx = new \App\Services\Gpx();
            $track = $gpx->parse((string) $file['tmp_name']);
            $name = bin2hex(random_bytes(16)) . '.gpx';
            $dir = $this->app->basePath . '/storage/private/gpx';
            if (!is_dir($dir)) {
                mkdir($dir, 0750, true);
            }
            file_put_contents($dir . '/' . $name, \App\Services\Gpx::build($track['points'], $track['name'] ?: 'Trilha'), LOCK_EX);
            $simple = \App\Services\Gpx::simplify($track['points']);
            $events->setTrack($eventId, $name, (string) json_encode($simple), $track['km'], $track['gain']);
            self::deleteGpx($this->app->basePath, $oldFile);
            $this->flash('success', sprintf('Percurso importado: %s km, %d m de subida acumulada.', number_format($track['km'], 1, ',', '.'), $track['gain']));
        } catch (\Throwable $e) {
            $this->flash('warning', 'GPX não importado: ' . $e->getMessage());
        }
    }

    public static function deleteGpx(string $basePath, ?string $file): void
    {
        if ($file && preg_match('/^[a-f0-9]{32}\.gpx$/', $file) && is_file($basePath . '/storage/private/gpx/' . $file)) {
            @unlink($basePath . '/storage/private/gpx/' . $file);
        }
    }

    private function storeCover(Request $request): ?string
    {
        $file = $request->file('cover');
        if (!$file) {
            return null;
        }
        try {
            return $this->app->uploader()->store($file, 'events', 1600, 600);
        } catch (\RuntimeException $e) {
            $this->flash('warning', 'Capa não enviada: ' . $e->getMessage());
            return null;
        }
    }
}
