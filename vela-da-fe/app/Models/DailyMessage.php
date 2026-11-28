<?php
declare(strict_types=1);

namespace App\Models;

/** "Palavra do dia": mensagem marcada para a data de hoje ou, na falta, uma do rodízio (muda a cada dia). */
final class DailyMessage extends Model
{
    protected string $table = 'daily_messages';

    public function forToday(): ?array
    {
        $today = date('Y-m-d');
        $fixed = $this->db->fetch('SELECT * FROM daily_messages WHERE active = 1 AND show_on = ? ORDER BY id DESC LIMIT 1', [$today]);
        if ($fixed !== null) {
            return $fixed;
        }
        $pool = $this->db->fetchAll('SELECT * FROM daily_messages WHERE active = 1 AND show_on IS NULL ORDER BY id');
        if ($pool === []) {
            return null;
        }
        return $pool[((int) date('z') + (int) date('Y')) % count($pool)];
    }
}
