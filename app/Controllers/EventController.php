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
    /** Regras de validação do cadastro de trilha (usadas também no painel admin). */
    public static function rules(): array
    {
        return [
            'title' => 'required|min:3|max:150',
            'description' => 'max:5000',
            'start_at' => 'required|datetime',
            'end_at' => 'datetime',
            'location' => 'max:200',
            'city' => 'required|max:80',
            'state' => 'required|in:' . implode(',', array_keys(Event::STATES)),
            'organizer' => 'max:120',
            'category' => 'in:' . implode(',', array_keys(Event::CATEGORIES)),
            'level' => 'in:' . implode(',', array_keys(Event::LEVELS)),
            'distance_km' => 'between:0,5000',
            'price' => 'max:60',
            'external_link' => 'url',
            'instagram_url' => 'url|regex:#^https://(www\.)?instagram\.com/[A-Za-z0-9._/?=&%-]{1,250}$#',
            'whatsapp' => 'phone',
            'capacity' => 'between:1,100000',
            'includes' => 'max:2000',
            'registration_until' => 'date',
        ];
    }

    /** Valida também a coerência das datas. */
    public static function dateErrors(array $data): array
    {
        $errors = [];
        $start = strtotime(str_replace('T', ' ', $data['start_at'] ?? ''));
        if (($data['end_at'] ?? '') !== '' && $start && strtotime(str_replace('T', ' ', $data['end_at'])) < $start) {
            $errors['end_at'] = ['O término deve ser depois do início.'];
        }
        if (($data['registration_until'] ?? '') !== '' && $start && strtotime($data['registration_until']) > $start) {
            $errors['registration_until'] = ['O prazo de inscrição deve ser até a data do evento.'];
        }
        return $errors;
    }

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

    /** Página "Trilhas do ano": todas as trilhas de um ano, com banner e filtros. */
    public function year(Request $request): Response
    {
        $year = $this->yearParam($request);
        $events = $this->app->model(Event::class);
        $filters = [
            'state' => mb_strtoupper($request->query('estado')),
            'month' => $request->int('mes'),
            'type' => in_array($request->query('tipo'), ['ducks', 'outras'], true) ? $request->query('tipo') : '',
            'category' => $request->query('categoria'),
            'level' => $request->query('nivel'),
            'q' => mb_substr($request->query('q'), 0, 60),
        ];
        $list = $events->byYear($year, $filters);

        $byMonth = array_fill(1, 12, []);
        foreach ($list as $ev) {
            $byMonth[(int) date('n', strtotime($ev['start_at']))][] = $ev;
        }

        return $this->view('events/year', [
            'title' => "Trilhas de {$year}",
            'metaDescription' => "Calendário de trilhas de moto de {$year}: datas, cidades, valores e inscrições.",
            'year' => $year,
            'years' => $events->years(),
            'byMonth' => $byMonth,
            'total' => count($list),
            'cities' => count(array_unique(array_filter(array_map(fn ($e) => $e['city'] . '/' . $e['state'], $list)))),
            'statesCount' => count(array_unique(array_filter(array_column($list, 'state')))),
            'states' => $events->statesInYear($year),
            'filters' => $filters,
        ]);
    }

    /** Calendário .ics com todas as trilhas do ano (assinável no celular). */
    public function yearIcs(Request $request): Response
    {
        $year = $this->yearParam($request);
        $list = $this->app->model(Event::class)->byYear($year);
        return Response::download($this->calendar()->ics($list), "trilhas-{$year}.ics", 'text/calendar; charset=UTF-8');
    }

    private function yearParam(Request $request): int
    {
        $raw = (string) ($request->param('ano') ?? date('Y'));
        if (!preg_match('/^\d{4}$/', $raw) || (int) $raw < 2000 || (int) $raw > 2100) {
            throw new HttpException(404);
        }
        return (int) $raw;
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
            'metaDescription' => excerpt(date_br($event['start_at']) . ' — ' . $event['city'] . ($event['state'] ? '/' . $event['state'] : '') . '. ' . $event['description'], 160),
            'ogImage' => ($b = upload_url('events', $event['cover'])) ? $this->app->config->get('app.url') . $b : null,
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
        return Response::download($this->calendar()->ics([$event]), 'trilha-' . (int) $event['id'] . '.ics', 'text/calendar; charset=UTF-8');
    }

    public function rsvp(Request $request): Response
    {
        $events = $this->app->model(Event::class);
        $event = $events->findApproved($this->idParam($request));
        if ($event === null) {
            throw new HttpException(404);
        }
        if (strtotime($event['end_at'] ?: $event['start_at']) < strtotime('today')) {
            $this->flash('error', 'Este evento já foi realizado.');
            return $this->redirect('/agenda/' . (int) $event['id']);
        }
        $userId = (int) $this->auth->id();
        if (!$events->isGoing((int) $event['id'], $userId) && $event['capacity']
            && count($events->attendees((int) $event['id'])) >= (int) $event['capacity']) {
            $this->flash('error', 'As vagas deste evento estão esgotadas.');
            return $this->redirect('/agenda/' . (int) $event['id']);
        }
        $going = $events->toggleRsvp((int) $event['id'], $userId);
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
        if ($fail = $this->validate($data, self::rules(), '/agenda/sugerir')) {
            return $fail;
        }
        if ($errors = self::dateErrors($data)) {
            return $this->failWith($errors, $data, '/agenda/sugerir');
        }
        $this->throttle('event-suggest', 10, 3600);
        $banner = null;
        if ($file = $request->file('cover')) {
            try {
                $banner = $this->app->uploader()->store($file, 'events', 1600, 600);
            } catch (\RuntimeException $e) {
                return $this->failWith(['cover' => ['Banner: ' . $e->getMessage()]], $data, '/agenda/sugerir');
            }
        }
        $isAdmin = $this->auth->isAdmin();
        $id = $this->app->model(Event::class)->create($data, false, $isAdmin ? 'approved' : 'pending', $this->auth->id(), $banner);
        $this->flash('success', $isAdmin ? 'Evento publicado.' : 'Obrigado! O evento aparece na agenda após aprovação.');
        return $this->redirect($isAdmin ? '/agenda/' . $id : '/agenda');
    }

    private function calendar(): Calendar
    {
        return new Calendar((string) $this->app->config->get('app.url'), (string) $this->app->config->get('app.name'));
    }
}
