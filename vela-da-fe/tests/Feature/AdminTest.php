<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Candle;
use App\Models\User;
use App\Services\Scheduler;

final class AdminTest extends FeatureTestCase
{
    public function testPanelRequiresLogin(): void
    {
        $h = $this->http();
        foreach (['/admin', '/admin/velas', '/admin/configuracoes', '/admin/tipos', '/admin/missas', '/admin/sistema'] as $path) {
            $h->get($path);
            $this->assertContains('Painel da equipe', $h->body, $path);
            $this->assertContains('/admin/entrar', $h->url, $path);
        }
        $h->get('/admin/velas/exportar');
        $this->assertContains('/admin/entrar', $h->url);
    }

    public function testLoginWrongPasswordAndLockout(): void
    {
        $h = $this->http();
        $h->post('/admin/entrar', ['email' => 'admin@teste.local', 'password' => 'errada1'], '/admin/entrar');
        $this->assertContains('incorretos', $h->flashText());
        for ($i = 0; $i < 5; $i++) {
            $h->post('/admin/entrar', ['email' => 'admin@teste.local', 'password' => 'errada1'], '/admin/entrar');
        }
        // bloqueado até para a senha certa
        $h->post('/admin/entrar', ['email' => 'admin@teste.local', 'password' => 'Admin12345'], '/admin/entrar');
        $this->assertContains('Muitas tentativas', $h->flashText());
    }

    public function testAdminLoginAndDashboard(): void
    {
        $a = $this->adminLogin();
        $a->get('/admin');
        $this->assertSame(200, $a->status);
        $this->assertContains('velas acesas agora', $a->body);
        $this->assertContains('Configurações', $a->body);
        $a->post('/admin/sair', [], '/admin');
        $a->get('/admin');
        $this->assertContains('Painel da equipe', $a->body);
    }

    public function testModeratorCannotReachAdminOnlyAreas(): void
    {
        $this->staff('mod3@teste.local', 'moderator');
        $m = $this->adminLogin('mod3@teste.local', 'Senha1234');
        $m->get('/admin');
        $this->assertSame(200, $m->status);
        $this->assertNotContains('/admin/configuracoes', $m->body);
        foreach (['/admin/configuracoes', '/admin/equipe', '/admin/financeiro', '/admin/sistema', '/admin/auditoria', '/admin/tipos', '/admin/duracoes', '/admin/velas/exportar'] as $path) {
            $m->get($path);
            $this->assertSame(403, $m->status, $path);
        }
        // mas pode editar conteúdo comum
        $m->get('/admin/oracoes');
        $this->assertSame(200, $m->status);
        $m->get('/admin/horarios');
        $this->assertSame(200, $m->status);
        $m->get('/admin/missas');
        $this->assertSame(200, $m->status);
    }

    public function testBlockedUserLosesAccess(): void
    {
        $u = $this->staff('block@teste.local', 'moderator');
        $m = $this->adminLogin('block@teste.local', 'Senha1234');
        $m->get('/admin');
        $this->assertSame(200, $m->status);
        self::$app->model(User::class)->setStatus((int) $u['id'], 'blocked');
        $m->get('/admin');
        $this->assertContains('/admin/entrar', $m->url);
        self::$app->model(User::class)->setStatus((int) $u['id'], 'active');
    }

    public function testPostsRequireCsrf(): void
    {
        $a = $this->adminLogin();
        $r = $a->post('/admin/sair', [], null, true, false);
        $this->assertSame(419, $r->status);
    }

