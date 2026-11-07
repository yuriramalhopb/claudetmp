<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Leitura segura de arquivos GPX (Wikiloc, Strava, Garmin, OsmAnd, Relive...).
 *  - recusa DOCTYPE/ENTITY (proteção contra XXE e "billion laughs")
 *  - leitura em streaming (XMLReader), limite de tamanho e de pontos
 *  - calcula distância (haversine) e subida acumulada (com filtro de ruído)
 *  - o arquivo guardado é REGERADO a partir das coordenadas (nunca o XML enviado)
 */
final class Gpx
{
    private const MAX_BYTES = 15 * 1024 * 1024;
    private const MAX_POINTS = 300000;
    private const MAP_POINTS = 1500;

    /**
     * @return array{points: array<int, array{0:float,1:float,2:?float}>, km: float, gain: int, min_ele: ?int, max_ele: ?int, name: string}
     */
    public function parse(string $path): array
    {
        $size = (int) @filesize($path);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new RuntimeException('O arquivo GPX deve ter até 15 MB.');
        }
        $head = (string) file_get_contents($path, false, null, 0, 4096);
        $all = (string) file_get_contents($path);
        if (stripos($all, '<!DOCTYPE') !== false || stripos($all, '<!ENTITY') !== false) {
            throw new RuntimeException('Arquivo GPX inválido.');
        }
        if (stripos($head, '<gpx') === false) {
            throw new RuntimeException('O arquivo não parece ser um GPX.');
        }
        unset($all);

        $reader = new \XMLReader();
        if (!$reader->open($path, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException('Não foi possível ler o GPX.');
        }
        $points = [];
        $routePoints = [];
        $name = '';
        $current = null;
        $inEle = false;
        $inName = false;
        $depthTrk = 0;
        libxml_use_internal_errors(true);
        while (@$reader->read()) {
            if ($reader->nodeType === \XMLReader::ELEMENT) {
                $tag = strtolower($reader->localName);
                if ($tag === 'trkpt' || $tag === 'rtept') {
                    $lat = (float) $reader->getAttribute('lat');
                    $lon = (float) $reader->getAttribute('lon');
                    if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180 || ($lat == 0.0 && $lon == 0.0)) {
                        $current = null;
                        continue;
                    }
                    $current = ['tag' => $tag, 'p' => [round($lat, 6), round($lon, 6), null]];
                    if ($reader->isEmptyElement) {
                        $this->push($current, $points, $routePoints);
                        $current = null;
                    }
                } elseif ($tag === 'ele' && $current !== null) {
                    $inEle = true;
                } elseif ($tag === 'name' && $name === '') {
                    $inName = true;
                }
            } elseif ($reader->nodeType === \XMLReader::TEXT || $reader->nodeType === \XMLReader::CDATA) {
                if ($inEle && $current !== null && is_numeric(trim($reader->value))) {
                    $current['p'][2] = round((float) trim($reader->value), 1);
                } elseif ($inName) {
                    $name = mb_substr(trim($reader->value), 0, 120);
                }
            } elseif ($reader->nodeType === \XMLReader::END_ELEMENT) {
                $tag = strtolower($reader->localName);
                if ($tag === 'ele') {
                    $inEle = false;
                } elseif ($tag === 'name') {
                    $inName = false;
                } elseif (($tag === 'trkpt' || $tag === 'rtept') && $current !== null) {
                    $this->push($current, $points, $routePoints);
                    $current = null;
                }
            }
            if (count($points) + count($routePoints) > self::MAX_POINTS) {
                throw new RuntimeException('GPX com pontos demais.');
            }
        }
        $reader->close();
        libxml_clear_errors();

        $points = $points ?: $routePoints; // sem trilha gravada, usa a rota planejada
        if (count($points) < 2) {
            throw new RuntimeException('O GPX não tem um percurso (trilha ou rota) válido.');
        }

        $km = 0.0;
        for ($i = 1, $n = count($points); $i < $n; $i++) {
            $km += self::haversine($points[$i - 1], $points[$i]);
        }
        [$gain, $min, $max] = self::elevation($points);

