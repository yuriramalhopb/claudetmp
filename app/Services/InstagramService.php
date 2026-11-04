<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Models\InstagramPost;
use App\Models\Setting;
use RuntimeException;

/**
 * Integração com a API do Instagram (Instagram API with Instagram Login).
 *
 * - Busca as publicações da conta da turma em graph.instagram.com
 * - Baixa e RECODIFICA as imagens localmente (URLs do CDN do Instagram expiram)
 * - Renova automaticamente o token de longa duração (válido por 60 dias)
 * - Pode rodar via cron (bin/instagram-sync.php) ou sob demanda pelo painel
 *
 * O token fica no banco (settings) ou no .env e NUNCA é exibido no HTML.
 */
final class InstagramService
{
    private const API = 'https://graph.instagram.com';
    private const FIELDS = 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp';
    private const ALLOWED_MEDIA_HOSTS = ['cdninstagram.com', 'fbcdn.net', 'instagram.com'];

    public function __construct(
        private readonly InstagramPost $posts,
        private readonly Setting $settings,
        private readonly ImageUploader $uploader,
        private readonly Logger $logger,
        private readonly string $envToken,
        private readonly int $syncMinutes,
    ) {
    }

    public function token(): string
    {
        return (string) ($this->settings->get('instagram_token') ?: $this->envToken);
    }

    public function isConfigured(): bool
    {
        return $this->token() !== '';
    }

    public function lastSync(): ?string
    {
        return $this->settings->get('instagram_last_sync');
    }

    /** Sincroniza se o intervalo configurado já passou (chamado de forma "preguiçosa" no site). */
    public function syncIfDue(): void
    {
        if (!$this->isConfigured() || $this->syncMinutes <= 0) {
            return;
        }
        $last = (int) strtotime((string) $this->lastSync());
        if (time() - $last < $this->syncMinutes * 60) {
            return;
        }
        // trava simples: evita que várias requisições sincronizem ao mesmo tempo
        $this->settings->set('instagram_last_sync', date('Y-m-d H:i:s'));
        try {
            $this->sync();
        } catch (\Throwable $e) {
            $this->logger->error('Instagram sync falhou: ' . $e->getMessage());
        }
    }

    /** @return int quantidade de posts processados */
    public function sync(int $limit = 30): int
    {
        $token = $this->token();
        if ($token === '') {
            throw new RuntimeException('Token do Instagram não configurado.');
        }
        $this->maybeRefreshToken();

        $data = $this->getJson(self::API . '/me/media?' . http_build_query([
            'fields' => self::FIELDS,
            'limit' => min(max($limit, 1), 50),
            'access_token' => $this->token(),
        ]));

        $count = 0;
        foreach (($data['data'] ?? []) as $item) {
            if (!is_array($item) || !isset($item['id'], $item['permalink'])) {
                continue;
            }
            if (!preg_match(InstagramPost::PERMALINK_REGEX, (string) $item['permalink'])) {
                continue;
            }
            $existing = $this->posts->findByIgId((string) $item['id']);
            $localFile = null;
            if (!$existing || empty($existing['local_file'])) {
                $imageUrl = ($item['media_type'] ?? '') === 'VIDEO'
                    ? ($item['thumbnail_url'] ?? null)
                    : ($item['media_url'] ?? null);
                if (is_string($imageUrl)) {
                    $localFile = $this->downloadImage($imageUrl);
                }
            }
            $this->posts->upsertFromApi($item, $localFile);
            $count++;
        }
        $this->settings->set('instagram_last_sync', date('Y-m-d H:i:s'));
        $this->settings->set('instagram_last_error', null);
        return $count;
    }

    /** Token de longa duração: renova quando tem mais de 30 dias. */
    private function maybeRefreshToken(): void
    {
        $refreshedAt = (int) strtotime((string) $this->settings->get('instagram_token_refreshed', ''));
        if ($refreshedAt && time() - $refreshedAt < 30 * 86400) {
            return;
        }
        try {
            $data = $this->getJson(self::API . '/refresh_access_token?' . http_build_query([
                'grant_type' => 'ig_refresh_token',
                'access_token' => $this->token(),
            ]));
            if (!empty($data['access_token']) && is_string($data['access_token'])) {
                $this->settings->set('instagram_token', $data['access_token']);
            }
            $this->settings->set('instagram_token_refreshed', date('Y-m-d H:i:s'));
        } catch (\Throwable $e) {
            // tokens com menos de 24h não podem ser renovados; segue com o atual
            $this->logger->info('Instagram: renovação de token ignorada: ' . $e->getMessage());
        }
    }

    private function downloadImage(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $allowed = false;
        foreach (self::ALLOWED_MEDIA_HOSTS as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.' . $suffix)) {
                $allowed = true;
            }
        }
        if (!$allowed || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            return null; // proteção contra SSRF
        }
        $tmp = tempnam(sys_get_temp_dir(), 'ig');
        try {
            $bytes = $this->http($url, 10 * 1024 * 1024);
            file_put_contents($tmp, $bytes);
            return $this->uploader->storeFromPath($tmp, 'instagram', 1080, 480);
        } catch (\Throwable $e) {
            $this->logger->error('Instagram: falha ao baixar imagem: ' . $e->getMessage());
            return null;
        } finally {
            @unlink($tmp);
        }
    }

    private function getJson(string $url): array
    {
        $body = $this->http($url, 2 * 1024 * 1024);
        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new RuntimeException('Resposta inválida da API do Instagram.');
        }
        if (isset($data['error'])) {
            $msg = is_array($data['error']) ? (string) ($data['error']['message'] ?? 'erro') : 'erro';
            $this->settings->set('instagram_last_error', mb_substr($msg, 0, 300));
            throw new RuntimeException('Instagram: ' . $msg);
        }
        return $data;
    }

    private function http(string $url, int $maxBytes): string
    {
        $ch = curl_init($url);
        $buffer = '';
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'DucksOffroadSite/1.0',
            CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (&$buffer, $maxBytes): int {
                $buffer .= $chunk;
                return strlen($buffer) > $maxBytes ? 0 : strlen($chunk); // aborta se exceder
            },
        ]);
        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($ok === false && $buffer === '') {
            throw new RuntimeException('Falha de conexão: ' . $error);
        }
        if ($status >= 400 && !str_starts_with(ltrim($buffer), '{')) {
            throw new RuntimeException('HTTP ' . $status);
        }
        return $buffer;
    }
}
