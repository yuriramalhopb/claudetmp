<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Pix;

final class EventsTest extends FeatureTestCase
{
    public function testAdminCadastraTrilhaComValidacoes(): void
    {
        $a = $this->admin();
        $d = date('Y-m-d', strtotime('+20 days'));
        $base = ['title' => 'Trilha do Admin', 'start_at' => $d . 'T07:00', 'city' => 'Socorro', 'state' => 'SP', 'is_own' => '1', 'requires_waiver' => '1'];
        foreach ([['state' => 'XX'], ['instagram_url' => 'https://evil.com/p/1'], ['external_link' => 'javascript:alert(1)'],
            ['end_at' => date('Y-m-d', strtotime('+19 days')) . 'T07:00'], ['pix_amount' => 'dez reais'], ['capacity' => '0']] as $bad) {
            $a->post('/admin/eventos/novo', $bad + $base);
            $this->assertSame('error', $a->flashes()[0][0] ?? '', json_encode($bad));
        }
        $a->post('/admin/eventos/novo', $base + ['pix_amount' => '75,00', 'capacity' => '30', 'instagram_url' => 'https://www.instagram.com/p/ABC123/', 'cover' => $this->jpeg('banner.jpg', 1080, 1350)]);
        $this->assertContains('Evento criado', $a->flashText());
        $ev = self::$app->db->fetch("SELECT * FROM events WHERE title = 'Trilha do Admin'");
        $this->assertSame('75.00', (string) $ev['pix_amount']);
        $this->assertTrue((bool) preg_match('/^[a-f0-9]{32}\.jpg$/', (string) $ev['cover']), 'banner salvo');
        $h = $this->http()->get('/trilhas-do-ano/' . date('Y', strtotime($d)) . '?estado=SP');
        $this->assertContains('Trilha do Admin', $h->body);
        $h->get('/trilhas-do-ano/' . date('Y', strtotime($d)) . '?estado=MG');
        $this->assertNotContains('Trilha do Admin', $h->body, 'filtro por estado');
        $h->get('/agenda/' . $ev['id'] . '/calendario.ics');
        $this->assertContains('BEGIN:VEVENT', $h->body);
    }

    public function testPresencaExigeTermoERespeitaVagas(): void
    {
        $id = $this->createEvent(['title' => 'Trilha Vagas', 'capacity' => '1']);
        $this->member('vaga1@teste.local');
        $this->member('vaga2@teste.local');
        $this->member('pendente@teste.local', 'pending');
        $p = $this->login('pendente@teste.local');
        $p->post('/agenda/' . $id . '/presenca', ['waiver' => '1'], '/minha-conta');
        $this->assertContains('aguardando aprovação', $p->flashText());

        $u1 = $this->login('vaga1@teste.local');
        $u1->post('/agenda/' . $id . '/presenca', [], '/agenda/' . $id);
        $this->assertContains('termo', $u1->flashText(), 'sem aceitar o termo não confirma');
        $u1->post('/agenda/' . $id . '/presenca', ['waiver' => '1'], '/agenda/' . $id);
        $this->assertContains('Presença confirmada', $u1->flashText());
        $r = self::$app->db->fetch('SELECT * FROM event_rsvps WHERE event_id = ?', [$id]);
        $this->assertTrue($r['waiver_accepted_at'] !== null && strlen((string) $r['waiver_hash']) === 64, 'aceite registrado com versão do termo');

        $u2 = $this->login('vaga2@teste.local');
        $u2->get('/agenda/' . $id);
        $this->assertContains('Vagas esgotadas', $u2->body);
        $u2->post('/agenda/' . $id . '/presenca', ['waiver' => '1', '_csrf' => $u2->csrf('/minha-conta')]);
        $this->assertContains('esgotadas', $u2->flashText(), 'servidor também bloqueia');
    }

