<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Event;
use App\Models\User;

/**
 * Diretório público de trilheiros: só exibe membros APROVADOS e apenas dados
 * não sensíveis (nunca e-mail, tipo sanguíneo, contato de emergência, nascimento).
 */
final class MemberController extends Controller
{
    private const PER_PAGE = 24;

    public function index(Request $request): Response
    {
        $users = $this->app->model(User::class);
        $search = mb_substr($request->query('q'), 0, 60);
        $level = $request->query('nivel');
        $total = $users->directoryCount($search, $level);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, $request->int('pagina', 1)), $pages);

        return $this->view('members/index', [
            'title' => 'Trilheiros',
            'members' => $users->directory($search, $level, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'total' => $total,
            'search' => $search,
            'level' => $level,
            'page' => $page,
            'pages' => $pages,
        ]);
    }

    public function show(Request $request): Response
    {
        $member = $this->app->model(User::class)->find($this->idParam($request));
        if ($member === null || $member['status'] !== 'approved') {
            throw new HttpException(404);
        }
        return $this->view('members/show', [
            'title' => display_name($member),
            'member' => $member,
            // telefone só para trilheiros logados e se o membro autorizou
            'showPhone' => (bool) $member['show_phone'] && $this->auth->isApproved(),
            'agenda' => $this->app->model(Event::class)->userAgenda((int) $member['id']),
            'taggedPhotos' => $this->app->model(\App\Models\PhotoSocial::class)->photosOfUser((int) $member['id']),
        ]);
    }
}
