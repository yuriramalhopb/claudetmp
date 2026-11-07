<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Crypto;
use App\Core\Router;
use App\Core\Totp;
use App\Core\Validator;
use App\Services\ImageUploader;
use Tests\TestCase;

final class SecurityUnitTest extends TestCase
{
    public function testValidatorRegrasPrincipais(): void
    {
        $v = Validator::make(
            ['email' => 'x@', 'password' => 'abc', 'external_link' => 'javascript:alert(1)', 'state' => 'SP', 'bike_cc' => '99999'],
            ['email' => 'required|email', 'password' => 'required|password', 'external_link' => 'url', 'state' => 'in:SP,MG', 'bike_cc' => 'between:50,2500']
        );
        $e = $v->errors();
        $this->assertTrue(isset($e['email'], $e['password'], $e['external_link'], $e['bike_cc']));
        $this->assertFalse(isset($e['state']));
        $this->assertFalse(Validator::make(['a' => ''], ['a' => 'email'])->fails(), 'campo opcional vazio passa');
    }

    public function testTotpVetoresOficiaisRfc6238(): void
    {
        $secret = Totp::base32Encode('12345678901234567890');
        foreach ([59 => '287082', 1111111109 => '081804', 1234567890 => '005924', 2000000000 => '279037'] as $t => $code) {
            $this->assertSame($code, Totp::code($secret, intdiv($t, 30)), "t={$t}");
        }
    }

    public function testTotpJanelaEReplay(): void
    {
        $s = Totp::generateSecret();
        $now = 1_800_000_000;
        $step = intdiv($now, 30);
        $this->assertSame($step, Totp::verify($s, Totp::code($s, $step), null, $now));
        $this->assertSame($step - 1, Totp::verify($s, Totp::code($s, $step - 1), null, $now), 'aceita 30s de atraso');
        $this->assertSame(null, Totp::verify($s, Totp::code($s, $step - 2), null, $now), 'recusa código velho');
        $this->assertSame(null, Totp::verify($s, Totp::code($s, $step), $step, $now), 'recusa reutilização');
        $this->assertSame(null, Totp::verify($s, 'abcdef', null, $now));
        $this->assertSame(10, count(array_unique(Totp::recoveryCodes())));
    }

    public function testCryptoCifraEDetectaAdulteracao(): void
    {
        $c = new Crypto(Crypto::generateKey());
        $enc = $c->encrypt('segredo-2fa');
        $this->assertNotContains('segredo', $enc);
        $this->assertSame('segredo-2fa', $c->decrypt($enc));
        $tampered = substr($enc, 0, -2) . (substr($enc, -2) === 'AA' ? 'BB' : 'AA');
        $this->assertThrows(fn () => $c->decrypt($tampered));
        $this->assertThrows(fn () => (new Crypto(Crypto::generateKey()))->decrypt($enc), 'outra chave não abre');
        $this->assertThrows(fn () => new Crypto('curta'));
    }

    public function testRouterParametrosERestricoes(): void
    {
        $r = new Router();
        $r->get('/agenda/{id}', ['C', 'show']);
        $r->post('/fotos/{id}/marcacoes/{uid}/remover', ['C', 'untag']);
        $r->get('/agenda/{id}/calendario.ics', ['C', 'ics']);
        $this->assertSame('12', $r->match('GET', '/agenda/12')['params']['id']);
        $this->assertSame('7', $r->match('POST', '/fotos/3/marcacoes/7/remover')['params']['uid']);
        $this->assertThrows(fn () => $r->match('GET', '/agenda/abc'), 'id só aceita dígitos');
        $this->assertThrows(fn () => $r->match('GET', '/agenda/1/calendarioXics'), 'ponto é literal');
        $e = $this->assertThrows(fn () => $r->match('GET', '/fotos/3/marcacoes/7/remover'));
        $this->assertSame(405, $e->getCode());
    }

    public function testUploadRecodificaERemoveExifEBloqueiaFalsos(): void
    {
        $dir = sys_get_temp_dir() . '/ducks-up-' . getmypid();
        $up = new ImageUploader($dir, 2 * 1024 * 1024);
        // JPEG com EXIF (simulado por segmento APP1)
        $im = imagecreatetruecolor(3000, 2000);
        $tmp = tempnam(sys_get_temp_dir(), 'x');
        imagejpeg($im, $tmp);
        $jpg = file_get_contents($tmp);
        $exif = "\xFF\xE1" . pack('n', 2 + 6 + 12) . "Exif\0\0" . 'GPS-SECRETO';
        file_put_contents($tmp, substr($jpg, 0, 2) . $exif . substr($jpg, 2));
        $name = $up->storeFromPath($tmp, 'photos', 1920, 480);
        $this->assertTrue((bool) preg_match('/^[a-f0-9]{32}\.jpg$/', $name), 'nome aleatório');
        $saved = file_get_contents("{$dir}/photos/{$name}");
        $this->assertNotContains('GPS-SECRETO', $saved, 'metadados removidos');
        [$w] = getimagesize("{$dir}/photos/{$name}");
        $this->assertSame(1920, $w, 'redimensionada');
        $this->assertTrue(is_file("{$dir}/photos/thumbs/{$name}"), 'miniatura');
        // PHP disfarçado de imagem
        file_put_contents($tmp, "\xFF\xD8\xFF\xE0<?php system(\$_GET['c']); ?>");
        $this->assertThrows(fn () => $up->storeFromPath($tmp, 'photos'));
        file_put_contents($tmp, 'GIF89a<?php echo 1; ?>');
        $this->assertThrows(fn () => $up->storeFromPath($tmp, 'photos'));
        $this->assertThrows(fn () => $up->storeFromPath($tmp, '../etc'), 'pasta inválida');
        $up->delete('photos', $name);
        $up->delete('photos', '../../etc/passwd');
        $this->assertFalse(is_file("{$dir}/photos/{$name}"));
        @unlink($tmp);
        @rmdir("{$dir}/photos/thumbs");
        @rmdir("{$dir}/photos");
        @rmdir($dir);
    }
}