        return ['points' => $points, 'km' => round($km, 2), 'gain' => $gain, 'min_ele' => $min, 'max_ele' => $max, 'name' => $name];
    }

    private function push(array $current, array &$points, array &$routePoints): void
    {
        if ($current['tag'] === 'trkpt') {
            $points[] = $current['p'];
        } else {
            $routePoints[] = $current['p'];
        }
    }

    /** Distância em km entre dois pontos [lat, lon]. */
    public static function haversine(array $a, array $b): float
    {
        $r = 6371.0088;
        $dLat = deg2rad($b[0] - $a[0]);
        $dLon = deg2rad($b[1] - $a[1]);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad($a[0])) * cos(deg2rad($b[0])) * sin($dLon / 2) ** 2;
        return 2 * $r * asin(min(1.0, sqrt($h)));
    }

    /** Subida acumulada ignorando variações menores que 3 m (ruído do GPS). */
    private static function elevation(array $points): array
    {
        $eles = array_values(array_filter(array_column($points, 2), static fn ($e) => $e !== null));
        if (count($eles) < 2) {
            return [0, null, null];
        }
        $gain = 0.0;
        $ref = $eles[0];
        foreach ($eles as $e) {
            if ($e - $ref >= 3) {
                $gain += $e - $ref;
                $ref = $e;
            } elseif ($ref - $e >= 3) {
                $ref = $e;
            }
        }
        return [(int) round($gain), (int) round(min($eles)), (int) round(max($eles))];
    }

    /** Reduz o traçado para exibir no mapa (Douglas-Peucker + limite de pontos). */
    public static function simplify(array $points, int $max = self::MAP_POINTS): array
    {
        $eps = 0.00002;
        $out = $points;
        while (count($out) > $max && $eps < 0.01) {
            $out = self::rdp($points, $eps);
            $eps *= 2;
        }
        if (count($out) > $max) {
            $step = (int) ceil(count($out) / $max);
            $out = array_values(array_filter($out, static fn ($k) => $k % $step === 0, ARRAY_FILTER_USE_KEY));
        }
        return array_map(static fn ($p) => [round($p[0], 5), round($p[1], 5), $p[2] === null ? null : (int) round($p[2])], $out);
    }

    private static function rdp(array $pts, float $eps): array
    {
        $n = count($pts);
        if ($n < 3) {
            return $pts;
        }
        $keep = array_fill(0, $n, false);
        $keep[0] = $keep[$n - 1] = true;
        $stack = [[0, $n - 1]];
        while ($stack) {
            [$s, $e] = array_pop($stack);
            $maxD = 0.0;
            $idx = -1;
            for ($i = $s + 1; $i < $e; $i++) {
                $d = self::perpDistance($pts[$i], $pts[$s], $pts[$e]);
                if ($d > $maxD) {
                    $maxD = $d;
                    $idx = $i;
                }
            }
            if ($maxD > $eps && $idx > 0) {
                $keep[$idx] = true;
                $stack[] = [$s, $idx];
                $stack[] = [$idx, $e];
            }
        }
        $out = [];
        foreach ($pts as $i => $p) {
            if ($keep[$i]) {
                $out[] = $p;
            }
        }
        return $out;
    }

    private static function perpDistance(array $p, array $a, array $b): float
    {
        $dx = $b[1] - $a[1];
        $dy = $b[0] - $a[0];
        if ($dx == 0.0 && $dy == 0.0) {
            return hypot($p[1] - $a[1], $p[0] - $a[0]);
        }
        return abs($dy * $p[1] - $dx * $p[0] + $b[1] * $a[0] - $b[0] * $a[1]) / hypot($dx, $dy);
    }

    /** Gera um GPX limpo a partir dos pontos (é este que os trilheiros baixam). */
    public static function build(array $points, string $name): string
    {
        $x = new \XMLWriter();
        $x->openMemory();
        $x->setIndent(false);
        $x->startDocument('1.0', 'UTF-8');
        $x->startElement('gpx');
        $x->writeAttribute('version', '1.1');
        $x->writeAttribute('creator', 'Ducks Offroad');
        $x->writeAttribute('xmlns', 'http://www.topografix.com/GPX/1/1');
        $x->startElement('trk');
        $x->writeElement('name', $name);
        $x->startElement('trkseg');
        foreach ($points as $p) {
            $x->startElement('trkpt');
            $x->writeAttribute('lat', (string) $p[0]);
            $x->writeAttribute('lon', (string) $p[1]);
            if ($p[2] !== null) {
                $x->writeElement('ele', (string) $p[2]);
            }
            $x->endElement();
        }
        $x->endElement();
        $x->endElement();
        $x->endElement();
        $x->endDocument();
        return $x->outputMemory();
    }

    /** Perfil de elevação em SVG (só números, sem texto do usuário). */
    public static function elevationSvg(array $points, float $km): string
    {
        $eles = array_column($points, 2);
        if (count(array_filter($eles, static fn ($e) => $e !== null)) < 2) {
            return '';
        }
        $w = 600;
        $h = 120;
        $valid = array_values(array_filter($eles, static fn ($e) => $e !== null));
        $min = min($valid);
        $max = max($valid);
        $range = max(1, $max - $min);
        $n = count($points);
        $coords = [];
        $last = $valid[0];
        foreach ($points as $i => $p) {
            $e = $p[2] ?? $last;
            $last = $e;
            $coords[] = round($i / max(1, $n - 1) * $w, 1) . ',' . round($h - (($e - $min) / $range) * ($h - 10) - 5, 1);
        }
        return '<svg class="ele-profile" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" role="img" aria-label="Perfil de elevação: de '
            . (int) $min . ' a ' . (int) $max . ' metros ao longo de ' . number_format($km, 1, ',', '') . ' km">'
            . '<polygon points="0,' . $h . ' ' . implode(' ', $coords) . ' ' . $w . ',' . $h . '" class="ele-fill"/>'
            . '<polyline points="' . implode(' ', $coords) . '" class="ele-line"/></svg>';
    }
}
