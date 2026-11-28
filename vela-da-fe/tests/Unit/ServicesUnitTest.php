<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Validator;
use App\Models\Candle;
use App\Services\Moderation;
use App\Services\Pix;
use Tests\TestCase;

final class ServicesUnitTest extends TestCase
{
    public function testPixCrc16MatchesReferenceVector(): void
    {
        // vetor de teste padrão do CRC16-CCITT/FALSE
        $this->assertSame('29B1', Pix::crc16('123456789'));
    }

    public function testPixPayloadIsWellFormed(): void
    {
        $pix = new Pix('pix@igreja.com', 'Paróquia São José', 'São Paulo');
        $p = $pix->payload(15.5, 'VELAabc123', 'Vela da Fe');
        $this->assertContains('br.gov.bcb.pix', $p);
        $this->assertContains('540515.50', $p);
        $this->assertContains('5802BR', $p);
        $this->assertSame(Pix::crc16(substr($p, 0, -4)), substr($p, -4));
        $this->assertContains('PAROQUIA SAO JOSE', $p);
        $this->assertThrows(fn () => $pix->payload(0, 'x'));
        $this->assertThrows(fn () => (new Pix('', 'x', 'y'))->payload(5, 'x'));
    }

    public function testPixKeyValidation(): void
    {
        foreach (['12345678901', '12345678000199', '+5511999998888', 'a@b.com', '123e4567-e89b-12d3-a456-426614174000'] as $k) {
            $this->assertTrue(Pix::validKey($k), $k);
        }
        foreach (['', 'abc', '123', 'a b'] as $k) {
            $this->assertFalse(Pix::validKey($k), $k);
        }
    }

    public function testModerationFlagsLinksPhonesAndBannedWords(): void
    {
        $m = new Moderation(['palavrão']);
        $this->assertSame(null, $m->flag('Peço saúde para minha mãe, Maria.'));
        $this->assertSame('contém link', $m->flag('veja www.golpe.com agora'));
        $this->assertSame('contém link', $m->flag('https://x.y/z'));
        $this->assertSame('contém e-mail', $m->flag('fale com a@b.org'));
        $this->assertSame('contém telefone/número longo', $m->flag('ligue (11) 99999-8888'));
        $this->assertSame('palavra bloqueada', $m->flag('que PALAVRÃO feio'));
        $this->assertSame('palavra bloqueada', $m->flag('p4l4vr40'));
        $this->assertSame('texto repetitivo', $m->flag('aaaaaaaaaaaa'));
        $this->assertSame(null, $m->flag(''));
    }

    public function testValidatorRules(): void
    {
        $v = Validator::make(['a' => 'x', 'b' => '#12ab9F', 'c' => '5,50', 'd' => '25:00', 'e' => 'não'], [
            'a' => 'required|min:2', 'b' => 'hexcolor', 'c' => 'money', 'd' => 'time', 'e' => 'in:sim',
        ]);
        $errors = $v->errors();
        $this->assertTrue(isset($errors['a']) && isset($errors['d']) && isset($errors['e']));
        $this->assertFalse(isset($errors['b']) || isset($errors['c']));
    }

    public function testHelpers(): void
    {
        $this->assertSame('R$ 5,00', money(500));
        $this->assertSame('Grátis', money(0, true));
        $this->assertSame(1550, to_cents('15,50'));
        $this->assertSame('5,00', cents_input(500));
        $this->assertSame('7 dias', hours_human(168));
        $this->assertSame('1 dia', hours_human(24));
        $this->assertSame('36 horas', hours_human(36));
        $this->assertSame('apagada', remaining_human(date('Y-m-d H:i:s', time() - 10)));
        $this->assertContains('dia', remaining_human(date('Y-m-d H:i:s', time() + 90000)));
        $this->assertSame('paroquia-sao-jose', slugify('Paróquia São José'));
        $this->assertSame('&lt;b&gt;', e('<b>'));
    }

    public function testCandlePrivacyRules(): void
    {
        $base = ['status' => 'active', 'expires_at' => date('Y-m-d H:i:s', time() + 3600), 'category' => 'saude', 'name' => 'Ana',
            'show_name' => 1, 'show_intention' => 1, 'intention' => 'texto', 'for_whom' => 'mãe', 'mod_status' => 'approved'];
        $p = Candle::present($base);
        $this->assertSame('Ana', $p['display_name']);
        $this->assertSame('texto', $p['display_text']);
        $this->assertTrue($p['lit']);

        $p = Candle::present(['show_name' => 0, 'show_intention' => 0] + $base);
        $this->assertSame('Anônimo', $p['display_name']);
        $this->assertSame(null, $p['display_text']);
        $this->assertSame(null, $p['display_whom']);

        $p = Candle::present(['mod_status' => 'pending'] + $base);
        $this->assertSame('Anônimo', $p['display_name']);
        $this->assertSame(null, $p['display_text']);
        $this->assertTrue($p['text_pending']);

        $p = Candle::present(['expires_at' => date('Y-m-d H:i:s', time() - 5)] + $base);
        $this->assertFalse($p['lit']);
    }

    public function testCandlePriceAndCodes(): void
    {
        $this->assertSame(2000, Candle::price(['price' => 500], ['price' => 1500]));
        $this->assertTrue(Candle::validCode('0123456789ab'));
        $this->assertFalse(Candle::validCode('0123456789ZZ'));
        $this->assertFalse(Candle::validCode("../etc/passwd"));
        $this->assertSame(40, strlen(Candle::newToken()));
    }
}
