<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Photo;
use App\Models\User;

final class UserController extends Controller
{
    private const PER_PAGE = 30;

    public function index(Request $request): Response
    {
        $users = $this->app->model(User::class);
        $search = mb_substr($request->query('q'), 0, 60);
        $status = $request->query('status');
        $total = $users->adminCount($search, $status);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, $request->int('pagina', 1)), $pages);
        return $this->adminView('users/index', [
            'title' => 'Trilheiros',
            'users' => $users->adminList($search, $status, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'search' => $search,
            'status' => $status,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ]);
    }

    public function show(Request $request): Response
    {
        $user = $this->notFoundIfNull($this->app->model(User::class)->find($this->idParam($request)));
        $this->app->audit('ver_ficha', 'Trilheiro #' . $user['id']);
        return $this->adminView('users/show', ['title' => $user['name'], 'user' => $user]);
    }

    public function status(Request $request): Response
    {
        $id = $this->idParam($request);
        $status = $request->input('status');
        if ($id === $this->auth->id()) {
            $this->flash('error', 'Você não pode alterar a própria situação.');
            return $this->back('/admin/trilheiros');
        }
        $user = $this->notFoundIfNull($this->app->model(User::class)->find($id));
        $this->app->model(User::class)->setStatus($id, $status);
        $this->app->audit('status_trilheiro', "#{$id} {$user['status']} -> {$status}");
        if ($status === 'approved' && $user['status'] !== 'approved') {
            $this->app->notifier()->userApproved($user);
        }
        $this->flash('success', 'Situação de ' . $user['name'] . ' atualizada.');
        return $this->back('/admin/trilheiros');
    }

    public function role(Request $request): Response
    {
        $id = $this->idParam($request);
        $role = $request->input('role');
        $users = $this->app->model(User::class);
        $user = $this->notFoundIfNull($users->find($id));
        if ($id === $this->auth->id()) {
            $this->flash('error', 'Você não pode alterar o próprio perfil de acesso.');
            return $this->back('/admin/trilheiros');
        }
        $users->setRole($id, $role);
        $users->bumpSessionVersion($id); // força novo login com as novas permissões
        $this->app->audit('perfil_trilheiro', "#{$id} {$user['role']} -> {$role}");
        $this->flash('success', 'Perfil de acesso atualizado.');
        return $this->back('/admin/trilheiros');
    }

    /** Trilheiro/admin perdeu o celular: remove o 2FA (ele configura de novo no próximo acesso). */
    public function resetTwoFactor(Request $request): Response
    {
        $id = $this->idParam($request);
        if ($id === $this->auth->id()) {
            $this->flash('error', 'Para alterar o seu 2FA use "Minha conta".');
            return $this->back('/admin/trilheiros');
        }
        $user = $this->notFoundIfNull($this->app->model(User::class)->find($id));
        $this->app->model(User::class)->disableTotp($id);
        $this->app->audit('2fa_resetado_admin', "#{$id} {$user['email']}");
        $this->flash('success', 'Verificação em duas etapas de ' . $user['name'] . ' foi removida.');
        return $this->back('/admin/trilheiros');
    }

    public function destroy(Request $request): Response
    {
        $id = $this->idParam($request);
        if ($id === $this->auth->id()) {
            $this->flash('error', 'Para excluir sua própria conta use "Minha conta".');
            return $this->redirect('/admin/trilheiros');
        }
        $users = $this->app->model(User::class);
        $user = $this->notFoundIfNull($users->find($id));
        $photos = $this->app->model(Photo::class);
        foreach ($photos->filenamesByUser($id) as $file) {
            $this->app->uploader()->delete('photos', $file);
        }
        $photos->deleteByUser($id);
        $this->app->uploader()->delete('avatars', $user['photo']);
        $users->delete($id);
        $this->app->audit('excluir_trilheiro', "#{$id} {$user['email']}");
        $this->flash('success', 'Trilheiro excluído.');
        return $this->redirect('/admin/trilheiros');
    }

    /** Exporta CSV (proteção contra injeção de fórmulas no Excel). */
    public function export(Request $request): Response
    {
        $cols = ['id', 'name', 'nickname', 'email', 'phone', 'city', 'birth_date', 'bike_brand', 'bike_model', 'bike_cc',
            'bike_year', 'experience', 'blood_type', 'emergency_name', 'emergency_phone', 'instagram', 'role', 'status', 'created_at'];
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, "\xEF\xBB\xBF"); // BOM para o Excel abrir acentos corretamente
        fputcsv($fh, $cols, ';');
        foreach ($this->app->model(User::class)->allForExport() as $u) {
            $row = [];
            foreach ($cols as $c) {
                $v = (string) ($u[$c] ?? '');
                if ($v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                    $v = "'" . $v;
                }
                $row[] = $v;
            }
            fputcsv($fh, $row, ';');
        }
        rewind($fh);
        $csv = (string) stream_get_contents($fh);
        fclose($fh);
        $this->app->audit('exportar_trilheiros');
        return Response::download($csv, 'trilheiros-' . date('Y-m-d') . '.csv', 'text/csv; charset=UTF-8');
    }
}
