<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Models\AuditLog;
use App\Models\Candle;
use App\Models\MailQueue;
use App\Models\Setting;

/**
 * Rotinas automáticas. O ideal é rodar `php bin/cron.php` a cada 5 minutos (cron do servidor).
 * Sem cron, o App roda runLight() depois de entregar a página (se o servidor usar PHP-FPM).
 */
final class Scheduler
{
    public function __construct(private readonly App $app)
    {
    }

    /** Rotina leve: fila de e-mails + avisos de vela. */
    public function runLight(): array
    {
        $candles = $this->app->model(Candle::class);
        $notifier = $this->app->notifier();
        $report = ['reminders' => 0, 'expired' => 0, 'mails' => 0];

        foreach ($candles->dueReminders() as $c) {
            $candles->markReminderSent((int) $c['id']);
            $notifier->reminder($c, $candles->rotateToken((int) $c['id']));
            $report['reminders']++;
        }
        foreach ($candles->dueExpiredNotices() as $c) {
            $candles->markExpiredNoticeSent((int) $c['id']);
            $notifier->expired($c, $candles->rotateToken((int) $c['id']));
            $report['expired']++;
        }
        $report['mails'] = $notifier->flush(30);
        $this->app->model(Setting::class)->set('cron_last_run', date('Y-m-d H:i:s'));
        return $report;
    }

    /** Rotina completa (uma vez por dia basta; é seguro rodar a cada execução do cron). */
    public function runDaily(): array
    {
        $report = $this->runLight();
        $candles = $this->app->model(Candle::class);
        $report['cancelled'] = $candles->cancelStalePending(72);
        $report['anonymized'] = $candles->anonymizeOld(180);
        $candles->purgeOldPrayers();
        $this->app->limiter->purgeExpired();
        $this->app->model(MailQueue::class)->purgeOld(60);
        $this->app->model(AuditLog::class)->purgeOld(365);
        $settings = $this->app->model(Setting::class);
        $settings->set('daily_last_run', date('Y-m-d H:i:s'));
        return $report;
    }

    public function dailyIsDue(): bool
    {
        $last = (int) strtotime((string) $this->app->model(Setting::class)->get('daily_last_run', ''));
        return time() - $last > 20 * 3600;
    }
}
