<?php
declare(strict_types=1);

namespace App\Models;

/** Durações da vela (24 h, 7 dias...) e o valor de cada uma. */
final class CandlePlan extends Model
{
    protected string $table = 'candle_plans';

    public function active(): array
    {
        return $this->db->fetchAll('SELECT * FROM candle_plans WHERE active = 1 ORDER BY sort_order, hours, id');
    }

    public function all(): array
    {
        return $this->db->fetchAll('SELECT * FROM candle_plans ORDER BY sort_order, hours, id');
    }
}
