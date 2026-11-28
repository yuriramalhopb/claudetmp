<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Candle;

final class PublicFlowTest extends FeatureTestCase
{
    public function testPublicPagesRender(): void
    {
        $h = $this->http();
        foreach (['/', '/acender', '/velas', '/velas?aba=memorial', '/oracoes', '/oracoes/pai-nosso', '/gracas', '/igreja', '/privacidade', '/minhas-velas', '/sitemap.xml', '/robots.txt', '/api/resumo'] as $path) {
            $h->get($path);
            $this->assertSame(200, $h->status, "{$path} retornou {$h->status}");
        }
        $h->get('/nao-existe');
        $this->assertSame(404, $h->status);
        $h->get('/oracoes/nao-existe');
        $this->assertSame(404, $h->status);
        $h->get('/vela/zzzzzzzzzzzz');
        $this->assertSame(404, $h->status);
    }

    public function testSecurityHeaders(): void
    {
        $h = $this->http();
        $h->get('/');
        $csp = $h->header('content-security-policy');
        $this->assertContains("default-src 'self'", $csp);
        $this->assertContains("'nonce-", $csp);
        $this->assertNotContains('unsafe-inline', $csp);
        $this->assertSame('nosniff', $h->header('x-content-type-options'));
        $this->assertSame('DENY', $h->header('x-frame-options'));
        $this->assertNotContains(' style="', $h->body);
        $this->assertNotContains(' onclick=', $h->body);
    }

    public function testLightFreeCandleAndPublicVisibility(): void
    {
        [$h, $code, $url] = $this->light(['name' => 'Joana Silva', 'intention' => 'Pela paz na minha casa.']);
        $this->assertTrue(strlen($code) === 12, 'não redirecionou para a vela: ' . $h->url . ' ' . $h->flashText());
        $this->assertContains('Sua vela está acesa', $h->body);
        $c = $this->candle($code);
        $this->assertSame('active', $c['status']);
        $this->assertSame('free', $c['payment_method']);
        $this->assertTrue(strtotime($c['expires_at']) > time() + 23 * 3600);

        $anon = $this->http();
        $anon->get('/vela/' . $code);
        $this->assertSame(200, $anon->status);
        $this->assertContains('Joana Silva', $anon->body);
        $this->assertContains('Pela paz na minha casa.', $anon->body);
        $anon->get('/velas');
        $this->assertContains('/vela/' . $code, $anon->body);
        // o token secreto nunca aparece na página pública
        $this->assertNotContains($c['token_hash'], $anon->body);
        $this->assertNotContains('/minha?t=', $anon->body);
    }

    public function testHiddenNameAndIntentionStayPrivate(): void
    {
        [$h, $code] = $this->light(['name' => 'Sigilo Secreto', 'intention' => 'Pedido bem particular', 'for_whom' => 'fulano', 'show_name' => null, 'show_intention' => null]);
        $anon = $this->http();
        foreach (['/vela/' . $code, '/velas', '/'] as $path) {
            $anon->get($path);
            $this->assertNotContains('Sigilo Secreto', $anon->body, $path);
            $this->assertNotContains('Pedido bem particular', $anon->body, $path);
        }
        $anon->get('/vela/' . $code);
        $this->assertContains('confiou a Deus', $anon->body);
    }

    public function testSuspiciousTextWaitsForModeration(): void
    {
        [$h, $code] = $this->light(['intention' => 'Compre em www.golpe.com agora']);
        $this->assertSame('pending', $this->candle($code)['mod_status']);
        $anon = $this->http();
        $anon->get('/vela/' . $code);
        $this->assertNotContains('golpe', $anon->body);
        $this->assertSame(200, $anon->status);

        // moderador aprova
        $this->staff('mod@teste.local', 'moderator');
        $mod = $this->adminLogin('mod@teste.local', 'Senha1234');
        $id = (int) $this->candle($code)['id'];
        $mod->get('/admin/velas?status=moderation');
        $this->assertContains('/admin/velas/' . $id, $mod->body);
        $mod->post('/admin/velas/' . $id . '/aprovar', [], '/admin/velas/' . $id);
        $this->assertSame('approved', $this->candle($code)['mod_status']);
    }

