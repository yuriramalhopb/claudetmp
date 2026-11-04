<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Event;
use App\Models\InstagramPost;
use App\Models\Photo;
use App\Models\User;

final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        $this->app->instagram()->syncIfDue();
        $events = $this->app->model(Event::class);
        $users = $this->app->model(User::class);

        $posts = $this->app->model(InstagramPost::class)->visible(6);

        return $this->view('home/index', [
            'title' => 'Início',
            'events' => $events->upcoming('', '', '', 4),
            'photos' => $this->app->model(Photo::class)->latest(8),
            'posts' => $posts,
            'instagramEmbed' => in_array(null, array_column($posts, 'local_file'), true),
            'stats' => [
                'members' => $users->count("status = 'approved'"),
                'events' => $events->count("status = 'approved' AND is_own = 1"),
                'photos' => $this->app->model(Photo::class)->count("status = 'approved'"),
            ],
            'newMembers' => $users->latestApproved(8),
        ]);
    }

    public function about(Request $request): Response
    {
        return $this->view('pages/about', ['title' => 'Sobre a turma']);
    }

    public function install(Request $request): Response
    {
        return $this->view('pages/install', ['title' => 'Instalar o app']);
    }

    public function privacy(Request $request): Response
    {
        return $this->view('pages/privacy', ['title' => 'Política de privacidade']);
    }
}
