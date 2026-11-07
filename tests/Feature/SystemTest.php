<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Scheduler;

final class SystemTest extends FeatureTestCase
{
    public function testAvisoDeTrilhaNovaELembreteSemDuplicar(): void
    {
        $this->member('aviso@teste.local');
        $u = $this->member('lembrete@teste.local');
        $id = self::$app->model(\App\Models\Event::class)->create(['title' => 'Trilha Amanhã', 'start_at' => date('Y-m-d', strtotime('+1 day')) . 'T08:00', 'city' => 'X', 'state' => 'SP'], true, 'approved', null, null);
        self::$app->model(\App\Models\Event::class)->confirmRsvp($id, (int) $u['id'], null, '', false);
        $before = (int) self::$app->db->fetchValue("SELECT COUNT(*) FROM mail_queue WHERE subject = 'Trilha nova: Trilha Amanhã'");
        $s = new Scheduler(self::$app);
        $s->announce();
        $s->announce();
        $after = (int) self::$app->db->fetchValue("SELECT COUNT(*) FROM mail_queue WHERE subject = 'Trilha nova: Trilha Amanhã'");
        $this->assertTrue($after > $before && $after === (int) self::$app->db->fetchValue("SELECT COUNT(*) FROM users WHERE status = 'approved' AND notify_email = 1"), 'um e-mail por trilheiro, sem duplicar');
        if ((int) date('G') >= 9) {
            $s->reminders();
            $s->reminders();
            $this->assertSame(1, (int) self::$app->db->fetchValue("SELECT COUNT(*) FROM mail_queue WHERE subject = 'É amanhã: Trilha Amanhã'"));
        }
        $s->emails();
        $this->assertSame(0, (int) self::$app->db->fetchValue('SELECT COUNT(*) FROM mail_queue WHERE sent_at IS NULL'), 'fila processada');
    }

    public function testInscricaoPushValidaEndpoint(): void
    {
        $this->member('push@teste.local');
        $h = $this->login('push@teste.local');
        $token = $h->csrf('/');
        $p256 = rtrim(strtr(base64_encode("\x04" . random_bytes(64)), '+/', '-_'), '=');
        $auth = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
        $h->post('/push/inscrever', ['_csrf' => $token, 'endpoint' => 'https://evil.com/x', 'p256dh' => $p256, 'auth' => $auth]);
        $this->assertSame(422, $h->status, 'endpoint fora dos serviços oficiais');
        $h->post('/push/inscrever', ['_csrf' => $token, 'endpoint' => 'https://fcm.googleapis.com/fcm/send/teste', 'p256dh' => $p256, 'auth' => $auth]);
        $this->assertSame(200, $h->status);
        $this->assertSame(1, (int) self::$app->db->fetchValue("SELECT COUNT(*) FROM push_subscriptions WHERE endpoint = 'https://fcm.googleapis.com/fcm/send/teste'"));
    }

    public function testBackupERestauracao(): void
    {
        $this->member('backup@teste.local');
        $b = self::$app->backup();
        $r = $b->run();
        $this->assertTrue(is_file($r['db']) && filesize($r['db']) > 100, 'dump criado');
        self::$app->db->execute("UPDATE users SET name = 'ALTERADO' WHERE email = 'backup@teste.local'");
        $b->restoreDatabase($r['db']);
        $this->assertSame('Trilheiro backup', self::$app->db->fetchValue("SELECT name FROM users WHERE email = 'backup@teste.local'"));
        $this->assertThrows(fn () => $b->path('../../.env'), 'nome de arquivo inválido recusado');
    }

    public function testPainelSistemaRestritoAoAdmin(): void
    {
        $this->member('naoadmin@teste.local');
        $this->assertSame(403, $this->login('naoadmin@teste.local')->get('/admin/sistema', false)->status);
        $a = $this->admin();
        $a->get('/admin/sistema');
        $this->assertContains('Backups', $a->body);
        $a->post('/admin/sistema/email-teste', [], '/admin/sistema');
        $this->assertContains('E-mail de teste enviado', $a->flashText());
    }
}
