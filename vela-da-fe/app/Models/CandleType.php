<?php
declare(strict_types=1);

namespace App\Models;

/** Tipos de vela (cor, significado, valor extra). Cadastrados pelo painel. */
final class CandleType extends Model
{
    protected string $table = 'candle_types';

    public function active(): array
    {
        return $this->db->fetchAll('SELECT * FROM candle_types WHERE active = 1 ORDER BY sort_order, id');
    }

    public function all(): array
    {
        return $this->db->fetchAll('SELECT * FROM candle_types ORDER BY sort_order, id');
    }
}
