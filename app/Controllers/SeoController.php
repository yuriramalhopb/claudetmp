<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;

/**
 * sitemap.xml e robots.txt dinâmicos para o Google encontrar as páginas públicas.
 */
final class SeoController extends Controller
{
    public function sitemap(Request $request): Response
    {
        $base = (string) $this->app->config->get('app.url');
        $urls = [
            ['/', 'daily', '1.0', null],
            ['/agenda', 'daily', '0.9', null],
            ['/trilhas-do-ano', 'weekly', '0.9', null],
            ['/galeria', 'weekly', '0.7', null],
            ['/instagram', 'daily', '0.6', null],
            ['/trilheiros', 'weekly', '0.5', null],
            ['/ranking', 'weekly', '0.5', null],
            ['/sobre', 'monthly', '0.5', null],
            ['/contato', 'yearly', '0.4', null],
            ['/instalar', 'yearly', '0.3', null],
        ];
        foreach ($this->db->fetchAll("SELECT DISTINCT YEAR(start_at) AS y FROM events WHERE status = 'approved'") as $r) {
            $urls[] = ['/trilhas-do-ano/' . (int) $r['y'], 'weekly', '0.7', null];
        }
        foreach ($this->db->fetchAll("SELECT id, COALESCE(updated_at, created_at) AS m, start_at FROM events WHERE status = 'approved' ORDER BY start_at DESC LIMIT 2000") as $e) {
            $future = strtotime($e['start_at']) > time();
            $urls[] = ['/agenda/' . (int) $e['id'], $future ? 'daily' : 'yearly', $future ? '0.8' : '0.4', $e['m']];
        }
        foreach ($this->db->fetchAll('SELECT id, created_at FROM albums') as $a) {
            $urls[] = ['/galeria/' . (int) $a['id'], 'weekly', '0.6', $a['created_at']];
        }
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        foreach ($urls as [$path, $freq, $prio, $mod]) {
            $xml->startElement('url');
            $xml->writeElement('loc', $base . $path);
            if ($mod) {
                $xml->writeElement('lastmod', date('Y-m-d', (int) strtotime($mod)));
            }
            $xml->writeElement('changefreq', $freq);
            $xml->writeElement('priority', $prio);
            $xml->endElement();
        }
        $xml->endElement();
        return (new Response($xml->outputMemory(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']))
            ->withHeader('Cache-Control', 'public, max-age=3600');
    }

    public function robots(Request $request): Response
    {
        $base = (string) $this->app->config->get('app.url');
        $txt = "User-agent: *\n"
            . "Disallow: /admin\n"
            . "Disallow: /minha-conta\n"
            . "Disallow: /entrar\n"
            . "Disallow: /cadastro\n"
            . "Disallow: /esqueci-senha\n"
            . "Disallow: /redefinir-senha\n"
            . "Disallow: /push/\n"
            . "Disallow: /*.gpx$\n"
            . "Disallow: /*.ics$\n"
            . "\nSitemap: {$base}/sitemap.xml\n";
        return (new Response($txt, 200, ['Content-Type' => 'text/plain; charset=UTF-8']))->withHeader('Cache-Control', 'public, max-age=86400');
    }
}
