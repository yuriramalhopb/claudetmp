<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\PasswordReset;
use App\Models\User;

final class AuthController extends Controller
{
    public const PROFILE_RULES = [
        'name' => 'required|min:3|max:120',
        'nickname' => 'max:60',
        'phone' => 'required|phone',
        'city' => 'required|max:80',
        'birth_date' => 'date',
        'bike_brand' => 'max:60',
        'bike_model' => 'max:80',
        'bike_cc' => 'between:50,2500',
        'bike_year' => 'between:1950,2100',
        'experience' => 'required|in:iniciante,intermediario,avancado,expert',
        'blood_type' => 'in:A+,A-,B+,B-,AB+,AB-,O+,O-',
        'emergency_name' => 'max:120',
        'emergency_phone' => 'phone',
        'instagram' => 'instagram_handle',
        'bio' => 'max:1000',
    ];

    public function registerForm(Request $request): Response
    {
        return $this->view('auth/register', ['title' => 'Cadastro de trilheiro']);
    }

    public function register(Request $request): Response
    {
        $fields = array_merge(User::PROFILE_FIELDS, ['email', 'password', 'password_confirmation', 'lgpd']);
        $data = $request->only($fields);
        $data['show_phone'] = $request->bool('show_phone');

        if ($this->isBot($request)) {
            return $this->redirect('/entrar');
        }
        $rules = self::PROFILE_RULES + [
            'email' => 'required|email',
            'password' => 'required|password|confirmed',
            'lgpd' => 'accepted',
        ];
        if ($fail = $this->validate($data, $rules, '/cadastro')) {
            return $fail;
        }
        $this->throttle('register', 5, 3600);

        $users = $this->app->model(User::class);
        if ($users->emailExists($data['email'])) {
            return $this->failWith(['email' => ['Este e-mail já está cadastrado. Tente entrar ou recuperar a senha.']], $data, '/cadastro');
        }

        $id = $users->create($data, $data['password']);
        $photo = $request->file('photo');
        if ($photo) {
            try {
                $users->updatePhoto($id, $this->app->uploader()->store($photo, 'avatars', 600));
            } catch (\RuntimeException $e) {
                $this->flash('warning', 'Cadastro feito, mas a foto não foi aceita: ' . $e->getMessage());
            }
        }
        $this->app->auth->login($users->find($id));
        $this->app->audit('cadastro', 'Novo trilheiro #' . $id);
        $this->flash('success', 'Cadastro realizado! Agora é só aguardar a aprovação da administração. 🦆');
        return $this->redirect('/minha-conta');
    }

    public function loginForm(Request $request): Response
    {
        return $this->view('auth/login', ['title' => 'Entrar']);
    }

    public function login(Request $request): Response
    {
        $email = $request->input('email');
        $result = $this->auth->attempt($email, (string) ($_POST['password'] ?? ''), $request->ip());
        if (!$result['ok']) {
            $this->app->model(\App\Models\AuditLog::class)->record(null, 'login_falhou', mb_substr($email, 0, 160), $request->ip());
            return $this->failWith(['email' => [$result['error']]], ['email' => $email], '/entrar');
        }
        $this->app->csrf->rotate();
        if (!empty($result['two_factor'])) {
            return $this->redirect('/entrar/2fa');
        }
        $this->flash('success', 'Bem-vindo de volta!');
        $intended = (string) $this->session->pull('intended', '');
        if ($this->auth->isAdmin() && $intended === '') {
            $intended = '/admin';
        }
        return $this->redirect($intended !== '' ? $intended : '/minha-conta');
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout();
        return $this->redirect('/');
    }

    public function forgotForm(Request $request): Response
    {
        return $this->view('auth/forgot', ['title' => 'Recuperar senha']);
    }

    public function forgot(Request $request): Response
    {
        $email = $request->input('email');
        if ($fail = $this->validate(['email' => $email], ['email' => 'required|email'], '/esqueci-senha')) {
            return $fail;
        }
        $this->throttle('forgot', 5, 3600);
        $user = $this->app->model(User::class)->findByEmail($email);
        if ($user && $user['status'] !== 'blocked') {
            $token = $this->app->model(PasswordReset::class)->createToken((int) $user['id']);
            $link = $this->app->config->get('app.url') . '/redefinir-senha?token=' . $token;
            $this->app->notifier()->sendNow(
                $user['email'],
                'Redefinição de senha - ' . $this->app->config->get('app.name'),
                "Olá, {$user['name']}!\n\nPara criar uma nova senha, acesse o link abaixo (válido por 60 minutos):\n{$link}\n\nSe você não pediu, ignore este e-mail."
            );
        }
        // resposta idêntica exista ou não o e-mail (evita enumeração de usuários)
        $this->flash('success', 'Se o e-mail estiver cadastrado, você receberá um link para redefinir a senha.');
        return $this->redirect('/entrar');
    }

    public function resetForm(Request $request): Response
    {
        $token = $request->query('token');
        if ($this->app->model(PasswordReset::class)->findValid($token) === null) {
            $this->flash('error', 'Link inválido ou expirado. Peça um novo.');
            return $this->redirect('/esqueci-senha');
        }
        return $this->view('auth/reset', ['title' => 'Nova senha', 'token' => $token]);
    }

    public function reset(Request $request): Response
    {
        $token = $request->input('token');
        $resets = $this->app->model(PasswordReset::class);
        $row = $resets->findValid($token);
        if ($row === null) {
            $this->flash('error', 'Link inválido ou expirado. Peça um novo.');
            return $this->redirect('/esqueci-senha');
        }
        $data = ['password' => (string) ($_POST['password'] ?? ''), 'password_confirmation' => (string) ($_POST['password_confirmation'] ?? '')];
        if ($fail = $this->validate($data, ['password' => 'required|password|confirmed'], '/redefinir-senha?token=' . $token)) {
            return $fail;
        }
        $this->app->model(User::class)->updatePassword((int) $row['user_id'], $data['password']);
        $resets->markUsed((int) $row['id']);
        $this->app->model(\App\Models\AuditLog::class)->record((int) $row['user_id'], 'senha_redefinida', '', $request->ip());
        $this->flash('success', 'Senha alterada! Entre com a nova senha.');
        return $this->redirect('/entrar');
    }
}
