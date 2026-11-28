<?php
declare(strict_types=1);

/** Cria velas de exemplo para testar o visual (NÃO use em produção): php bin/seed-demo.php */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$config = require dirname(__DIR__) . '/app/bootstrap.php';
if ($config->get('app.env') === 'production' && ($argv[1] ?? '') !== '--force') {
    fwrite(STDERR, "APP_ENV=production: use --force se tiver certeza.\n");
    exit(1);
}
$app = new App\Core\App($config, BASE_PATH);
$candles = $app->model(App\Models\Candle::class);
$types = $app->model(App\Models\CandleType::class)->active();
$plans = $app->model(App\Models\CandlePlan::class)->active();
$samples = [
    ['Ana Paula', 'minha avó Dulce', 'Pela saúde da minha avó, que está internada.', 'saude'],
    ['Carlos', '', 'Obrigado, Senhor, pelo emprego novo!', 'gratidao'],
    ['Família Souza', 'nossos pais', 'Que a paz reine em nossa casa.', 'familia'],
    ['Lúcia', 'meu esposo Antônio (in memoriam)', 'Descanse em paz, meu amor.', 'falecidos'],
    ['Pedro Henrique', '', 'Peço luz para a prova de amanhã.', 'estudos'],
    ['Marta', 'meu filho', 'Que ele encontre o caminho de volta para casa.', 'conversao'],
];
foreach ($samples as $i => [$name, $whom, $text, $cat]) {
    $type = $types[$i % count($types)];
    $plan = $plans[$i % 2];
    if ((int) $type['price'] > 0 || (int) $plan['price'] > 0) {
        $type = $types[0];
        $plan = $plans[0];
    }
    $c = $candles->create([
        'type_id' => $type['id'], 'plan_id' => $plan['id'], 'category' => $cat, 'name' => $name, 'show_name' => 1,
        'for_whom' => $whom, 'intention' => $text, 'show_intention' => 1, 'status' => 'active', 'mod_status' => 'approved',
        'amount' => 0, 'hours' => $plan['hours'], 'payment_method' => 'free',
    ], App\Models\Candle::newToken());
    echo "Vela {$c['code']} ({$name})\n";
}
