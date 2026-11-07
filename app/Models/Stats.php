<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Estatísticas dos trilheiros: ranking por ano, quilometragem e selos.
 * Conta as trilhas JÁ REALIZADAS em que o trilheiro confirmou presença
 * (se o admin marcou falta na ficha, não conta).
 */
final class Stats extends Model
{
    protected string $table = 'event_rsvps';

    private const DONE = "e.status = 'approved' AND COALESCE(e.end_at, e.start_at) < ? AND (r.attended IS NULL OR r.attended = 1)";

    public function ranking(?int $year, int $limit = 100): array
    {
        $params = [date('Y-m-d H:i:s')];
        $yearSql = '';
        if ($year !== null) {
            $yearSql = ' AND e.start_at >= ? AND e.start_at < ?';
            array_push($params, sprintf('%04d-01-01', $year), sprintf('%04d-01-01', $year + 1));
        }
        $params[] = $limit;
        return $this->db->fetchAll(
            'SELECT u.id, u.name, u.nickname, u.photo, u.bike_model, u.city,
                    COUNT(*) AS trails, ROUND(SUM(COALESCE(e.track_km, e.distance_km, 0))) AS km,
                    MAX(e.start_at) AS last_trail
             FROM event_rsvps r JOIN events e ON e.id = r.event_id JOIN users u ON u.id = r.user_id
             WHERE u.status = \'approved\' AND ' . self::DONE . $yearSql . '
             GROUP BY u.id, u.name, u.nickname, u.photo, u.bike_model, u.city
             ORDER BY trails DESC, km DESC, u.name ASC LIMIT ?',
            $params
        );
    }

    public function years(): array
    {
        $years = array_map('intval', array_column($this->db->fetchAll(
            'SELECT DISTINCT YEAR(e.start_at) AS y FROM event_rsvps r JOIN events e ON e.id = r.event_id
             WHERE ' . self::DONE . ' ORDER BY y DESC',
            [date('Y-m-d H:i:s')]
        ), 'y'));
        return $years ?: [(int) date('Y')];
    }

    /** @return array{trails:int, km:int, extreme:int, photos:int, first:?string} */
    public function forUser(int $userId): array
    {
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS trails, ROUND(SUM(COALESCE(e.track_km, e.distance_km, 0))) AS km,
                    SUM(e.level = 'extremo') AS extreme, MIN(e.start_at) AS first
             FROM event_rsvps r JOIN events e ON e.id = r.event_id
             WHERE r.user_id = ? AND " . self::DONE,
            [$userId, date('Y-m-d H:i:s')]
        ) ?? [];
        $photos = (int) $this->db->fetchValue("SELECT COUNT(*) FROM photos WHERE uploaded_by = ? AND status = 'approved'", [$userId]);
        return [
            'trails' => (int) ($row['trails'] ?? 0),
            'km' => (int) ($row['km'] ?? 0),
            'extreme' => (int) ($row['extreme'] ?? 0),
            'photos' => $photos,
            'first' => $row['first'] ?? null,
        ];
    }

    /** Selos conquistados. @return array<int, array{icon:string, name:string, desc:string}> */
    public static function badges(array $stats, ?string $memberSince): array
    {
        $b = [];
        $trailBadges = [1 => ['🐣', 'Primeira trilha', 'Completou a primeira trilha com a turma'],
            5 => ['🏍️', 'Pegou o jeito', '5 trilhas completadas'],
            10 => ['🔥', 'Dez trilhas', '10 trilhas completadas'],
            25 => ['🏆', 'Casca grossa', '25 trilhas completadas'],
            50 => ['👑', 'Lenda do barro', '50 trilhas completadas']];
        foreach ($trailBadges as $min => [$icon, $name, $desc]) {
            if ($stats['trails'] >= $min) {
                $b[] = compact('icon', 'name', 'desc');
            }
        }
        foreach ([500 => '🛣️', 1000 => '🌎', 5000 => '🚀'] as $km => $icon) {
            if ($stats['km'] >= $km) {
                $b[] = ['icon' => $icon, 'name' => number_format($km, 0, ',', '.') . ' km', 'desc' => 'Rodou ' . number_format($km, 0, ',', '.') . ' km nas trilhas da turma'];
            }
        }
        if ($stats['extreme'] > 0) {
            $b[] = ['icon' => '💀', 'name' => 'Sobreviveu ao extremo', 'desc' => 'Completou uma trilha de nível extremo'];
        }
        if ($stats['photos'] >= 10) {
            $b[] = ['icon' => '📸', 'name' => 'Fotógrafo da turma', 'desc' => '10+ fotos publicadas na galeria'];
        }
        if ($memberSince && strtotime($memberSince) < strtotime('-1 year')) {
            $b[] = ['icon' => '🦆', 'name' => 'Veterano', 'desc' => 'Mais de 1 ano na Ducks Offroad'];
        }
        return $b;
    }
}
