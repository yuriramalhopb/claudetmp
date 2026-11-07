<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Core\RateLimiter;
use App\Models\MailQueue;
use App\Models\PushSubscription;
use App\Models\Setting;

/**
 * Tarefas periódicas. Executadas por bin/cron.php (recomendado) e, como
 * reserva, em pequenas doses após responder visitas quando o cron está parado.
 */
final class Scheduler
{
    /** @var callable */
    private $log;

    public function __construct(private readonly App $app, ?callable $log = null)
    {
        $this->log = $log ?? static fn (string $m) => null;
    }

    public function runAll(): void
    {
        foreach (['announce', 'reminders', 'push', 'emails', 'instagram', 'backup', 'cleanup'] as $task) {
            $this->safely($task, fn () => $this->$task());
        }
        $this->app->model(Setting::class)->set('cron_last_run', date('Y-m-d H:i:s'));
    }

    /** Versão leve (sem backup/Instagram) usada quando o cron não está configurado. */
    public function runLight(): void
    {
        foreach (['announce', 'reminders', 'push', 'emails'] as $task) {
            $this->safely($task, fn () => $this->$task(20));
        }
    }

    private function safely(string $name, callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            ($this->log)("ERRO em {$name}: " . $e->getMessage());
            $this->app->logger->error("agendador {$name}: " . $e->getMessage());
        }
    }

    /** Trilha nova publicada -> avisa a turma (uma única vez por evento). */
    public function announce(int $limit = 5): void
    {
        $db = $this->app->db;
        $now = date('Y-m-d H:i:s');
        $events = $db->fetchAll("SELECT * FROM events WHERE status = 'approved' AND announced_at IS NULL AND start_at >= ? ORDER BY id LIMIT ?", [$now, $limit]);
        foreach ($events as $ev) {
            if ($db->execute('UPDATE events SET announced_at = ? WHERE id = ? AND announced_at IS NULL', [$now, (int) $ev['id']]) === 0) {
                continue; // outro processo já anunciou
            }
            $n = $this->app->notifier()->newEvent($ev);
            ($this->log)("trilha nova #{$ev['id']} anunciada para {$n} e-mail(s)");
        }
        $db->execute('UPDATE events SET announced_at = ? WHERE announced_at IS NULL AND start_at < ?', [$now, $now]);
    }

    /** Lembrete na véspera para quem confirmou presença (a partir das 9h). */
    public function reminders(int $limit = 5): void
    {
        if ((int) date('G') < 9) {
            return;
        }
        $db = $this->app->db;
        $events = $db->fetchAll(
            "SELECT * FROM events WHERE status = 'approved' AND reminder_sent_at IS NULL AND start_at >= ? AND start_at < ? LIMIT ?",
            [date('Y-m-d', strtotime('+1 day')) . ' 00:00:00', date('Y-m-d', strtotime('+2 days')) . ' 00:00:00', $limit]
        );
        foreach ($events as $ev) {
            if ($db->execute('UPDATE events SET reminder_sent_at = ? WHERE id = ? AND reminder_sent_at IS NULL', [date('Y-m-d H:i:s'), (int) $ev['id']]) === 0) {
                continue;
            }
            $attendees = $db->fetchAll(
                "SELECT u.id, u.name, u.email, u.notify_email FROM event_rsvps r JOIN users u ON u.id = r.user_id
                 WHERE r.event_id = ? AND u.status = 'approved'",
                [(int) $ev['id']]
            );
            $this->app->notifier()->eventReminder($ev, $attendees);
            ($this->log)('lembrete da trilha #' . $ev['id'] . ' para ' . count($attendees) . ' trilheiro(s)');
        }
    }

    public function push(int $limit = 10): void
    {
        $s = $this->app->model(PushSubscription::class)->dispatchPending($this->app->webPush(), $limit);
        if ($s['notifications'] > 0) {
            ($this->log)("push: {$s['notifications']} notificação(ões), {$s['sent']} enviada(s), {$s['removed']} aparelho(s) removido(s), {$s['failed']} falha(s)");
        }
    }

    public function emails(int $limit = 100): void
    {
        $s = $this->app->model(MailQueue::class)->process($this->app->mailer(), $limit);
        if ($s['sent'] + $s['failed'] > 0) {
            ($this->log)("e-mails: {$s['sent']} enviado(s), {$s['failed']} falha(s)");
        }
    }

    public function instagram(): void
    {
        $this->app->instagram()->syncIfDue();
    }

    /** Backup diário no horário BACKUP_HOUR. */
    public function backup(): void
    {
        $settings = $this->app->model(Setting::class);
        $last = (int) strtotime((string) $settings->get('backup_last_run', ''));
        if ((int) date('G') !== (int) $this->app->config->get('backup.hour', 3) || time() - $last < 20 * 3600) {
            return;
        }
        $settings->set('backup_last_run', date('Y-m-d H:i:s'));
        $r = $this->app->backup()->run();
        $settings->set('backup_last_ok', date('Y-m-d H:i:s'));
        $settings->set('backup_last_size', (string) $r['size']);
        ($this->log)('backup criado: ' . basename($r['db']) . ' (' . round($r['size'] / 1048576, 1) . ' MB)');
    }

    public function cleanup(): void
    {
        $db = $this->app->db;
        (new RateLimiter($db))->purgeExpired();
        $this->app->model(MailQueue::class)->purgeOld(30);
        $db->execute('DELETE FROM notifications WHERE pushed_at IS NOT NULL AND pushed_at < ?', [date('Y-m-d H:i:s', time() - 30 * 86400)]);
        $db->execute('DELETE FROM password_resets WHERE expires_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    }
}
