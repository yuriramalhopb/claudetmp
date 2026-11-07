<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Totp;

final class AuthTest extends FeatureTestCase
{
    public function testPostSemCsrfERecusado(): void
    {
        $h = $this->http()->post('/contato', ['name' => 'x'], null, true, false);
        $this->assertSame(419, $h->status);
        $h->post('/contato', ['name' => 'x', '_csrf' => str_repeat('a', 64)]);
        $this->assertSame(419, $h->status, 'token falso');
    }

    public function testCadastroFicaPendenteEEscapaHtml(): void
    {
        $h = $this->http();
        $h->post('/cadastro', ['email' => 'novo@teste.local', 'password' => 'Senha1234', 'password_confirmation' => 'Senha1234',
            'name' => '<img src=x onerror=alert(1)> Novo', 'phone' => '11988887777', 'city' => 'Atibaia', 'experience' => 'iniciante', 'lgpd' => '1'],
            '/cadastro');
        $this->assertContains('aguardando aprovação', $h->body);
        $this->assertNotContains('<img src=x', $h->body, 'HTML do usuário escapado');
        $h->get('/admin', false);
        $this->assertSame(403, $h->status, 'trilheiro não acessa o painel');
        $u = self::$app->db->fetch("SELECT * FROM users WHERE email = 'novo@teste.local'");
        $this->assertSame('pending', $u['status']);
        $this->assertTrue(str_starts_with($u['password_hash'], '$argon2id$') || str_starts_with($u['password_hash'], '$2y$'), 'senha com hash forte');
    }

    public function testCadastroRecusaSenhaFracaEmailDuplicadoESemLgpd(): void
    {
        $this->member('dup@teste.local');
        $base = ['password' => 'Senha1234', 'password_confirmation' => 'Senha1234', 'name' => 'Fulano', 'phone' => '11988887777', 'city' => 'X', 'experience' => 'iniciante', 'lgpd' => '1'];
        $h = $this->http()->post('/cadastro', ['email' => 'dup@teste.local'] + $base);
        $this->assertContains('já está cadastrado', $h->flashText());
        $h = $this->http()->post('/cadastro', ['email' => 'a1@teste.local', 'password' => 'fraca', 'password_confirmation' => 'fraca'] + $base);
        $this->assertContains('senha', strtolower($h->flashText()));
        $h = $this->http()->post('/cadastro', ['email' => 'a2@teste.local', 'lgpd' => ''] + $base);
        $this->assertContains('aceitar', $h->flashText());
    }

    public function testBloqueioPorTentativasDeLogin(): void
    {
        $this->member('alvo@teste.local');
        $h = $this->http();
        for ($i = 0; $i < 6; $i++) {
            $h->post('/entrar', ['email' => 'alvo@teste.local', 'password' => 'errada' . $i]);
        }
        $this->assertContains('Muitas tentativas', $h->flashText());
        $h->post('/entrar', ['email' => 'alvo@teste.local', 'password' => 'Senha1234']);
        $this->assertContains('Muitas tentativas', $h->flashText(), 'nem a senha certa entra durante o bloqueio');
    }

    public function testMensagemGenericaNaoRevelaSeEmailExiste(): void
    {
        $this->member('existe@teste.local');
        $a = $this->http()->post('/entrar', ['email' => 'existe@teste.local', 'password' => 'errada1'])->flashText();
        $b = $this->http()->post('/entrar', ['email' => 'naoexiste@teste.local', 'password' => 'errada1'])->flashText();
        $this->assertSame($a, $b);
    }

    public function testTrocaDeSenhaDerrubaOutrasSessoes(): void
    {
        $this->member('sessao@teste.local');
        $a = $this->login('sessao@teste.local');
        $b = $this->login('sessao@teste.local');
        $a->post('/minha-conta/senha', ['current_password' => 'Senha1234', 'password' => 'NovaSenha99', 'password_confirmation' => 'NovaSenha99'], '/minha-conta/editar');
        $this->assertContains('Senha alterada', $a->flashText());
        $this->assertSame(200, $a->get('/minha-conta', false)->status, 'sessão atual continua');
        $this->assertSame(302, $b->get('/minha-conta', false)->status, 'outra sessão derrubada');
    }

