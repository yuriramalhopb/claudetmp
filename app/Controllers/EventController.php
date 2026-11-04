<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Event;
use App\Services\Calendar;

final class EventController extends Controller
{
    public const RULES = [
        'title' => 'required|min:3|max:150',
        'description' => 'max:5000',
        'start_at' => 'required|datetime',
        'end_at' => 'datetime',
        'location' => 'max:200',
        'city' => 'required|max:80',
        'organizer' => 'max:120',
        'level' => 'in:leve,moderado,pesado,extremo',
        'distance_km' => 'between:0,5000',
        'price' => 'max:60',
        'external_link' => 'url',
    ];

    public function index(Request $request): Response
    {
        $events = $this->app->model(Event::class);
        $type = in_array($request->query('tipo'), ['ducks', 'outras'], true) ? $request->query('tipo') : '';
        $level = $request->query('nivel');
        $search = mb_substr($request->query('q'), 0, 60);
        $showPast = $request->query('passados') === '1';

        $list = $showPast ? $events->past($type, $level, $search, 60) : $events->upcoming($type, $level, $search, 200);
        $grouped = [];
        foreach ($list as $ev) {
            $grouped[month_year_br($ev['start_at'])][] = $ev;
        }

        return $this->view('events/index', [
            'title' => 'Agenda de trilhas',
            'grouped' => $grouped,
            'type' => $type,
            'level' => $level,
            'search' => $search,
            'showPast' => $showPast,
        ]);
    }

    public function show(Request $request): Response
    {
        $events = $this->app->model(Event::class);
        $event = $events->findApproved($this->idParam($request));
        if ($event === null) {
            throw new HttpException(404);
        }
        $userId = $this->auth->id();
        return $this->view('events/show', [
            'title' => $event['title'],
            'event' => $event,
            'attendees' => $events->attendees((int) $event['id']),
            'going' => $userId ? $events->isGoing((int) $event['id'], $userId) : false,
            'googleLink' => $this->calendar()->googleLink($event),
            'mapsLink' => Calendar::mapsLink($event),
            'shareUrl' => $this->app->config->get('app.url') . '/agenda/' . (int) $event['id'],
        ]);
    }

    public function ics(Request $request): Response
    {
        $event = $this->app->model(Event::class)->findApproved($this->idParam($request));
        if ($event === null) {
            throw new HttpException(404);
        }
        return Response::download($this->calendar()->ics($event), 'trilha-' . (int) $event['id'] . '.ics', 'text/calendar; charset=UTF-8');
    }

    public function rsvp(Request $request): Response
    {
        $events = $this->app->model(Event::class);
        $event = $events->findApproved($this->idParam($request));
        if ($event === null) {
            throw new HttpException(404);
        }
        $going = $events->toggleRsvp((int) $event['id'], (int) $this->auth->id());
        $this->flash('success', $going ? 'Presença confirmada! Bora pro barro! 🏍️' : 'Presença cancelada.');
        return $this->redirect('/agenda/' . (int) $event['id']);
    }

    public function suggestForm(Request $request): Response
    {
        return $this->view('events/suggest', ['title' => 'Sugerir trilha / evento']);
    }

    /** Trilheiros sugerem eventos de outras turmas; ficam pendentes até aprovação. */
    public function suggest(Request $request): Response
    {
        $data = $request->only(Event::FIELDS);
        if ($fail = $this->validate($data, self::RULES, '/agenda/sugerir')) {
            return $fail;
        }
        $this->throttle('event-suggest', 10, 3600);
        $isAdmin = $this->auth->isAdmin();
        $id = $this->app->model(Event::class)->create($data, false, $isAdmin ? 'approved' : 'pending', $this->auth->id(), null);
        $this->flash('success', $isAdmin ? 'Evento publicado.' : 'Obrigado! O evento aparece na agenda após aprovação.');
        return $this->redirect($isAdmin ? '/agenda/' . $id : '/agenda');
    }

    private function calendar(): Calendar
    {
        return new Calendar((string) $this->app->config->get('app.url'), (string) $this->app->config->get('app.name'));
    }
}
