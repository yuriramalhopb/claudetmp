<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Totp;
use App\Models\AuditLog;
use App\Models\User;

/**
 * Verificação em duas etapas (TOTP): configuração, desafio no login e desativação.
 * Obrigatória para administradores; opcional para trilheiros.
 */
final class TwoFactorController extends Controller
{
    // ---------- desafio no login ----------
    public function challengeForm(Request $request): Response
    {
        if ($this->auth->pendingTwoFactorUser() === null) {
            return $this->redirect('/entrar');
        }
        return $this->view('auth/two-factor', ['title' => 'Verificação em duas etapas']);
    }

    public function challenge(Request $request): Response
    {
        $user = $this->auth->pendingTwoFactorUser();
        if ($user === null) {
            $this->flash('error', 'Sessão de login expirou. Entre novamente.');
            return $this->redirect('/entrar');
        }
        $key = '2fa:' . $user['id'];
        if (!$this->app->limiter->attempt($key, 5, 900)) {
            $this->flash('error', 'Muitas tentativas. Aguarde 15 minutos.');
            return $this->redirect('/entrar/2fa');
        }
        $users = $this->app->model(User::class);
        $code = $request->input('code');
        $ok = false;
        if (preg_match('/^\s*\d{3}\s?\d{3}\s*$/', $code)) {
            $step = Totp::verify($this->secretOf($user), $code, $user['totp_last_step'] !== null ? (int) $user['totp_last_step'] : null);
            if ($step !== null) {
                $users->setTotpStep((int) $user['id'], $step);
                $ok = true;
            }
        } elseif ($code !== '' && $users->consumeRecoveryCode((int) $user['id'], $this->recoveryHash($code))) {
            $ok = true;
            $this->flash('warning', 'Você usou um código de recuperação. Restam poucos? Gere novos desativando e ativando o 2FA.');
            $this->app->model(AuditLog::class)->record((int) $user['id'], '2fa_codigo_recuperacao', '', $request->ip());
        }
        if (!$ok) {
            $this->app->model(AuditLog::class)->record((int) $user['id'], '2fa_falhou', '', $request->ip());
            $this->flash('error', 'Código inválido.');
            return $this->redirect('/entrar/2fa');
        }
        $this->app->limiter->clear($key);
        $this->auth->completeTwoFactor($user);
        $this->app->csrf->rotate();
        $this->flash('success', 'Bem-vindo de volta!');
        $intended = (string) $this->session->pull('intended', '');
        return $this->redirect($intended !== '' ? $intended : ($user['role'] === 'admin' ? '/admin' : '/minha-conta'));
    }

    // ---------- configuração ----------
    public function setupForm(Request $request): Response
    {
        $user = $this->auth->user();
        if (!empty($user['totp_enabled_at'])) {
            return $this->view('account/two-factor', ['title' => 'Verificação em duas etapas', 'enabled' => true, 'user' => $user,
                'recoveryLeft' => count(json_decode((string) $user['totp_recovery'], true) ?: [])]);
        }
        $secret = $this->session->get('2fa_setup_secret');
        if (!is_string($secret)) {
            $secret = Totp::generateSecret();
            $this->session->set('2fa_setup_secret', $secret);
        }
        return $this->view('account/two-factor', [
            'title' => 'Verificação em duas etapas',
            'enabled' => false,
            'user' => $user,
            'secret' => $secret,
            'uri' => Totp::uri($secret, $user['email'], (string) $this->app->config->get('app.name')),
            'scripts' => ['vendor/qrcode.js', 'js/qr.js'],
        ]);
    }

    public function enable(Request $request): Response
    {
        $user = $this->auth->user();
        $secret = $this->session->get('2fa_setup_secret');
        if (!is_string($secret) || !empty($user['totp_enabled_at'])) {
            return $this->redirect('/minha-conta/2fa');
        }
        if (!$this->app->limiter->attempt('2fa-setup:' . $user['id'], 10, 900)) {
            $this->flash('error', 'Muitas tentativas. Aguarde alguns minutos.');
            return $this->redirect('/minha-conta/2fa');
        }
        $step = Totp::verify($secret, $request->input('code'));
        if ($step === null) {
            $this->flash('error', 'Código inválido. Confira se o horário do celular está automático e tente de novo.');
            return $this->redirect('/minha-conta/2fa');
        }
        $codes = Totp::recoveryCodes();
        $this->app->model(User::class)->enableTotp(
            (int) $user['id'],
            $this->app->crypto()->encrypt($secret),
            array_map(fn ($c) => $this->recoveryHash($c), $codes),
            $step
        );
        $this->session->forget('2fa_setup_secret');
        $this->app->audit('2fa_ativado');
        // os códigos aparecem UMA vez
        return $this->view('account/recovery-codes', ['title' => 'Códigos de recuperação', 'codes' => $codes, 'scripts' => ['js/qr.js']]);
    }

    public function disable(Request $request): Response
    {
        $user = $this->auth->user();
        if (!password_verify((string) ($_POST['password'] ?? ''), $user['password_hash'])
            || Totp::verify($this->secretOf($user), $request->input('code'), (int) $user['totp_last_step']) === null) {
            $this->flash('error', 'Senha ou código incorretos.');
            return $this->redirect('/minha-conta/2fa');
        }
        $this->app->model(User::class)->disableTotp((int) $user['id']);
        $this->auth->refresh();
        $this->app->audit('2fa_desativado');
        $this->flash('success', 'Verificação em duas etapas desativada.' . ($user['role'] === 'admin' ? ' Atenção: o painel admin exige 2FA.' : ''));
        return $this->redirect('/minha-conta/2fa');
    }

    private function secretOf(array $user): string
    {
        return $this->app->crypto()->decrypt((string) $user['totp_secret']);
    }

    private function recoveryHash(string $code): string
    {
        return hash_hmac('sha256', Totp::normalizeRecovery($code), (string) $this->app->config->get('app.key'));
    }
}