    public function testResourceCrudForPrayersAndTypes(): void
    {
        $a = $this->adminLogin();
        $a->post('/admin/oracoes', ['title' => 'Oração de Teste', 'body' => "Linha 1\nLinha 2", 'sort_order' => '99', 'active' => '1'], '/admin/oracoes/novo');
        $this->assertContains('criado', $a->flashText());
        $anon = $this->http();
        $anon->get('/oracoes/oracao-de-teste');
        $this->assertSame(200, $anon->status);
        $this->assertContains('Linha 2', $anon->body);

        $id = (int) self::$app->db->fetchValue("SELECT id FROM prayers WHERE slug = 'oracao-de-teste'");
        $a->post("/admin/oracoes/{$id}", ['title' => 'Oração de Teste 2', 'body' => 'Novo texto', 'sort_order' => '99'], "/admin/oracoes/{$id}/editar"); // active desmarcado
        $anon->get('/oracoes/oracao-de-teste');
        $this->assertSame(404, $anon->status, 'oração desativada ainda aparece');
        $a->post("/admin/oracoes/{$id}/excluir", [], '/admin/oracoes');
        $this->assertSame(0, (int) self::$app->db->fetchValue('SELECT COUNT(*) FROM prayers WHERE id = ?', [$id]));

        // validação
        $a->post('/admin/tipos', ['name' => 'Vela Rosa', 'color' => 'rosa', 'price' => '0', 'sort_order' => '9'], '/admin/tipos/novo');
        $this->assertContains('cor', mb_strtolower($a->flashText()));
        $a->post('/admin/tipos', ['name' => 'Vela Rosa', 'color' => '#ff66aa', 'price' => '2,50', 'sort_order' => '9', 'active' => '1'], '/admin/tipos/novo');
        $row = self::$app->db->fetch("SELECT * FROM candle_types WHERE name = 'Vela Rosa'");
        $this->assertSame(250, (int) $row['price']);
        $anon->get('/acender');
        $this->assertContains('Vela Rosa', $anon->body);
        $this->assertContains('.ct-' . $row['id'] . '{--c:#ff66aa}', $anon->body);

        // tipo já usado em vela não pode ser excluído
        $this->light(['type_id' => $row['id']]);
        $a->post('/admin/tipos/' . $row['id'] . '/excluir', [], '/admin/tipos');
        $this->assertContains('já foi usado', $a->flashText());
        $this->assertSame(1, (int) self::$app->db->fetchValue('SELECT COUNT(*) FROM candle_types WHERE id = ?', [$row['id']]));

        $a->get('/admin/recurso-inexistente');
        $this->assertSame(404, $a->status);
    }

    public function testSettingsAreSavedAndShown(): void
    {
        $a = $this->adminLogin();
        $a->post('/admin/configuracoes', $this->settingsPayload(['church_name' => 'Paróquia Santa Luzia', 'church_phone' => '(11) 4000-0000']), '/admin/configuracoes');
        $this->assertContains('salvas', $a->flashText());
        $h = $this->http();
        $h->get('/igreja');
        $this->assertContains('Paróquia Santa Luzia', $h->body);
        $this->assertContains('(11) 4000-0000', $h->body);

        $a->post('/admin/configuracoes', $this->settingsPayload(['pix_key' => 'inválida!']), '/admin/configuracoes');
        $this->assertContains('Chave Pix inválida', $a->flashText());
        $a->post('/admin/configuracoes', $this->settingsPayload(['church_name' => 'Nossa Paróquia', 'church_phone' => '']), '/admin/configuracoes');
    }

    private function settingsPayload(array $over): array
    {
        $base = [];
        foreach (\App\Models\Setting::SCHEMA as $k => $d) {
            $base[$k] = $d['type'] === 'bool' ? '1' : (string) $d['default'];
        }
        $base['pix_key'] = 'pix@teste.local';
        return $over + $base;
    }

