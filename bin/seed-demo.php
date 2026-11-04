<?php
declare(strict_types=1);

/**
 * Popula o banco com eventos e um álbum de exemplo (apenas para testes).
 * Uso: php bin/seed-demo.php
 */
if (PHP_SAPI !== 'cli') {
    exit("Somente via linha de comando.\n");
}
$config = require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Models\Album;
use App\Models\Event;

$db = new Database($config->get('db'), BASE_PATH);
$db->migrate(BASE_PATH . '/database');
$events = new Event($db);
$d = static fn (int $days, string $time) => date('Y-m-d', strtotime("+{$days} days")) . 'T' . $time;

$samples = [
    [true, ['title' => 'Trilha da Cachoeira', 'start_at' => $d(5, '07:30'), 'end_at' => $d(5, '16:00'), 'location' => 'Posto Shell da entrada da cidade', 'city' => 'Atibaia - SP', 'organizer' => 'Ducks Offroad', 'level' => 'moderado', 'distance_km' => '85', 'price' => 'Gratuito (almoço por conta)', 'description' => "Saída pontual às 8h. Trilha com subidas de pedra e travessia de rio.\nLeve água, kit de ferramentas e câmara reserva."]],
    [true, ['title' => 'Rolê de Iniciantes', 'start_at' => $d(12, '08:00'), 'location' => 'Sítio do Zé', 'city' => 'Bragança Paulista - SP', 'organizer' => 'Ducks Offroad', 'level' => 'leve', 'distance_km' => '40', 'price' => 'Gratuito', 'description' => 'Trilha leve, ideal para quem está começando. Muita estradinha de terra e paradas para fotos.']],
    [false, ['title' => '5ª Trilha Solidária dos Lobos', 'start_at' => $d(19, '07:00'), 'end_at' => $d(19, '17:00'), 'location' => 'Parque de Exposições', 'city' => 'Socorro - SP', 'organizer' => 'Lobos do Barro', 'level' => 'pesado', 'distance_km' => '120', 'price' => 'R$ 80 + 1 kg de alimento', 'external_link' => 'https://example.com/inscricao', 'description' => 'Evento beneficente com café da manhã, almoço e camiseta.']],
    [false, ['title' => 'Enduro Regional - Etapa 4', 'start_at' => $d(33, '06:30'), 'city' => 'Extrema - MG', 'organizer' => 'Federação Mineira', 'level' => 'extremo', 'price' => 'R$ 250', 'description' => 'Etapa válida pelo campeonato regional.']],
];
foreach ($samples as [$own, $data]) {
    $events->create($data, $own, 'approved', null, null);
}
(new Album($db))->create('Trilha da Serra - Abertura da temporada', 'Fotos do primeiro rolê do ano.', date('Y-m-d', strtotime('-20 days')));
echo "✔ Dados de exemplo criados.\n";
