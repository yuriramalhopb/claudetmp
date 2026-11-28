<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Core\App;
use App\Models\Candle;
use App\Models\Setting;
use App\Models\User;
use Tests\Http;
use Tests\TestCase;

/** Base dos testes de ponta a ponta: servidor PHP real + banco de teste. */
abstract class FeatureTestCase extends TestCase
{
    public static string $base = '';
    public static App $app;

    public function setUp(): void
    {
        self::$app->db->execute('DELETE FROM rate_limits');
        self::$app->settings()->set('moderation', 'auto');
        self::$app->settings()->set('max_per_day', '50');
    }

    protected function http(): Http
    {
        return new Http(self::$base);
    }

    protected function adminLogin(string $email = 'admin@teste.local', string $password = 'Admin12345'): Http
    {
        $h = $this->http();
        $h->post('/admin/entrar', ['email' => $email, 'password' => $password]);
        return $h;
    }

    /** Equipe com o perfil pedido (cria se não existir). */
    protected function staff(string $email, string $role, string $password = 'Senha1234'): array
    {
        $users = self::$app->model(User::class);
        $u = $users->findByEmail($email) ?? $users->find($users->create('Equipe ' . $role, $email, $password, $role));
        return $u;
    }

    /** Acende uma vela pela tela pública e devolve [http, código, URL de gerenciamento]. */
    protected function light(array $over = [], ?Http $h = null): array
    {
        $h ??= $this->http();
        $type = self::$app->db->fetchValue('SELECT id FROM candle_types WHERE price = 0 AND active = 1 ORDER BY id LIMIT 1');
        $plan = self::$app->db->fetchValue('SELECT id FROM candle_plans WHERE price = 0 AND active = 1 ORDER BY hours LIMIT 1');
        $data = $over + [
            'type_id' => $type, 'plan_id' => $plan, 'category' => 'saude', 'name' => 'Maria Teste',
            'for_whom' => 'minha mãe', 'intention' => 'Peço saúde para a minha mãe.', 'show_name' => '1', 'show_intention' => '1',
            'terms' => '1',
        ];
        $h->post('/acender', array_filter($data, fn ($v) => $v !== null), '/acender');
        preg_match('#/vela/([a-f0-9]{12})/minha#', $h->url, $m);
        return [$h, $m[1] ?? '', $h->url];
    }

    protected function candle(string $code): array
    {
        return self::$app->model(Candle::class)->findByCode($code) ?? [];
    }

    protected function paidIds(): array
    {
        $type = (int) self::$app->db->fetchValue('SELECT id FROM candle_types WHERE price > 0 AND active = 1 ORDER BY id LIMIT 1');
        $plan = (int) self::$app->db->fetchValue('SELECT id FROM candle_plans WHERE price > 0 AND active = 1 ORDER BY id LIMIT 1');
        return [$type, $plan];
    }
}