    public function testMassIntentionsListAndPrint(): void
    {
        [, $code] = $this->light(['name' => 'Para Missa', 'intention' => 'Pelos meus avós', 'mass_intention' => '1', 'show_intention' => null]);
        $a = $this->adminLogin();
        $a->get('/admin/missas?de=' . date('Y-m-d') . '&ate=' . date('Y-m-d'));
        $this->assertContains('Pelos meus avós', $a->body);
        $a->get('/admin/missas/imprimir?de=' . date('Y-m-d') . '&ate=' . date('Y-m-d'));
        $this->assertContains('Pelos meus avós', $a->body);
        // a intenção da missa continua privada no site
        $h = $this->http();
        $h->get('/vela/' . $code);
        $this->assertNotContains('Pelos meus avós', $h->body);

        $a->post('/admin/missas/marcar', ['de' => date('Y-m-d'), 'ate' => date('Y-m-d')], '/admin/missas');
        $this->assertTrue($this->candle($code)['mass_done_at'] !== null);
        $a->get('/admin/missas?de=' . date('Y-m-d') . '&ate=' . date('Y-m-d'));
        $this->assertNotContains('Pelos meus avós', $a->body, 'rezadas devem sair da lista pendente');
    }

    public function testCandleAdminActions(): void
    {
        [, $code] = $this->light(['name' => 'Controle']);
        $id = (int) $this->candle($code)['id'];
        $a = $this->adminLogin();
        $before = strtotime($this->candle($code)['expires_at']);
        $a->post("/admin/velas/{$id}/prolongar", ['hours' => '48'], "/admin/velas/{$id}");
        $this->assertTrue(strtotime($this->candle($code)['expires_at']) >= $before + 48 * 3600 - 2);
        $a->post("/admin/velas/{$id}/apagar", [], "/admin/velas/{$id}");
        $this->assertTrue(strtotime($this->candle($code)['expires_at']) <= time());
        $a->post("/admin/velas/{$id}/remover", [], "/admin/velas/{$id}");
        $h = $this->http();
        $h->get('/vela/' . $code);
        $this->assertSame(404, $h->status);
        $a->post("/admin/velas/{$id}/restaurar", [], "/admin/velas/{$id}");
        $this->assertSame('active', $this->candle($code)['status']);
        $a->post("/admin/velas/{$id}/excluir", [], "/admin/velas/{$id}");
        $this->assertSame([], $this->candle($code));
    }

    public function testCsvExportIsProtectedFromFormulaInjection(): void
    {
        $this->light(['name' => '=HYPERLINK("http://x")', 'intention' => '+cmd']);
        $a = $this->adminLogin();
        $a->get('/admin/velas/exportar?de=' . date('Y-m-d') . '&ate=' . date('Y-m-d'));
        $this->assertContains('text/csv', $a->header('content-type'));
        $this->assertContains("'=HYPERLINK", $a->body);
        $this->assertNotContains(";=HYPERLINK", $a->body);
    }

    public function testTeamManagementKeepsAtLeastOneAdmin(): void
    {
        $a = $this->adminLogin();
        $admin = self::$app->model(User::class)->findByEmail('admin@teste.local');
        $a->post('/admin/equipe/' . $admin['id'], ['name' => 'Admin Teste', 'email' => 'admin@teste.local', 'role' => 'moderator', 'status' => 'active'], '/admin/equipe/' . $admin['id'] . '/editar');
        $this->assertContains('único administrador', $a->flashText());
        $this->assertSame('admin', self::$app->model(User::class)->find((int) $admin['id'])['role']);

        $a->post('/admin/equipe', ['name' => 'Nova Moderadora', 'email' => 'nova@teste.local', 'role' => 'moderator', 'password' => 'fraca', 'password_confirmation' => 'fraca'], '/admin/equipe/novo');
        $this->assertSame(null, self::$app->model(User::class)->findByEmail('nova@teste.local'));
        $a->post('/admin/equipe', ['name' => 'Nova Moderadora', 'email' => 'nova@teste.local', 'role' => 'moderator', 'password' => 'Senha1234', 'password_confirmation' => 'Senha1234'], '/admin/equipe/novo');
        $this->assertTrue(self::$app->model(User::class)->findByEmail('nova@teste.local') !== null);
    }

