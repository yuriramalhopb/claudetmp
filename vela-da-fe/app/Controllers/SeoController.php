<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Prayer;

final class SeoController extends Controller
{
    public function robots(Request $request): Response
    {
        $base = (string) $this->app->config->get('app.url');
        $txt = "User-agent: *\nDisallow: /admin\nDisallow: /minhas-velas\nSitemap: {$base}/sitemap.xml\n";
        return new Response($txt, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(Request $request): Response
    {
        $base = (string) $this->app->config->get('app.url');
        $urls = ['/', '/acender', '/velas', '/oracoes', '/gracas', '/igreja', '/privacidade'];
        foreach ($this->app->model(Prayer::class)->active() as $p) {
            $urls[] = '/oracoes/' . $p['slug'];
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            $xml .= '  <url><loc>' . htmlspecialchars($base . $u, ENT_XML1) . '</loc></url>' . "\n";
        }
        return new Response($xml . '</urlset>', 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
