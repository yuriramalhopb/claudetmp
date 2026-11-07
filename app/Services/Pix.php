<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

/**
 * Gera o "Pix Copia e Cola"/QR Code estático (BR Code, padrão EMV do Banco Central)
 * com chave, valor e identificador da cobrança. Não depende de banco/intermediário:
 * o dinheiro cai direto na conta da chave Pix da turma.
 */
final class Pix
{
    public function __construct(
        private readonly string $key,
        private readonly string $name,
        private readonly string $city,
    ) {
    }

    public function isConfigured(): bool
    {
        return self::validKey($this->key);
    }

    public function key(): string
    {
        return $this->key;
    }

    public static function validKey(string $key): bool
    {
        return (bool) preg_match('/^(\d{11}|\d{14}|\+55\d{10,11}|[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})$/i', $key)
            || (filter_var($key, FILTER_VALIDATE_EMAIL) && strlen($key) <= 77);
    }

    /** @param string $txid identificador (até 25 letras/números), aparece no extrato */
    public function payload(float $amount, string $txid, string $description = ''): string
    {
        if (!$this->isConfigured()) {
            throw new InvalidArgumentException('Chave Pix não configurada (PIX_KEY).');
        }
        if ($amount <= 0 || $amount > 1000000) {
            throw new InvalidArgumentException('Valor inválido.');
        }
        $txid = substr((string) preg_replace('/[^A-Za-z0-9]/', '', $txid), 0, 25) ?: '***';
        $account = self::field('00', 'br.gov.bcb.pix') . self::field('01', $this->key);
        $description = self::ascii($description, 40);
        if ($description !== '' && strlen($account) + strlen($description) + 4 <= 99) {
            $account .= self::field('02', $description);
        }
        $payload = self::field('00', '01')
            . self::field('26', $account)
            . self::field('52', '0000')
            . self::field('53', '986')
            . self::field('54', number_format($amount, 2, '.', ''))
            . self::field('58', 'BR')
            . self::field('59', self::ascii($this->name, 25) ?: 'DUCKS OFFROAD')
            . self::field('60', self::ascii($this->city, 15) ?: 'BRASIL')
            . self::field('62', self::field('05', $txid))
            . '6304';
        return $payload . self::crc16($payload);
    }

    private static function field(string $id, string $value): string
    {
        $len = strlen($value);
        if ($len > 99) {
            throw new InvalidArgumentException("Campo {$id} do Pix muito longo.");
        }
        return $id . str_pad((string) $len, 2, '0', STR_PAD_LEFT) . $value;
    }

    /** Remove acentos e caracteres fora do padrão (nome/cidade do recebedor). */
    private static function ascii(string $text, int $max): string
    {
        $t = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: '';
        $t = strtoupper((string) preg_replace('/[^A-Za-z0-9 ]/', '', $t));
        return substr(trim((string) preg_replace('/\s+/', ' ', $t)), 0, $max);
    }

    /** CRC16-CCITT (polinômio 0x1021, inicial 0xFFFF), exigido pelo BR Code. */
    public static function crc16(string $data): string
    {
        $crc = 0xFFFF;
        for ($i = 0, $n = strlen($data); $i < $n; $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($b = 0; $b < 8; $b++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) & 0xFFFF : ($crc << 1) & 0xFFFF;
            }
        }
        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
