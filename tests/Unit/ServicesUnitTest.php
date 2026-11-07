<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Stats;
use App\Services\Calendar;
use App\Services\Gpx;
use App\Services\Pix;
use App\Services\WebPush;
use Tests\TestCase;

final class ServicesUnitTest extends TestCase
{
    public function testPixCrcExemploDoBancoCentral(): void
    {
        $ex = '00020126580014br.gov.bcb.pix0136123e4567-e12b-12d1-a456-4266554400005204000053039865802BR5913Fulano de Tal6008BRASILIA62070503***6304';
        $this->assertSame('1D3D', Pix::crc16($ex));
    }

    public function testPixPayloadComValorEIdentificador(): void
    {
        $p = (new Pix('pix@ducks.com.br', 'Ducks Offroad Ação', 'São Paulo'))->payload(80.5, 'DUCKS-E1-U2', 'Trilha');
        $this->assertContains('0014br.gov.bcb.pix0116pix@ducks.com.br', $p);
        $this->assertContains('540580.50', $p);
        $this->assertContains('5918DUCKS OFFROAD ACAO', $p, 'nome sem acento');
        $this->assertContains('6009SAO PAULO', $p);
        $this->assertContains('0509DUCKSE1U2', $p, 'txid só alfanumérico');
        $this->assertSame(Pix::crc16(substr($p, 0, -4)), substr($p, -4));
        $this->assertThrows(fn () => (new Pix('chave-invalida', 'X', 'Y'))->payload(10, 'a'));
        $this->assertThrows(fn () => (new Pix('pix@ducks.com.br', 'X', 'Y'))->payload(0, 'a'));
    }

