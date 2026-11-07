<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Core\App;
use App\Core\Totp;
use App\Models\User;
use Tests\Http;
use Tests\TestCase;

/**
 * Base dos testes de ponta a ponta: servidor PHP real + banco MySQL de teste.
 */
abstract class FeatureTestCase extends TestCase
{
    public static string $base = '';
    public static App $app;

    public function setUp(): void
    {
        self::$app->db->execute('DELETE FROM rate_limits');
    }

    protected function http(): Http
    {
        return new Http(self::$base);
    }

    /** Cria (ou reaproveita) um trilheiro com a situação desejada. */
    protected function member(string $email, string $status = 'approved', string $password = 'Senha1234', array $extra = []): array
    {
        $users = self::$app->model(User::class);
        $u = $users->findByEmail($email);
        if ($u === null) {
            $id = $users->create($extra + ['name' => 'Trilheiro ' . explode('@', $email)[0], 'email' => $email, 'phone' => '11999998888', 'city' => 'Atibaia', 'experience' => 'intermediario'], $password, $status);
            $u = $users->find($id);
        }
        return $u;
    }

    protected function login(string $email, string $password = 'Senha1234'): Http
    {
        $h = $this->http();
        $h->post('/entrar', ['email' => $email, 'password' => $password]);
        return $h;
    }

    /** Login do admin, configurando o 2FA na primeira vez e respondendo o código depois. */
    protected function admin(): Http
    {
        $users = self::$app->model(User::class);
        $admin = $users->findByEmail('admin@teste.local');
        $h = $this->http();
        $h->post('/entrar', ['email' => 'admin@teste.local', 'password' => 'Admin12345']);
        if ($admin === null || empty($admin['totp_enabled_at'])) {
            $h->get('/minha-conta/2fa');
            preg_match('#<code class="secret">([A-Z2-7 ]+)</code>#', $h->body, $m);
            $secret = str_replace(' ', '', $m[1] ?? '');
            $h->post('/minha-conta/2fa/ativar', ['code' => Totp::code($secret, intdiv(time(), 30))]);
            return $h;
        }
        // evita esperar o próximo intervalo de 30s entre logins seguidos nos testes
        self::$app->db->execute('UPDATE users SET totp_last_step = NULL WHERE id = ?', [(int) $admin['id']]);
        $secret = self::$app->crypto()->decrypt((string) $admin['totp_secret']);
        $h->post('/entrar/2fa', ['code' => Totp::code($secret, intdiv(time(), 30))]);
        return $h;
    }

    /** Arquivo de imagem JPEG válido para upload. */
    protected function jpeg(string $name = 'foto.jpg', int $w = 800, int $h = 600): \CURLFile
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, random_int(0, 255), 120, 40));
        $file = tempnam(sys_get_temp_dir(), 'img') . '.jpg';
        imagejpeg($im, $file, 85);
        imagedestroy($im);
        return new \CURLFile($file, 'image/jpeg', $name);
    }

    protected function file(string $content, string $name, string $mime): \CURLFile
    {
        $file = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($file, $content);
        return new \CURLFile($file, $mime, $name);
    }

    protected function createEvent(array $data = [], bool $own = true): int
    {
        $data += ['title' => 'Trilha de Teste', 'start_at' => date('Y-m-d', strtotime('+10 days')) . 'T08:00', 'city' => 'Atibaia', 'state' => 'SP'];
        $id = self::$app->model(\App\Models\Event::class)->create($data, $own, 'approved', null, null);
        self::$app->db->execute('UPDATE events SET announced_at = NOW() WHERE id = ?', [$id]);
        return $id;
    }
}
