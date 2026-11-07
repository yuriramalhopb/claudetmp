<?php
declare(strict_types=1);

namespace Tests;

/**
 * Cliente HTTP de teste com cookies (sessão) e helpers de formulário.
 */
final class Http
{
    private string $cookieFile;
    public int $status = 0;
    public string $body = '';
    public array $headers = [];
    public string $url = '';

    public function __construct(private readonly string $base)
    {
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'ck');
    }

    public function __destruct()
    {
        @unlink($this->cookieFile);
    }

    public function get(string $path, bool $follow = true): self
    {
        return $this->request('GET', $path, null, $follow);
    }

    /** POST com o token CSRF da página $from (ou o token já conhecido). */
    public function post(string $path, array $data = [], ?string $from = null, bool $follow = true, bool $csrf = true): self
    {
        if ($csrf && !isset($data['_csrf'])) {
            $data['_csrf'] = $this->csrf($from ?? $path);
        }
        return $this->request('POST', $path, $data, $follow);
    }

    public function csrf(string $page): string
    {
        $this->get($page);
        if (!preg_match('/name="_csrf" value="([a-f0-9]{64})"/', $this->body, $m)
            && !preg_match('/name="csrf-token" content="([a-f0-9]{64})"/', $this->body, $m)) {
            throw new AssertionFailed("Token CSRF não encontrado em {$page} (status {$this->status})");
        }
        return $m[1];
    }

    /** @return array<int, array{0:string,1:string}> mensagens [tipo, texto] */
    public function flashes(): array
    {
        preg_match_all('#<div class="flash flash-(\w+)">\s*<span>(.*?)</span>#s', $this->body, $m, PREG_SET_ORDER);
        return array_map(fn ($x) => [$x[1], html_entity_decode($x[2], ENT_QUOTES | ENT_HTML5, 'UTF-8')], $m);
    }

    public function flashText(): string
    {
        return implode(' | ', array_map(fn ($f) => $f[1], $this->flashes()));
    }

    public function header(string $name): string
    {
        return $this->headers[strtolower($name)] ?? '';
    }

    private function request(string $method, string $path, ?array $data, bool $follow): self
    {
        $ch = curl_init($this->base . $path);
        $this->headers = [];
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => $follow,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HEADERFUNCTION => function ($ch, $line) {
                if (str_contains($line, ':')) {
                    [$k, $v] = explode(':', $line, 2);
                    $this->headers[strtolower(trim($k))] = trim($v);
                }
                return strlen($line);
            },
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $hasFile = (bool) array_filter($data ?? [], fn ($v) => $v instanceof \CURLFile);
            $opts[CURLOPT_POSTFIELDS] = $hasFile ? $data : http_build_query($data ?? []);
        }
        curl_setopt_array($ch, $opts);
        $this->body = (string) curl_exec($ch);
        $this->status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $this->url = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        return $this;
    }
}
