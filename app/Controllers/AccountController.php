<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Event;
use App\Models\Photo;
use App\Models\User;

final class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->auth->user();
        return $this->view('account/index', [
            'title' => 'Minha conta',
            'user' => $user,
            'agenda' => $this->app->model(Event::class)->userAgenda((int) $user['id']),
        ]);
    }

    public function edit(Request $request): Response
    {
        return $this->view('account/edit', ['title' => 'Editar perfil', 'user' => $this->auth->user()]);
    }

    public function update(Request $request): Response
    {
        $data = $request->only(User::PROFILE_FIELDS);
        $data['show_phone'] = $request->bool('show_phone');
        $data['notify_email'] = $request->bool('notify_email');
        $data['notify_push'] = $request->bool('notify_push');
        if ($fail = $this->validate($data, AuthController::PROFILE_RULES, '/minha-conta/editar')) {
            return $fail;
        }
        $this->app->model(User::class)->updateProfile((int) $this->auth->id(), $data);
        $this->flash('success', 'Perfil atualizado.');
        return $this->redirect('/minha-conta');
    }

    public function photo(Request $request): Response
    {
        $file = $request->file('photo');
        if (!$file) {
            $this->flash('error', 'Selecione uma imagem.');
            return $this->redirect('/minha-conta/editar');
        }
        $this->throttle('avatar', 20, 3600);
        try {
            $name = $this->app->uploader()->store($file, 'avatars', 600);
        } catch (\RuntimeException $e) {
            $this->flash('error', $e->getMessage());
            return $this->redirect('/minha-conta/editar');
        }
        $user = $this->auth->user();
        $this->app->uploader()->delete('avatars', $user['photo']);
        $this->app->model(User::class)->updatePhoto((int) $user['id'], $name);
        $this->flash('success', 'Foto atualizada.');
        return $this->redirect('/minha-conta/editar');
    }

    public function password(Request $request): Response
    {
        $user = $this->auth->user();
        $data = [
            'current_password' => (string) ($_POST['current_password'] ?? ''),
            'password' => (string) ($_POST['password'] ?? ''),
            'password_confirmation' => (string) ($_POST['password_confirmation'] ?? ''),
        ];
        if ($fail = $this->validate($data, ['current_password' => 'required', 'password' => 'required|password|confirmed'], '/minha-conta/editar')) {
            return $fail;
        }
        if (!$this->app->limiter->attempt('pwchange:' . $user['id'], 5, 900)) {
            $this->flash('error', 'Muitas tentativas. Aguarde alguns minutos.');
            return $this->redirect('/minha-conta/editar');
        }
        if (!password_verify($data['current_password'], $user['password_hash'])) {
            return $this->failWith(['current_password' => ['Senha atual incorreta.']], [], '/minha-conta/editar');
        }
        $this->app->model(User::class)->updatePassword((int) $user['id'], $data['password']);
        $this->auth->refresh(); // mantém esta sessão, derruba as outras
        $this->session->regenerate();
        $this->app->audit('senha_alterada');
        $this->flash('success', 'Senha alterada. Outras sessões abertas foram encerradas.');
        return $this->redirect('/minha-conta');
    }

    /** LGPD: o trilheiro pode baixar os próprios dados. */
    public function export(Request $request): Response
    {
        $user = $this->auth->user();
        unset($user['password_hash'], $user['session_version']);
        $user['presencas'] = $this->app->model(Event::class)->userAgenda((int) $user['id']);
        return Response::download(
            (string) json_encode($user, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'meus-dados-ducks.json',
            'application/json; charset=UTF-8'
        );
    }

    /** LGPD: exclusão da conta e dos arquivos pessoais. */
    public function destroy(Request $request): Response
    {
        $user = $this->auth->user();
        if (!password_verify((string) ($_POST['password'] ?? ''), $user['password_hash'])) {
            $this->flash('error', 'Senha incorreta. A conta não foi excluída.');
            return $this->redirect('/minha-conta/editar');
        }
        $users = $this->app->model(User::class);
        if ($user['role'] === 'admin' && $users->countAdmins() <= 1) {
            $this->flash('error', 'Você é o único administrador. Promova outro admin antes de excluir sua conta.');
            return $this->redirect('/minha-conta/editar');
        }
        $photos = $this->app->model(Photo::class);
        foreach ($photos->filenamesByUser((int) $user['id']) as $file) {
            $this->app->uploader()->delete('photos', $file);
        }
        $photos->deleteByUser((int) $user['id']);
        $this->app->uploader()->delete('avatars', $user['photo']);
        $this->app->audit('conta_excluida', 'Usuário #' . $user['id']);
        $users->delete((int) $user['id']);
        $this->auth->logout();
        return $this->redirect('/');
    }
}