    public function testWebPushCriptografiaRfc8291(): void
    {
        $keys = WebPush::generateVapidKeys();
        $wp = new WebPush($keys['private_b64'], 'mailto:teste@teste.local');
        // "navegador" de teste
        $ua = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $d = openssl_pkey_get_details($ua)['ec'];
        $uaPub = "\x04" . str_pad($d['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['y'], 32, "\0", STR_PAD_LEFT);
        $auth = random_bytes(16);
        $body = $wp->encrypt('{"title":"Trilha nova"}', $uaPub, $auth);
        // decifra como o navegador faria
        $salt = substr($body, 0, 16);
        $this->assertSame(4096, unpack('N', substr($body, 16, 4))[1]);
        $asPub = substr($body, 21, 65);
        $cipher = substr($body, 86);
        $asPem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode(hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $asPub), 64, "\n") . "-----END PUBLIC KEY-----\n";
        $ecdh = openssl_pkey_derive(openssl_pkey_get_public($asPem), $ua, 32);
        $ikm = hash_hkdf('sha256', $ecdh, 32, "WebPush: info\0" . $uaPub . $asPub, $auth);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
        $plain = openssl_decrypt(substr($cipher, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, substr($cipher, -16));
        $this->assertSame("{\"title\":\"Trilha nova\"}\x02", $plain);
        $this->assertSame(65, strlen(WebPush::b64uDecode($wp->publicKey())));
    }

    public function testWebPushSoServicosOficiais(): void
    {
        $this->assertTrue(WebPush::isAllowedEndpoint('https://fcm.googleapis.com/fcm/send/abc'));
        $this->assertTrue(WebPush::isAllowedEndpoint('https://web.push.apple.com/abc'));
        $this->assertTrue(WebPush::isAllowedEndpoint('https://updates.push.services.mozilla.com/wpush/v2/abc'));
        $this->assertFalse(WebPush::isAllowedEndpoint('http://fcm.googleapis.com/x'));
        $this->assertFalse(WebPush::isAllowedEndpoint('https://fcm.googleapis.com.evil.com/x'));
        $this->assertFalse(WebPush::isAllowedEndpoint('https://127.0.0.1/x'));
        $this->assertFalse(WebPush::isAllowedEndpoint('https://evil.com/fcm.googleapis.com'));
    }

    public function testGpxDistanciaSubidaESeguranca(): void
    {
        $dir = sys_get_temp_dir();
        $pts = '';
        for ($i = 0; $i <= 100; $i++) {
            $pts .= sprintf('<trkpt lat="%.6f" lon="-46.5"><ele>%d</ele></trkpt>', -23.0 - $i * 0.001, 800 + ($i < 50 ? $i * 2 : (100 - $i) * 2));
        }
        file_put_contents("{$dir}/t.gpx", '<?xml version="1.0"?><gpx version="1.1" xmlns="http://www.topografix.com/GPX/1/1"><trk><name>Teste</name><trkseg>' . $pts . '</trkseg></trk></gpx>');
        $r = (new Gpx())->parse("{$dir}/t.gpx");
        $this->assertEqualsFloat(11.12, $r['km'], 0.05, '0,1° de latitude ≈ 11,12 km');
        $this->assertEqualsFloat(100, $r['gain'], 6, 'subida acumulada');
        $this->assertSame('Teste', $r['name']);
        $clean = Gpx::build($r['points'], '<script>');
        $this->assertContains('&lt;script&gt;', $clean, 'GPX regerado escapa o nome');
        file_put_contents("{$dir}/x.gpx", '<?xml version="1.0"?><!DOCTYPE gpx [<!ENTITY x SYSTEM "file:///etc/passwd">]><gpx><trk><name>&x;</name><trkseg><trkpt lat="1" lon="1"/><trkpt lat="2" lon="2"/></trkseg></trk></gpx>');
        $this->assertThrows(fn () => (new Gpx())->parse("{$dir}/x.gpx"), 'XXE recusado');
        file_put_contents("{$dir}/x.gpx", 'não sou gpx');
        $this->assertThrows(fn () => (new Gpx())->parse("{$dir}/x.gpx"));
        $this->assertTrue(count(Gpx::simplify($r['points'], 20)) <= 20);
        @unlink("{$dir}/t.gpx");
        @unlink("{$dir}/x.gpx");
    }

    public function testCalendarioIcsEscapaEDobraLinhas(): void
    {
        $cal = new Calendar('https://ducks.test', 'Ducks; Offroad');
        $ics = $cal->ics([['id' => 5, 'title' => 'Trilha, com; vírgula ' . str_repeat('á', 60), 'start_at' => '2026-12-12 08:00:00', 'end_at' => null,
            'description' => "linha1\nlinha2", 'location' => 'Posto', 'city' => 'Atibaia', 'state' => 'SP', 'price' => 'R$ 10', 'instagram_url' => null]]);
        $this->assertContains("BEGIN:VCALENDAR\r\n", $ics);
        $this->assertContains('SUMMARY:Trilha\, com\; vírgula', $ics);
        $this->assertContains('linha1\nlinha2', $ics);
        foreach (explode("\r\n", $ics) as $line) {
            $this->assertTrue(strlen($line) <= 75, 'linha com no máximo 75 bytes');
            $this->assertTrue(mb_check_encoding($line, 'UTF-8'), 'não corta caractere UTF-8');
        }
    }

    public function testSelosDoRanking(): void
    {
        $b = Stats::badges(['trails' => 10, 'km' => 1200, 'extreme' => 1, 'photos' => 0, 'first' => null], date('Y-m-d', strtotime('-2 years')));
        $names = array_column($b, 'name');
        foreach (['Primeira trilha', 'Pegou o jeito', 'Dez trilhas', '500 km', '1.000 km', 'Sobreviveu ao extremo', 'Veterano'] as $n) {
            $this->assertTrue(in_array($n, $names, true), "selo {$n}");
        }
        $this->assertSame([], Stats::badges(['trails' => 0, 'km' => 0, 'extreme' => 0, 'photos' => 0, 'first' => null], date('Y-m-d')));
    }
}