    public function testPixComprovantePrivadoEFicha(): void
    {
        $id = $this->createEvent(['title' => 'Trilha Paga', 'pix_amount' => '80,50']);
        $u = $this->member('pagante@teste.local', 'approved', 'Senha1234', ['blood_type' => 'O-', 'emergency_name' => 'Mãe', 'emergency_phone' => '11977776666']);
        $h = $this->login('pagante@teste.local');
        $h->post('/agenda/' . $id . '/presenca', ['waiver' => '1'], '/agenda/' . $id);
        $this->assertTrue((bool) preg_match('#id="pix-code"[^>]*>([^<]+)</textarea>#', $h->body, $m), 'Pix exibido');
        $pix = html_entity_decode($m[1]);
        $this->assertContains('540580.50', $pix);
        $this->assertSame(Pix::crc16(substr($pix, 0, -4)), substr($pix, -4), 'CRC do Pix válido');
        $h->post('/agenda/' . $id . '/pagamento', ['receipt' => $this->jpeg('comprovante.jpg')], '/agenda/' . $id);
        $this->assertContains('conferir', $h->flashText());
        $rec = (string) self::$app->db->fetchValue('SELECT payment_receipt FROM event_rsvps WHERE event_id = ? AND user_id = ?', [$id, (int) $u['id']]);
        $this->assertTrue(is_file(BASE_PATH . '/storage/private/receipts/' . $rec), 'comprovante fora da pasta pública');
        $hash = substr($rec, 0, 32);
        $this->assertSame(403, $h->get('/admin/comprovantes/' . $hash, false)->status, 'trilheiro não vê comprovantes');
        $this->assertSame(404, $this->http()->get('/uploads/receipts/' . $rec)->status);

        $a = $this->admin();
        $a->get('/admin/eventos/' . $id . '/ficha');
        $this->assertContains('O-', $a->body);
        $this->assertContains('11977776666', $a->body);
        $this->assertContains('Pagamento informado', $a->body);
        $this->assertSame('image/jpeg', $a->get('/admin/comprovantes/' . $hash)->header('content-type'));
        $a->post('/admin/eventos/' . $id . '/pagamento/' . $u['id'], ['status' => 'paid'], '/admin/eventos/' . $id . '/ficha');
        $this->assertContains('R$ 80,50', $a->body, 'total arrecadado');
        $a->post('/admin/eventos/' . $id . '/presenca/' . $u['id'], ['attended' => '1'], '/admin/eventos/' . $id . '/ficha');
        $this->assertSame('1', (string) self::$app->db->fetchValue('SELECT attended FROM event_rsvps WHERE event_id = ? AND user_id = ?', [$id, (int) $u['id']]));
        $this->assertContains('O-', $a->get('/admin/eventos/' . $id . '/ficha.csv')->body);
        $this->assertSame(403, $h->get('/admin/eventos/' . $id . '/ficha', false)->status, 'trilheiro não vê a ficha');
        $h->post('/agenda/' . $id . '/presenca', [], '/agenda/' . $id);
        $this->assertContains('reembolso', $h->flashText(), 'não cancela depois de pago');
        $this->assertSame(1, (int) self::$app->db->fetchValue("SELECT COUNT(*) FROM mail_queue WHERE subject LIKE 'Pagamento confirmado%'"));
    }

    public function testGpxMapaEDownloadRestrito(): void
    {
        $id = $this->createEvent(['title' => 'Trilha GPX']);
        $pts = '';
        for ($i = 0; $i < 200; $i++) {
            $pts .= sprintf('<trkpt lat="%.6f" lon="%.6f"><ele>%d</ele></trkpt>', -23.1 - $i * 0.0005, -46.5 - $i * 0.0003, 700 + $i);
        }
        $gpx = '<?xml version="1.0"?><gpx version="1.1" creator="Strava" xmlns="http://www.topografix.com/GPX/1/1"><trk><name>Teste</name><trkseg>' . $pts . '</trkseg></trk></gpx>';
        $a = $this->admin();
        $a->get('/admin/eventos/' . $id . '/editar');
        $a->post('/admin/eventos/' . $id . '/editar', ['title' => 'Trilha GPX', 'start_at' => date('Y-m-d', strtotime('+10 days')) . 'T08:00', 'city' => 'Atibaia', 'state' => 'SP',
            'gpx' => $this->file($gpx, 'trilha.gpx', 'application/gpx+xml')], '/admin/eventos/' . $id . '/editar');
        $this->assertContains('Percurso importado', $a->flashText());
        $h = $this->http()->get('/agenda/' . $id);
        $this->assertContains('data-track="[[', $h->body);
        $this->assertContains('ele-profile', $h->body);
        $this->assertSame(302, $h->get('/agenda/' . $id . '/percurso.gpx', false)->status, 'visitante não baixa');
        $this->member('gpx@teste.local');
        $m = $this->login('gpx@teste.local')->get('/agenda/' . $id . '/percurso.gpx');
        $this->assertContains('<trkpt', $m->body);
        $this->assertNotContains('Strava', $m->body, 'arquivo regerado');
        $xxe = '<?xml version="1.0"?><!DOCTYPE gpx [<!ENTITY x SYSTEM "file:///etc/passwd">]><gpx><trk><name>&x;</name><trkseg><trkpt lat="1" lon="1"/><trkpt lat="2" lon="2"/></trkseg></trk></gpx>';
        $a->post('/admin/eventos/' . $id . '/editar', ['title' => 'Trilha GPX', 'start_at' => date('Y-m-d', strtotime('+10 days')) . 'T08:00', 'city' => 'Atibaia', 'state' => 'SP',
            'gpx' => $this->file($xxe, 'x.gpx', 'application/gpx+xml')], '/admin/eventos/' . $id . '/editar');
        $this->assertContains('GPX não importado', $a->flashText());
    }

    public function testRankingContaTrilhasRealizadas(): void
    {
        $u = $this->member('ranking@teste.local');
        $past = self::$app->model(\App\Models\Event::class)->create(['title' => 'Trilha Antiga', 'start_at' => (date('Y') - 0) . '-01-02T08:00', 'city' => 'X', 'state' => 'SP', 'distance_km' => '321', 'level' => 'extremo'], true, 'approved', null, null);
        if (strtotime(date('Y') . '-01-02') > time()) {
            return; // primeiro dia do ano: nada a validar
        }
        self::$app->model(\App\Models\Event::class)->confirmRsvp($past, (int) $u['id'], null, '', false);
        $h = $this->http()->get('/ranking?ano=' . date('Y'));
        $this->assertContains('Trilheiro ranking', $h->body);
        $this->assertContains('321', $h->body);
        $this->assertContains('Sobreviveu ao extremo', $this->http()->get('/trilheiros/' . $u['id'])->body);
    }
}
