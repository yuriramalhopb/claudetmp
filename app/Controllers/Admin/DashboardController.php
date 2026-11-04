<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\InstagramPost;
use App\Models\Message;
use App\Models\Photo;
use App\Models\User;

final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $users = $this->app->model(User::class);
        $events = $this->app->model(Event::class);
        return $this->adminView('dashboard', [
            'title' => 'Painel',
            'stats' => [
                ['label' => 'Trilheiros aprovados', 'value' => $users->count("status = 'approved'"), 'link' => '/admin/trilheiros?status=approved'],
                ['label' => 'Cadastros pendentes', 'value' => $users->count("status = 'pending'"), 'link' => '/admin/trilheiros?status=pending', 'alert' => true],
                ['label' => 'Eventos pendentes', 'value' => $events->count("status = 'pending'"), 'link' => '/admin/eventos?status=pending', 'alert' => true],
                ['label' => 'Fotos pendentes', 'value' => $this->app->model(Photo::class)->count("status = 'pending'"), 'link' => '/admin/galeria', 'alert' => true],
                ['label' => 'Mensagens não lidas', 'value' => $this->app->model(Message::class)->count('is_read = 0'), 'link' => '/admin/mensagens', 'alert' => true],
                ['label' => 'Posts do Instagram', 'value' => $this->app->model(InstagramPost::class)->count('hidden = 0'), 'link' => '/admin/instagram'],
            ],
            'pendingUsers' => $users->adminList('', 'pending', 5, 0),
            'nextEvents' => $events->upcoming('', '', '', 5),
            'igLastSync' => $this->app->instagram()->lastSync(),
            'igConfigured' => $this->app->instagram()->isConfigured(),
        ]);
    }

    public function audit(Request $request): Response
    {
        return $this->adminView('audit', [
            'title' => 'Auditoria',
            'logs' => $this->app->model(AuditLog::class)->latest(300),
        ]);
    }
}
