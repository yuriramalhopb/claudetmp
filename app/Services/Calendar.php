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

    /** Gera um calendário .ics com um ou vários eventos. */
    public function ics(array $events): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//' . $this->escape($this->siteName) . '//Agenda//PT-BR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $this->escape($this->siteName . ' - Trilhas'),
            'X-WR-TIMEZONE:' . date_default_timezone_get(),
        ];
        foreach ($events as $event) {
            array_push($lines, ...$this->vevent($event));
        }
        $lines[] = 'END:VCALENDAR';
        return implode("\r\n", array_map([$this, 'fold'], $lines)) . "\r\n";
    }

    private function vevent(array $event): array
    {
        $start = strtotime((string) $event['start_at']);
        $end = $event['end_at'] ? strtotime((string) $event['end_at']) : $start + 4 * 3600;
        $url = $this->siteUrl . '/agenda/' . (int) $event['id'];
        $details = trim(implode("\n", array_filter([
            $event['description'] ?? '',
            !empty($event['price']) ? 'Valor: ' . $event['price'] : '',
            !empty($event['instagram_url']) ? 'Instagram: ' . $event['instagram_url'] : '',
            $url,
        ])));
        return [
            'BEGIN:VEVENT',
            'UID:evento-' . (int) $event['id'] . '@' . (parse_url($this->siteUrl, PHP_URL_HOST) ?: 'ducks'),
            'DTSTAMP:' . gmdate('Ymd\THis\Z'),
            'DTSTART:' . gmdate('Ymd\THis\Z', $start),
            'DTEND:' . gmdate('Ymd\THis\Z', $end),
            'SUMMARY:' . $this->escape((string) $event['title']),
            'DESCRIPTION:' . $this->escape($details),
            'LOCATION:' . $this->escape(self::place($event)),
            'URL:' . $url,
            'END:VEVENT',
        ];
    }

    /** "Local — Cidade/UF" */
    public static function place(array $event): string
    {
        $city = trim(($event['city'] ?? '') . (!empty($event['state']) ? '/' . $event['state'] : ''));
        return trim(($event['location'] ?? '') !== '' ? $event['location'] . ' — ' . $city : $city, ' —');
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
            'location' => self::place($event),
        ]);
    }

    public static function mapsLink(array $event): ?string
    {
        $q = trim(($event['location'] ?? '') . ' ' . ($event['city'] ?? '') . ' ' . ($event['state'] ?? ''));
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
