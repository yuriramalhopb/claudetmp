<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;

/** Equipe do painel (só administradores). */
final class UserController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->adminView('users/index', ['title' => 'Equipe', 'users' => $this->app->model(User::class)->all()]);
    }

    public function create(Request $request): Response
    {
        return $this->adminView('users/form', ['title' => 'Nova pessoa na equipe', 'u' => null]);
    }

    public function store(Request $request): Response
    {
        $data = $request->only(['name', 'email', 'role', 'password', 'password_confirmation']);
        $rules = ['name' => 'required|min:3|max:120', 'email' => 'required|email', 'role' => 'required|in:admin,moderator', 'password' => 'required|password|confirmed'];
        if ($fail = $this->validate($data, $rules, '/admin/equipe/novo')) {
            return $fail;
        }
        $users = $this->app->model(User::class);
        if ($users->emailExists($data['email'])) {
            return $this->failWith(['email' => ['Este e-mail já está em uso.']], $data, '/admin/equipe/novo');
        }
        $id = $users->create($data['name'], $data['email'], $data['password'], $data['role']);
        $this->app->audit('equipe_criada', "#{$id} {$data['email']} ({$data['role']})");
        $this->flash('success', 'Pessoa adicionada. Passe a ela o e-mail e a senha inicial; peça para trocar em "Minha conta".');
        return $this->redirect('/admin/equipe');
    }

    public function edit(Request $request): Response
    {
        $u = $this->notFoundIfNull($this->app->model(User::class)->find($this->idParam($request)));
        return $this->adminView('users/form', ['title' => 'Editar ' . $u['name'], 'u' => $u]);
    }

    public function update(Request $request): Response
    {
        $users = $this->app->model(User::class);
        $u = $this->notFoundIfNull($users->find($this->idParam($request)));
        $back = '/admin/equipe/' . $u['id'] . '/editar';
        $data = $request->only(['name', 'email', 'role', 'status', 'password', 'password_confirmation']);
        $rules = ['name' => 'required|min:3|max:120', 'email' => 'required|email', 'role' => 'required|in:admin,moderator', 'status' => 'required|in:active,blocked'];
        if ($data['password'] !== '') {
            $rules['password'] = 'password|confirmed';
        }
        if ($fail = $this->validate($data, $rules, $back)) {
            return $fail;
        }
        if ($users->emailExists($data['email'], (int) $u['id'])) {
            return $this->failWith(['email' => ['Este e-mail já está em uso.']], $data, $back);
        }
        // nunca deixar a igreja sem administrador (nem a própria pessoa se trancar para fora)
        $losesAdmin = $u['role'] === 'admin' && ($data['role'] !== 'admin' || $data['status'] !== 'active');
        if ($losesAdmin && ($users->countAdmins() <= 1 || (int) $u['id'] === $this->auth->id())) {
            return $this->failWith(['role' => ['Este é o único administrador (ou você mesmo): mantenha o perfil Administrador e o acesso Ativo.']], $data, $back);
        }
        $users->updateProfile((int) $u['id'], $data['name'], $data['email']);
        $users->setRole((int) $u['id'], $data['role']);
        if ($data['status'] !== $u['status']) {
            $users->setStatus((int) $u['id'], $data['status']);
        }
        if ($data['password'] !== '') {
            $users->updatePassword((int) $u['id'], $data['password']);
        }
        $this->app->audit('equipe_editada', "#{$u['id']} {$data['role']}/{$data['status']}" . ($data['password'] !== '' ? ' (senha redefinida)' : ''));
        $this->flash('success', 'Dados salvos.');
        return $this->redirect('/admin/equipe');
    }
}
