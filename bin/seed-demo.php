<?php
declare(strict_types=1);

/**
 * Popula o banco com trilhas e um álbum de exemplo (apenas para testes).
 * Uso: php bin/seed-demo.php
 */
if (PHP_SAPI !== 'cli') {
    exit("Somente via linha de comando.\n");
}
$config = require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Models\Album;
use App\Models\Event;
use App\Services\ImageUploader;

$db = new Database($config->get('db'));
$db->migrate(BASE_PATH . '/database');
$events = new Event($db);
$uploader = new ImageUploader(BASE_PATH . '/public/uploads', 8 * 1048576);
$d = static fn (int $days, string $time) => date('Y-m-d', strtotime("+{$days} days")) . 'T' . $time;
$day = static fn (int $days) => date('Y-m-d', strtotime("+{$days} days"));

/** Gera um "flyer" simples (retrato 4:5) só para demonstração. */
$banner = static function (string $title, array $rgb) use ($uploader): string {
    $im = imagecreatetruecolor(1080, 1350);
    for ($y = 0; $y < 1350; $y++) {
        $k = $y / 1350;
        $c = imagecolorallocate($im, (int) ($rgb[0] * (1 - $k * .7)), (int) ($rgb[1] * (1 - $k * .7)), (int) ($rgb[2] * (1 - $k * .7)));
        imageline($im, 0, $y, 1080, $y, $c);
    }
    $white = imagecolorallocate($im, 255, 255, 255);
    imagefilledrectangle($im, 60, 1050, 1020, 1060, $white);
    $x = 60;
    $ascii = strtoupper((string) iconv('UTF-8', 'ASCII//TRANSLIT', $title));
    foreach (str_split($ascii, 22) as $i => $line) {
        // fonte embutida ampliada
        $tmp = imagecreatetruecolor(strlen($line) * 9, 16);
        imagefill($tmp, 0, 0, imagecolorallocatealpha($tmp, 0, 0, 0, 127));
        imagesavealpha($tmp, true);
        imagestring($tmp, 5, 0, 0, $line, imagecolorallocate($tmp, 255, 255, 255));
        imagecopyresampled($im, $tmp, $x, 1100 + $i * 90, 0, 0, strlen($line) * 40, 72, strlen($line) * 9, 16);
        imagedestroy($tmp);
    }
    $file = tempnam(sys_get_temp_dir(), 'bn') . '.jpg';
    imagejpeg($im, $file, 90);
    imagedestroy($im);
    $name = $uploader->storeFromPath($file, 'events', 1600, 600);
    @unlink($file);
    return $name;
};

$samples = [
    [true, [255, 122, 26], ['title' => 'Trilha da Cachoeira', 'category' => 'trilha', 'start_at' => $d(5, '07:30'), 'end_at' => $d(5, '16:00'), 'location' => 'Posto Shell da entrada da cidade', 'city' => 'Atibaia', 'state' => 'SP', 'organizer' => 'Ducks Offroad', 'level' => 'moderado', 'distance_km' => '85', 'price' => 'Gratuito', 'includes' => 'Apoio com carro e resgate', 'instagram_url' => 'https://www.instagram.com/ducksoffroad/', 'description' => "Saída pontual às 8h. Trilha com subidas de pedra e travessia de rio.\nLeve água, kit de ferramentas e câmara reserva."]],
    [true, null, ['title' => 'Rolê de Iniciantes', 'category' => 'treino', 'start_at' => $d(12, '08:00'), 'location' => 'Sítio do Zé', 'city' => 'Bragança Paulista', 'state' => 'SP', 'organizer' => 'Ducks Offroad', 'level' => 'leve', 'distance_km' => '40', 'price' => 'Gratuito', 'capacity' => '20', 'description' => 'Trilha leve, ideal para quem está começando.']],
    [false, [40, 120, 200], ['title' => '5ª Trilha Solidária dos Lobos', 'category' => 'beneficente', 'start_at' => $d(19, '07:00'), 'end_at' => $d(19, '17:00'), 'location' => 'Parque de Exposições', 'city' => 'Socorro', 'state' => 'SP', 'organizer' => 'Lobos do Barro', 'level' => 'pesado', 'distance_km' => '120', 'price' => 'R$ 80 + 1 kg de alimento', 'registration_until' => $day(15), 'capacity' => '300', 'includes' => 'Café da manhã, almoço, camiseta e seguro', 'external_link' => 'https://example.com/inscricao', 'instagram_url' => 'https://www.instagram.com/p/ABCdef123/', 'whatsapp' => '(19) 99876-5432', 'description' => 'Evento beneficente.']],
    [false, [190, 40, 60], ['title' => 'Enduro Regional - Etapa 4', 'category' => 'enduro', 'start_at' => $d(33, '06:30'), 'city' => 'Extrema', 'state' => 'MG', 'organizer' => 'Federação Mineira', 'level' => 'extremo', 'price' => 'R$ 250', 'registration_until' => $day(25), 'instagram_url' => 'https://www.instagram.com/p/XYZenduro4/', 'description' => 'Etapa válida pelo campeonato regional.']],
    [false, [60, 150, 80], ['title' => 'Trilhão das Araucárias', 'category' => 'trilha', 'start_at' => $d(48, '07:00'), 'end_at' => $d(49, '15:00'), 'city' => 'Campos do Jordão', 'state' => 'SP', 'organizer' => 'Serra Trail', 'level' => 'moderado', 'distance_km' => '160', 'price' => 'R$ 150', 'includes' => 'Pousada, jantar e camiseta', 'instagram_url' => 'https://www.instagram.com/serratrail/']],
    [false, [120, 80, 200], ['title' => 'Encontro Off-Road do Sul', 'category' => 'encontro', 'start_at' => $d(70, '09:00'), 'city' => 'Rio Negrinho', 'state' => 'SC', 'organizer' => 'Moto Clube Sul', 'level' => 'leve', 'price' => 'R$ 60']],
];
foreach ($samples as [$own, $color, $data]) {
    $events->create($data, $own, 'approved', null, $color ? $banner($data['title'], $color) : null);
}
(new Album($db))->create('Trilha da Serra - Abertura da temporada', 'Fotos do primeiro rolê do ano.', date('Y-m-d', strtotime('-20 days')));
echo "✔ Dados de exemplo criados.\n";
