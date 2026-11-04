<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Gera arquivos .ics (RFC 5545) e links do Google Agenda para os eventos.
 */
final class Calendar
{
    public function __construct(private readonly string $siteUrl, private readonly string $siteName)
    {
    }

    public function ics(array $event): string
    {
        $start = strtotime((string) $event['start_at']);
        $end = $event['end_at'] ? strtotime((string) $event['end_at']) : $start + 4 * 3600;
        $url = $this->siteUrl . '/agenda/' . (int) $event['id'];
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//' . $this->escape($this->siteName) . '//Agenda//PT-BR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:evento-' . (int) $event['id'] . '@' . (parse_url($this->siteUrl, PHP_URL_HOST) ?: 'ducks'),
            'DTSTAMP:' . gmdate('Ymd\THis\Z'),
            'DTSTART:' . gmdate('Ymd\THis\Z', $start),
            'DTEND:' . gmdate('Ymd\THis\Z', $end),
            'SUMMARY:' . $this->escape((string) $event['title']),
            'DESCRIPTION:' . $this->escape(trim(($event['description'] ?? '') . "\n\n" . $url)),
            'LOCATION:' . $this->escape(trim(($event['location'] ?? '') . ' ' . ($event['city'] ?? ''))),
            'URL:' . $url,
            'END:VEVENT',
            'END:VCALENDAR',
        ];
        return implode("\r\n", array_map([$this, 'fold'], $lines)) . "\r\n";
    }

    public function googleLink(array $event): string
    {
        $start = strtotime((string) $event['start_at']);
        $end = $event['end_at'] ? strtotime((string) $event['end_at']) : $start + 4 * 3600;
        return 'https://calendar.google.com/calendar/render?' . http_build_query([
            'action' => 'TEMPLATE',
            'text' => $event['title'],
            'dates' => gmdate('Ymd\THis\Z', $start) . '/' . gmdate('Ymd\THis\Z', $end),
            'details' => excerpt((string) ($event['description'] ?? ''), 500) . "\n" . $this->siteUrl . '/agenda/' . (int) $event['id'],
            'location' => trim(($event['location'] ?? '') . ' ' . ($event['city'] ?? '')),
        ]);
    }

    public static function mapsLink(array $event): ?string
    {
        $q = trim(($event['location'] ?? '') . ' ' . ($event['city'] ?? ''));
        return $q === '' ? null : 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($q);
    }

    private function escape(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\;', '\,', '\n'], $text);
    }

    private function fold(string $line): string
    {
        $out = '';
        while (strlen($line) > 74) {
            $cut = 74;
            // não corta caractere UTF-8 no meio
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
                $cut--;
            }
            $out .= substr($line, 0, $cut) . "\r\n ";
            $line = substr($line, $cut);
        }
        return $out . $line;
    }
}
