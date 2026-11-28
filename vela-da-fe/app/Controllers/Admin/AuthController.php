<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;

/** Login da equipe e "minha conta" (nome, e-mail e senha). */
final class AuthController extends Controller
{
    public function loginForm(Request $request): Response
    {
        return $this->view('auth/login', ['title' => 'Entrar no painel', 'metaRobots' => 'noindex'], 200, 'layouts/plain');
    }

    public function login(Request $request): Response
    {
        $email = mb_strtolower($request->input('email'));
        $result = $this->auth->attempt($email, $request->input('password'), $request->ip());
        if (!$result['ok']) {
            $this->flash('error', $result['error']);
            $this->session->set('_old', ['email' => $email]);
            $this->app->audit('login_falhou', $email);
            return $this->redirect('/admin/entrar');
        }
        if (!$this->auth->isStaff()) {
            $this->auth->logout();
            $this->session->flash('error', 'Esta conta não tem acesso ao painel.');
            return $this->redirect('/admin/entrar');
        }
        $this->app->audit('login', $email);
        $to = (string) $this->session->pull('intended', '/admin');
        return $this->redirect(str_starts_with($to, '/admin') ? $to : '/admin');
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout();
        $this->session->start($request->userAgent());
        $this->flash('success', 'Você saiu do painel.');
        return $this->redirect('/admin/entrar');
    }

    public function account(Request $request): Response
    {
        return $this->adminView('account', ['title' => 'Minha conta', 'me' => $this->auth->user()]);
    }

    public function updateAccount(Request $request): Response
    {
        $data = $request->only(['name', 'email']);
        if ($fail = $this->validate($data, ['name' => 'required|min:3|max:120', 'email' => 'required|email'], '/admin/conta')) {
            return $fail;
        }
        $users = $this->app->model(User::class);
        $id = (int) $this->auth->id();
        if ($users->emailExists($data['email'], $id)) {
            return $this->failWith(['email' => ['Este e-mail já está em uso.']], $data, '/admin/conta');
        }
        $users->updateProfile($id, $data['name'], $data['email']);
        $this->app->audit('conta_atualizada');
        $this->flash('success', 'Dados atualizados.');
        return $this->redirect('/admin/conta');
    }

    public function updatePassword(Request $request): Response
    {
        $data = $request->only(['current_password', 'password', 'password_confirmation']);
        if ($fail = $this->validate($data, ['current_password' => 'required', 'password' => 'required|password|confirmed'], '/admin/conta')) {
            return $fail;
        }
        $me = $this->auth->user();
        if (!$this->app->limiter->attempt('chgpw:' . $me['id'], 5, 900) || !password_verify($data['current_password'], $me['password_hash'])) {
            return $this->failWith(['current_password' => ['Senha atual incorreta.']], $data, '/admin/conta');
        }
        $this->app->model(User::class)->updatePassword((int) $me['id'], $data['password']);
        $this->auth->refresh(); // mantém ESTA sessão; as outras caem
        $this->app->audit('senha_alterada');
        $this->flash('success', 'Senha alterada. Os outros acessos desta conta foram encerrados.');
        return $this->redirect('/admin/conta');
    }
}
