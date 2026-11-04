<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\InstagramPost;

final class InstagramController extends Controller
{
    private const PER_PAGE = 24;

    public function index(Request $request): Response
    {
        $this->app->instagram()->syncIfDue();
        $posts = $this->app->model(InstagramPost::class);
        $total = $posts->countVisible();
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, $request->int('pagina', 1)), $pages);

        $list = $posts->visible(self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        return $this->view('instagram/index', [
            'title' => 'Instagram',
            'posts' => $list,
            'instagramEmbed' => in_array(null, array_column($list, 'local_file'), true),
            'page' => $page,
            'pages' => $pages,
        ]);
    }
}