    public function testPasswordChangeEndsOtherSessions(): void
    {
        $this->staff('troca@teste.local', 'moderator', 'Senha1234');
        $one = $this->adminLogin('troca@teste.local', 'Senha1234');
        $two = $this->adminLogin('troca@teste.local', 'Senha1234');
        $one->post('/admin/conta/senha', ['current_password' => 'Senha1234', 'password' => 'NovaSenha99', 'password_confirmation' => 'NovaSenha99'], '/admin/conta');
        $this->assertContains('Senha alterada', $one->flashText());
        $one->get('/admin');
        $this->assertSame(200, $one->status);
        $this->assertContains('Visão geral', $one->body);
        $two->get('/admin');
        $this->assertContains('/admin/entrar', $two->url, 'a outra sessão deveria ter caído');
    }

    public function testSystemPageBackupAndCron(): void
    {
        $a = $this->adminLogin();
        $a->get('/admin/sistema');
        $this->assertSame(200, $a->status);
        $a->post('/admin/sistema/backup', [], '/admin/sistema');
        $this->assertContains('Backup criado', $a->flashText());
        $a->get('/admin/sistema');
        preg_match('#/admin/sistema/backup/(vela-[0-9-]+\.(?:sqlite|sql)\.gz)#', $a->body, $m);
        $this->assertTrue(isset($m[1]), 'backup não listado');
        $a->get('/admin/sistema/backup/' . $m[1]);
        $this->assertSame(200, $a->status);
        $this->assertSame("\x1f\x8b", substr($a->body, 0, 2), 'não é gzip');
        $a->get('/admin/sistema/backup/..%2F..%2F.env');
        $this->assertSame(404, $a->status);
        $a->post('/admin/sistema/rotinas', [], '/admin/sistema');
        $this->assertContains('Rotinas executadas', $a->flashText());
        $a->get('/admin/auditoria');
        $this->assertContains('backup', $a->body);
    }

    public function testSchedulerSendsRemindersExpiredNoticesAndCancelsStalePix(): void
    {
        [, $code] = $this->light(['email' => 'aviso@example.com', 'notify_email' => '1']);
        $db = self::$app->db;
        // vela de 3 dias que apaga em 10 h -> lembrete
        $db->execute('UPDATE candles SET hours = 72, expires_at = ? WHERE code = ?', [date('Y-m-d H:i:s', time() + 36000), $code]);
        $r = (new Scheduler(self::$app))->runLight();
        $this->assertTrue($r['reminders'] >= 1);
        $this->assertTrue((int) $db->fetchValue("SELECT COUNT(*) FROM mail_queue WHERE to_email = 'aviso@example.com' AND subject LIKE '%perto de se apagar%'") === 1);
        (new Scheduler(self::$app))->runLight();
        $this->assertSame(1, (int) $db->fetchValue("SELECT COUNT(*) FROM mail_queue WHERE to_email = 'aviso@example.com' AND subject LIKE '%perto de se apagar%'"), 'lembrete duplicado');

        // apagou -> aviso (uma vez)
        $db->execute('UPDATE candles SET expires_at = ? WHERE code = ?', [date('Y-m-d H:i:s', time() - 60), $code]);
        (new Scheduler(self::$app))->runLight();
        (new Scheduler(self::$app))->runLight();
        $this->assertSame(1, (int) $db->fetchValue("SELECT COUNT(*) FROM mail_queue WHERE to_email = 'aviso@example.com' AND subject LIKE '%se apagou%'"));

        // Pix vencido é cancelado e e-mails antigos são anonimizados
        [$type, $plan] = $this->paidIds();
        [, $paid] = $this->light(['type_id' => $type, 'plan_id' => $plan]);
        $db->execute('UPDATE candles SET created_at = ? WHERE code = ?', [date('Y-m-d H:i:s', time() - 5 * 86400), $paid]);
        $db->execute('UPDATE candles SET expires_at = ? WHERE code = ?', [date('Y-m-d H:i:s', time() - 200 * 86400), $code]);
        (new Scheduler(self::$app))->runDaily();
        $this->assertSame('cancelled', $this->candle($paid)['status']);
        $this->assertSame(null, $this->candle($code)['email'], 'e-mail antigo deveria ser apagado (LGPD)');
    }
}