    public function testManualModerationHoldsPublicTexts(): void
    {
        self::$app->settings()->set('moderation', 'manual');
        [$h, $code] = $this->light(['name' => 'Texto Manual']);
        $this->assertSame('pending', $this->candle($code)['mod_status']);
        [, $code2] = $this->light(['name' => 'Anonimo Sem Texto', 'show_name' => null, 'show_intention' => null]);
        $this->assertSame('approved', $this->candle($code2)['mod_status']);
    }

    public function testPrayOncePerDay(): void
    {
        [, $code] = $this->light();
        $v = $this->http();
        $v->post('/vela/' . $code . '/rezar', [], '/vela/' . $code);
        $this->assertSame(1, (int) $this->candle($code)['prayer_count']);
        $v->post('/vela/' . $code . '/rezar', [], '/vela/' . $code);
        $this->assertSame(1, (int) $this->candle($code)['prayer_count'], 'rezou duas vezes no mesmo dia');
        $this->assertContains('já rezou', $v->flashText());
    }

    public function testManageNeedsTheSecretToken(): void
    {
        [$h, $code, $url] = $this->light();
        $this->assertContains('Guarde este endereço', $h->body);
        preg_match('#\?t=([a-f0-9]{40})#', $h->body, $m);
        $token = $m[1] ?? '';
        $this->assertSame(40, strlen($token));

        $other = $this->http();
        $other->get('/vela/' . $code . '/minha');
        $this->assertSame(403, $other->status);
        $other->get('/vela/' . $code . '/minha?t=' . str_repeat('a', 40));
        $this->assertSame(403, $other->status);
        $other->get('/vela/' . $code . '/minha?t=' . $token);
        $this->assertSame(200, $other->status);
        $other->get('/vela/' . $code . '/certificado?t=' . $token);
        $this->assertContains('Certificado de Vela de Fé', $other->body);

        // acender de novo preenche o formulário só com token válido
        $other->get('/acender?repetir=' . $code . '&t=' . $token);
        $this->assertContains('Maria Teste', $other->body);
        $fresh = $this->http();
        $fresh->get('/acender?repetir=' . $code . '&t=ruim');
        $this->assertNotContains('value="Maria Teste"', $fresh->body);
    }

    public function testPaidCandleFlowWithPixAndAdminConfirmation(): void
    {
        [$type, $plan] = $this->paidIds();
        $this->assertTrue($type > 0 && $plan > 0, 'seed sem tipo/duração pagos');
        [$h, $code] = $this->light(['type_id' => $type, 'plan_id' => $plan, 'name' => 'Pagante']);
        $c = $this->candle($code);
        $this->assertSame('pending_payment', $c['status']);
        $this->assertSame(null, $c['expires_at']);
        $this->assertContains('Pix copia e cola', $h->body);
        $this->assertContains('VELA' . $code, $h->body);
        $this->assertContains('br.gov.bcb.pix', $h->body);

        // ainda não está no site
        $anon = $this->http();
        $anon->get('/vela/' . $code);
        $this->assertSame(404, $anon->status);
        $anon->get('/velas');
        $this->assertNotContains($code, $anon->body);

        // a pessoa avisa que pagou
        $h->post('/vela/' . $code . '/paguei', ['t' => ''], '/vela/' . $code . '/minha');
        $this->assertTrue($this->candle($code)['paid_claimed_at'] !== null);

        // moderador confirma
        $this->staff('mod2@teste.local', 'moderator');
        $mod = $this->adminLogin('mod2@teste.local', 'Senha1234');
        $id = (int) $c['id'];
        $mod->get('/admin/velas?status=pending_payment');
        $this->assertContains('Informou que pagou', $mod->body);
        $mod->post('/admin/velas/' . $id . '/confirmar', [], '/admin/velas/' . $id);
        $after = $this->candle($code);
        $this->assertSame('active', $after['status']);
        $this->assertSame('pix', $after['payment_method']);
        $this->assertTrue($after['paid_at'] !== null && strtotime($after['expires_at']) > time() + ((int) $c['hours'] - 1) * 3600);
        $anon->get('/vela/' . $code);
        $this->assertSame(200, $anon->status);

        // confirmar de novo não reinicia o relógio
        $mod->post('/admin/velas/' . $id . '/confirmar', [], '/admin/velas/' . $id);
        $this->assertSame($after['expires_at'], $this->candle($code)['expires_at']);
    }

