<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

/**
 * Digital Asset Links: prova para o Android que o app (APK/Play Store) pertence a
 * este site. Sem isso, o app TWA abre com uma barra de endereço no topo.
 */
final class AndroidController extends Controller
{
    public function assetLinks(Request $request): Response
    {
        $package = (string) $this->app->config->get('android.package', '');
        $prints = array_values(array_filter(array_map('trim', explode(',', (string) $this->app->config->get('android.sha256', '')))));
        $prints = array_values(array_filter($prints, fn ($p) => (bool) preg_match('/^([0-9A-F]{2}:){31}[0-9A-F]{2}$/i', $p)));
        if (!preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/', $package) || !$prints) {
            throw new HttpException(404);
        }
        return (new Response((string) json_encode([[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => $package,
                'sha256_cert_fingerprints' => array_map('strtoupper', $prints),
            ],
        ]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 200, ['Content-Type' => 'application/json']))
            ->withHeader('Cache-Control', 'public, max-age=3600');
    }
}
