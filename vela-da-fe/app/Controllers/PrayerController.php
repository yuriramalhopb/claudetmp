<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Prayer;

final class PrayerController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('pages/prayers', [
            'title' => 'Orações',
            'metaDescription' => 'Orações para rezar diante da sua vela.',
            'prayers' => $this->app->model(Prayer::class)->active(),
        ]);
    }

    public function show(Request $request): Response
    {
        $prayer = $this->notFoundIfNull($this->app->model(Prayer::class)->findBySlug((string) $request->param('slug')));
        return $this->view('pages/prayer', [
            'prayer' => $prayer,
            'title' => $prayer['title'],
            'metaDescription' => excerpt($prayer['body'], 150),
        ]);
    }
}
