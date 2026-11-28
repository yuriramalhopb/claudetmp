<?php
declare(strict_types=1);

namespace App\Models;

/** Horários de missas e celebrações. */
final class MassTime extends Model
{
    protected string $table = 'mass_times';

    /** @return array<int, array> dia da semana (0 = domingo) => horários, só dias que têm algo */
    public function byWeekday(): array
    {
        $out = [];
        foreach ($this->db->fetchAll('SELECT * FROM mass_times WHERE active = 1 ORDER BY weekday, time, sort_order') as $row) {
            $out[(int) $row['weekday']][] = $row;
        }
        ksort($out);
        return $out;
    }
}
