<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Triagem automática dos textos públicos (nome, "por quem", intenção).
 * Segura para análise (não bloqueia a vela, só esconde o texto até a equipe aprovar) quando houver:
 * links, e-mails, telefones, palavras bloqueadas pela igreja ou spam evidente.
 */
final class Moderation
{
    /** @param string[] $bannedWords */
    public function __construct(private readonly array $bannedWords = [])
    {
    }

    /** @return string|null motivo, ou null se o texto parece normal */
    public function flag(string ...$texts): ?string
    {
        $text = trim(implode(' ', $texts));
        if ($text === '') {
            return null;
        }
        if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $text)) {
            return 'contém e-mail';
        }
        if (preg_match('#(https?://|www\.|\b[a-z0-9-]+\.(com|net|org|br|io|info|xyz|ru|top|club|site|online)\b)#i', $text)) {
            return 'contém link';
        }
        if (preg_match('/(?:\d[\s().-]*){9,}/', $text)) {
            return 'contém telefone/número longo';
        }
        if (preg_match('/(.)\1{9,}/u', $text)) {
            return 'texto repetitivo';
        }
        $normalized = self::normalize($text);
        foreach ($this->bannedWords as $word) {
            $w = self::normalize($word);
            if ($w !== '' && preg_match('/(?<![a-z0-9])' . preg_quote($w, '/') . '/', $normalized)) {
                return 'palavra bloqueada';
            }
        }
        return null;
    }

    /** minúsculas, sem acentos, sem símbolos (pega "p0rr@" -> "porra"... em parte). */
    public static function normalize(string $text): string
    {
        $t = mb_strtolower($text);
        $t = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t) ?: $t;
        $t = strtr($t, ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '@' => 'a', '$' => 's']);
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $t));
    }
}
