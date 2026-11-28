<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Números do painel. */
final class Stats
{
    public function __construct(private readonly Database $db)
    {
    }

    public function dashboard(): array
    {
        $now = date('Y-m-d H:i:s');
        $monthStart = date('Y-m-01 00:00:00');
        $one = fn (string $sql, array $p = []) => (int) $this->db->fetchValue($sql, $p);
        return [
            'lit_now' => $one("SELECT COUNT(*) FROM candles WHERE status = 'active' AND expires_at > ?", [$now]),
            'lit_total' => $one("SELECT COUNT(*) FROM candles WHERE status = 'active'"),
            'lit_today' => $one("SELECT COUNT(*) FROM candles WHERE status = 'active' AND lit_at >= ?", [date('Y-m-d 00:00:00')]),
            'lit_month' => $one("SELECT COUNT(*) FROM candles WHERE status = 'active' AND lit_at >= ?", [$monthStart]),
            'prayers' => $one("SELECT COALESCE(SUM(prayer_count), 0) FROM candles WHERE status = 'active'"),
            'pending_payment' => $one("SELECT COUNT(*) FROM candles WHERE status = 'pending_payment'"),
            'pending_moderation' => $one("SELECT COUNT(*) FROM candles WHERE mod_status = 'pending' AND status IN ('active', 'pending_payment')"),
            'pending_testimonials' => $one("SELECT COUNT(*) FROM testimonials WHERE status = 'pending'"),
            'mass_pending' => $one("SELECT COUNT(*) FROM candles WHERE mass_intention = 1 AND status = 'active' AND mod_status <> 'rejected' AND mass_done_at IS NULL"),
            'revenue_total' => $one("SELECT COALESCE(SUM(amount), 0) FROM candles WHERE paid_at IS NOT NULL AND status = 'active'"),
            'revenue_month' => $one("SELECT COALESCE(SUM(amount), 0) FROM candles WHERE paid_at IS NOT NULL AND status = 'active' AND paid_at >= ?", [$monthStart]),
        ];
    }

    /** Contadores do menu do painel (consultas leves). */
    public function badges(): array
    {
        return [
            'payments' => (int) $this->db->fetchValue("SELECT COUNT(*) FROM candles WHERE status = 'pending_payment'"),
            'moderation' => (int) $this->db->fetchValue("SELECT COUNT(*) FROM candles WHERE mod_status = 'pending' AND status IN ('active', 'pending_payment')"),
            'testimonials' => (int) $this->db->fetchValue("SELECT COUNT(*) FROM testimonials WHERE status = 'pending'"),
            'mass' => (int) $this->db->fetchValue("SELECT COUNT(*) FROM candles WHERE mass_intention = 1 AND status = 'active' AND mod_status <> 'rejected' AND mass_done_at IS NULL"),
        ];
    }

    /** Velas acesas por dia nos últimos $days dias: [['day' => 'Y-m-d', 'n' => int], ...] sem buracos. */
    public function perDay(int $days = 14): array
    {
        $since = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));
        $rows = $this->db->fetchAll(
            "SELECT DATE(lit_at) AS d, COUNT(*) AS n FROM candles WHERE status = 'active' AND lit_at >= ? GROUP BY DATE(lit_at)",
            [$since]
        );
        $map = array_column($rows, 'n', 'd');
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $out[] = ['day' => $d, 'n' => (int) ($map[$d] ?? 0)];
        }
        return $out;
    }

    /** @return array<int, array{category:string, n:int}> */
    public function byCategory(): array
    {
        return $this->db->fetchAll("SELECT category, COUNT(*) AS n FROM candles WHERE status = 'active' GROUP BY category ORDER BY n DESC");
    }

    /** @return array<int, array{name:string, color:string, n:int}> */
    public function byType(): array
    {
        return $this->db->fetchAll(
            "SELECT t.name, t.color, COUNT(c.id) AS n FROM candle_types t LEFT JOIN candles c ON c.type_id = t.id AND c.status = 'active'
             GROUP BY t.id, t.name, t.color, t.sort_order ORDER BY n DESC, t.sort_order"
        );
    }

    /** Receita (Pix confirmado) de $from a $to (datas Y-m-d). */
    public function revenue(string $from, string $to): array
    {
        $end = date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00';
        return $this->db->fetchAll(
            "SELECT DATE(paid_at) AS d, COUNT(*) AS n, SUM(amount) AS total FROM candles
             WHERE paid_at IS NOT NULL AND status = 'active' AND paid_at >= ? AND paid_at < ? GROUP BY DATE(paid_at) ORDER BY d",
            [$from . ' 00:00:00', $end]
        );
    }
}
