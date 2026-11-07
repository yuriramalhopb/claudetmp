<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\MailQueue;
use App\Models\PushSubscription;
use App\Models\Setting;

/**
 * Saúde do sistema: cron, e-mails, notificações e backups.
 */
final class SystemController extends Controller
{
    public function index(Request $request): Response
    {
        $settings = $this->app->model(Setting::class);
        $cronLast = $settings->get('cron_last_run');
        return $this->adminView('system', [
            'title' => 'Sistema',
            'cronLast' => $cronLast,
            'cronOk' => $cronLast !== null && time() - (int) strtotime($cronLast) < 20 * 60,
            'mailDriver' => $this->app->mailer()->driver(),
            'mailStats' => $this->app->model(MailQueue::class)->stats(),
            'pushReady' => $this->app->webPush()->isConfigured(),
            'pushDevices' => $this->app->model(PushSubscription::class)->count(),
            'myDevices' => $this->app->model(PushSubscription::class)->countForUser((int) $this->auth->id()),
            'backups' => $this->app->backup()->list(),
            'backupLast' => $settings->get('backup_last_ok'),
            'backupHour' => (int) $this->app->config->get('backup.hour', 3),
            'basePath' => $this->app->basePath,
        ]);
    }

    public function testEmail(Request $request): Response
    {
        $this->throttle('test-email', 5, 600);
        $user = $this->auth->user();
        try {
            $this->app->mailer()->send($user['email'], 'Teste de e-mail - Ducks Offroad', "Olá!\n\nSe você recebeu este e-mail, o envio está funcionando. 🦆");
            $this->flash('success', 'E-mail de teste enviado para ' . $user['email'] . ' (driver: ' . $this->app->mailer()->driver() . ').');
        } catch (\Throwable $e) {
            $this->flash('error', 'Falha no envio: ' . $e->getMessage());
        }
        return $this->redirect('/admin/sistema');
    }

    public function backupNow(Request $request): Response
    {
        $this->throttle('backup-now', 3, 3600);
        @set_time_limit(300);
        try {
            $r = $this->app->backup()->run();
            $this->app->model(Setting::class)->set('backup_last_ok', date('Y-m-d H:i:s'));
            $this->app->audit('backup_manual', basename($r['db']));
            $this->flash('success', 'Backup criado: ' . basename($r['db']) . ' (' . round($r['size'] / 1048576, 1) . ' MB).');
        } catch (\Throwable $e) {
            $this->app->logger->error('Backup manual: ' . $e->getMessage());
            $this->flash('error', 'Falha no backup: ' . $e->getMessage());
        }
        return $this->redirect('/admin/sistema');
    }
}