    public function testRecuperacaoDeSenhaTokenDeUsoUnico(): void
    {
        $this->member('esqueci@teste.local');
        $this->http()->post('/esqueci-senha', ['email' => 'esqueci@teste.local']);
        $log = (string) @file_get_contents(BASE_PATH . '/storage/logs/mail-' . date('Y-m-d') . '.log');
        $this->assertTrue((bool) preg_match_all('/token=([a-f0-9]{64})/', $log, $m), 'e-mail registrado');
        $token = end($m[1]);
        $h = $this->http();
        $h->post('/redefinir-senha', ['token' => $token, 'password' => 'Recuperada1', 'password_confirmation' => 'Recuperada1'], '/redefinir-senha?token=' . $token);
        $this->assertContains('Senha alterada', $h->flashText());
        $h->get('/redefinir-senha?token=' . $token);
        $this->assertContains('inválido', $h->flashText(), 'token não pode ser reutilizado');
        $this->assertContains('Bem-vindo', $this->login('esqueci@teste.local', 'Recuperada1')->flashText());
    }

    public function testAdminExige2faECodigosDeRecuperacao(): void
    {
        $h = $this->http();
        $h->post('/entrar', ['email' => 'admin@teste.local', 'password' => 'Admin12345']);
        $admin = self::$app->db->fetch("SELECT * FROM users WHERE email = 'admin@teste.local'");
        if (empty($admin['totp_enabled_at'])) {
            $h->get('/admin');
            $this->assertContains('/minha-conta/2fa', $h->url, 'admin sem 2FA vai para a configuração');
            preg_match('#<code class="secret">([A-Z2-7 ]+)</code>#', $h->body, $mm);
            $secret = str_replace(' ', '', $mm[1]);
            $h->post('/minha-conta/2fa/ativar', ['code' => '000000']);
            $this->assertContains('inválido', $h->flashText());
            $h->post('/minha-conta/2fa/ativar', ['code' => Totp::code($secret, intdiv(time(), 30))]);
        } else {
            $this->admin(); // já configurado por outro teste
            self::$app->db->execute('UPDATE users SET totp_secret = NULL, totp_enabled_at = NULL, totp_recovery = NULL WHERE id = ?', [(int) $admin['id']]);
            $this->testAdminExige2faECodigosDeRecuperacao();
            return;
        }
        preg_match_all('#<li><code>([A-Z2-7]{4}-[A-Z2-7]{4})</code></li>#', $h->body, $codes);
        $this->assertSame(10, count($codes[1]), '10 códigos de recuperação');
        $row = self::$app->db->fetch("SELECT totp_secret, totp_recovery FROM users WHERE email = 'admin@teste.local'");
        $this->assertTrue(str_starts_with($row['totp_secret'], 'v1:'), 'segredo criptografado');
        $this->assertNotContains($codes[1][0], $row['totp_recovery'], 'códigos guardados só como hash');

        // login: senha ok + código errado não entra
        $x = $this->http()->post('/entrar', ['email' => 'admin@teste.local', 'password' => 'Admin12345']);
        $this->assertContains('/entrar/2fa', $x->url);
        $this->assertSame(302, $x->get('/admin', false)->status, 'sem o código não está logado');
        $x->post('/entrar/2fa', ['code' => '123456']);
        $this->assertContains('inválido', $x->flashText());
        // código de recuperação funciona uma vez
        $x->post('/entrar/2fa', ['code' => strtolower($codes[1][0])]);
        $this->assertContains('/admin', $x->url);
        $y = $this->http()->post('/entrar', ['email' => 'admin@teste.local', 'password' => 'Admin12345']);
        $y->post('/entrar/2fa', ['code' => $codes[1][0]]);
        $this->assertContains('/entrar/2fa', $y->url, 'código de recuperação não reutilizável');
    }
}
