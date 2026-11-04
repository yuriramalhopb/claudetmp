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
        $id = $this->app->model(Event::class)->create($data, $request->bool('is_own'), 'approved', $this->auth->id(), $cover);
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
        $events->updateEvent((int) $event['id'], $data, $request->bool('is_own'), $cover);
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
        $events->delete((int) $event['id']);
        $this->app->audit('excluir_evento', '#' . $event['id'] . ' ' . $event['title']);
        $this->flash('success', 'Evento excluído.');
        return $this->redirect('/admin/eventos');
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
