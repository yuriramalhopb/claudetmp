<?php
declare(strict_types=1);

namespace Tests\Feature;

final class PublicPagesTest extends FeatureTestCase
{
    public function testPaginasPublicasRespondem(): void
    {
        $this->createEvent(['title' => 'Trilha Pública']);
        $h = $this->http();
        foreach (['/', '/agenda', '/trilhas-do-ano', '/trilhas-do-ano/' . date('Y'), '/galeria', '/instagram', '/trilheiros', '/ranking',
            '/ranking?ano=geral', '/contato', '/sobre', '/privacidade', '/instalar', '/cadastro', '/entrar', '/esqueci-senha'] as $p) {
            $h->get($p);
            $this->assertSame(200, $h->status, $p);
        }
        $h->get('/pagina-que-nao-existe');
        $this->assertSame(404, $h->status);
        $h->get('/trilhas-do-ano/1800');
        $this->assertSame(404, $h->status);
    }

    public function testCabecalhosDeSeguranca(): void
    {
        $h = $this->http()->get('/');
        $csp = $h->header('content-security-policy');
        $this->assertContains("default-src 'self'", $csp);
        $this->assertContains("frame-ancestors 'none'", $csp);
        $this->assertContains("object-src 'none'", $csp);
        $this->assertTrue((bool) preg_match("/script-src 'self' 'nonce-[A-Za-z0-9+\/=]+'/", $csp), 'CSP com nonce');
        $this->assertSame('nosniff', $h->header('x-content-type-options'));
        $this->assertSame('DENY', $h->header('x-frame-options'));
        $this->assertSame('', $h->header('x-powered-by'));
        $this->assertFalse((bool) preg_match('/\son(click|load|error|submit)=/i', $h->body), 'nenhum atributo on*= nas páginas');
    }

    public function testSeoSitemapRobotsECanonical(): void
    {
        $id = $this->createEvent(['title' => 'Trilha SEO </script><b>x</b>']);
        $h = $this->http()->get('/sitemap.xml');
        $xml = simplexml_load_string($h->body);
        $this->assertTrue($xml !== false, 'sitemap é XML válido');
        $this->assertContains('/agenda/' . $id, $h->body);
        $this->assertNotContains('/admin', $h->body);
        $h->get('/robots.txt');
        $this->assertContains('Disallow: /admin', $h->body);
        $h->get('/agenda/' . $id);
        $this->assertSame(1, preg_match_all('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $h->body, $m), 'JSON-LD não é quebrado pelo título');
        $ld = json_decode($m[1][0], true);
        $this->assertSame('SportsEvent', $ld['@type']);
        $this->assertContains('<link rel="canonical"', $h->body);
        $this->assertContains('noindex', $this->http()->get('/entrar')->body);
    }

    public function testPwaEAndroid(): void
    {
        $h = $this->http()->get('/manifest.webmanifest');
        $m = json_decode($h->body, true);
        $this->assertSame('standalone', $m['display']);
        $this->assertTrue(count($m['icons']) >= 4);
        $h->get('/sw.js');
        $this->assertSame(200, $h->status);
        $h->get('/.well-known/assetlinks.json');
        $this->assertSame(404, $h->status, 'sem pacote configurado não expõe assetlinks');
    }

    public function testArquivosInternosNaoAcessiveis(): void
    {
        $h = $this->http();
        foreach (['/.env', '/../.env', '/app/config.php', '/storage/logs/', '/uploads/.htaccess', '/index.php/../.env'] as $p) {
            $h->get($p);
            $this->assertTrue(in_array($h->status, [403, 404], true), "{$p} => {$h->status}");
        }
    }

    public function testContatoComHoneypotELimite(): void
    {
        $h = $this->http();
        $h->post('/contato', ['name' => 'Bot', 'email' => 'bot@x.com', 'message' => 'spam spam spam', 'website' => 'http://spam']);
        $this->assertSame(0, (int) self::$app->db->fetchValue("SELECT COUNT(*) FROM messages WHERE email = 'bot@x.com'"), 'robô ignorado');
        $h->post('/contato', ['name' => 'Maria', 'email' => 'maria@x.com', 'message' => 'Quero participar da trilha!']);
        $this->assertContains('Mensagem enviada', $h->flashText());
        $this->assertSame(1, (int) self::$app->db->fetchValue("SELECT COUNT(*) FROM messages WHERE email = 'maria@x.com'"));
    }
}