    public function testPaidOptionsRejectedWithoutPixKey(): void
    {
        [$type, $plan] = $this->paidIds();
        $settings = self::$app->settings();
        $app = self::$app;
        // o servidor de testes tem PIX_KEY no ambiente; aqui simulamos preço > 0 com chave inválida via configuração
        $settings->set('pix_key', 'chave invalida');
        // Pix::isConfigured usa a chave das configurações primeiro, então fica inválida
        [$h, $code] = $this->light(['type_id' => $type, 'plan_id' => $plan]);
        $settings->set('pix_key', '');
        $this->assertSame('', $code, 'criou vela paga sem Pix configurado');
        $this->assertContains('não está disponível', $h->flashText() . $h->body);
    }

    public function testDailyLimitPerPerson(): void
    {
        self::$app->settings()->set('max_per_day', '2');
        $h = $this->http();
        $this->light([], $h);
        $this->light([], $h);
        [$h3, $code] = $this->light([], $h);
        $this->assertSame('', $code);
        $this->assertContains('já acendeu', $h3->flashText());
    }

    public function testFormValidationAndCsrfAndHoneypot(): void
    {
        $h = $this->http();
        $h->post('/acender', ['name' => ''], '/acender');
        $this->assertSame(200, $h->status);
        $this->assertSame([], array_filter([preg_match('#/vela/#', $h->url)]));
        $r = $this->http()->post('/acender', ['name' => 'x'], null, true, false);
        $this->assertSame(419, $r->status);
        $before = (int) self::$app->db->fetchValue('SELECT COUNT(*) FROM candles');
        $this->light(['website' => 'http://spam']);
        $this->assertSame($before, (int) self::$app->db->fetchValue('SELECT COUNT(*) FROM candles'), 'honeypot não bloqueou');
    }

    public function testXssIsEscaped(): void
    {
        [, $code] = $this->light(['name' => '<script>alert(1)</script>', 'intention' => '<img src=x onerror=alert(2)>']);
        // texto com HTML não tem link/telefone, então é publicado: precisa sair escapado
        $anon = $this->http();
        $anon->get('/vela/' . $code);
        $this->assertNotContains('<script>alert(1)</script>', $anon->body);
        $this->assertNotContains('<img src=x', $anon->body);
        $this->assertContains('&lt;script&gt;', $anon->body);
    }

    public function testRecoverLinksByEmailDoesNotRevealAccounts(): void
    {
        [, $code] = $this->light(['email' => 'dona@example.com']);
        $old = $this->candle($code)['token_hash'];
        $h = $this->http();
        $h->post('/minhas-velas', ['email' => 'ninguem@example.com'], '/minhas-velas');
        $msg1 = $h->flashText();
        $h->post('/minhas-velas', ['email' => 'dona@example.com'], '/minhas-velas');
        $this->assertSame($msg1, $h->flashText(), 'resposta diferente revela o cadastro');
        $this->assertTrue($this->candle($code)['token_hash'] !== $old, 'token não foi renovado');
        $this->assertTrue((int) self::$app->db->fetchValue("SELECT COUNT(*) FROM mail_queue WHERE to_email = 'dona@example.com'") >= 2);
    }

    public function testTestimonialsNeedApproval(): void
    {
        $h = $this->http();
        $h->post('/gracas', ['name' => 'Fiel Agradecido', 'text' => 'Recebi uma graça enorme depois de rezar.'], '/gracas');
        $this->assertContains('será publicado', $h->flashText());
        $h->get('/gracas');
        $this->assertNotContains('Fiel Agradecido', $h->body);
        $id = (int) self::$app->db->fetchValue("SELECT id FROM testimonials WHERE name = 'Fiel Agradecido'");
        $admin = $this->adminLogin();
        $admin->post('/admin/gracas/' . $id . '/status', ['status' => 'approved'], '/admin/gracas');
        $h->get('/gracas');
        $this->assertContains('Fiel Agradecido', $h->body);
    }

    public function testExpiredCandleMovesToMemorial(): void
    {
        [, $code] = $this->light(['name' => 'Vela Memorial']);
        self::$app->db->execute('UPDATE candles SET expires_at = ? WHERE code = ?', [date('Y-m-d H:i:s', time() - 60), $code]);
        $h = $this->http();
        $h->get('/velas');
        $this->assertNotContains('Vela Memorial', $h->body);
        $h->get('/velas?aba=memorial');
        $this->assertContains('Vela Memorial', $h->body);
        $h->get('/vela/' . $code);
        $this->assertContains('já se apagou', $h->body);
    }
}
