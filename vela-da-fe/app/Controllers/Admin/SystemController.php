<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;
use App\Models\MailQueue;
use App\Services\Scheduler;

/** Saúde do sistema, rotinas, e-mail de teste, backups e auditoria (só administradores). */
final class SystemController extends Controller
{
    public function index(Request $request): Response
    {
        $s = $this->app->settings();
        return $this->adminView('system', [
            'title' => 'Sistema',
            'info' => [
                'PHP' => PHP_VERSION,
                'Banco de dados' => $this->db->isSqlite() ? 'SQLite (arquivo)' : 'MySQL/MariaDB',
                'E-mail (driver)' => $this->app->mailer()->driver(),
                'Última rotina automática' => $s->get('cron_last_run') ? datetime_short((string) $s->get('cron_last_run')) : 'nunca (configure o cron — veja o README)',
                'Última limpeza diária' => $s->get('daily_last_run') ? datetime_short((string) $s->get('daily_last_run')) : 'nunca',
                'Chave Pix' => $this->app->pix()->isConfigured() ? 'configurada' : 'não configurada (só velas gratuitas)',
                'Site (APP_URL)' => (string) $this->app->config->get('app.url'),
            ],
            'mail' => $this->app->model(MailQueue::class)->stats(),
            'backups' => $this->app->backup()->list(),
            'httpsOk' => $this->app->request->isHttps(),
        ]);
    }

    public function runCron(Request $request): Response
    {
        $r = (new Scheduler($this->app))->runDaily();
        $this->app->audit('rotinas_executadas');
        $this->flash('success', sprintf('Rotinas executadas: %d e-mail(s) enviado(s), %d lembrete(s), %d aviso(s) de vela apagada, %d pedido(s) vencido(s) cancelado(s).', $r['mails'], $r['reminders'], $r['expired'], $r['cancelled']));
        return $this->redirect('/admin/sistema');
    }

    public function testMail(Request $request): Response
    {
        $me = $this->auth->user();
        $this->app->model(MailQueue::class)->enqueue($me['email'], 'Teste de e-mail', "Se você recebeu esta mensagem, o envio de e-mails está funcionando.\n\n" . $this->app->settings()->get('church_name'));
        $sent = $this->app->notifier()->flush(5);
        $driver = $this->app->mailer()->driver();
        $this->flash($driver === 'log' ? 'warning' : 'success', $driver === 'log'
            ? 'O driver de e-mail está em "log": a mensagem foi gravada em storage/logs/mail-*.log e não enviada. Configure MAIL_DRIVER=smtp no .env.'
            : "E-mail de teste enviado para {$me['email']} ({$sent} na fila processada). Confira a caixa de entrada.");
        return $this->redirect('/admin/sistema');
    }

    public function backup(Request $request): Response
    {
        try {
            $r = $this->app->backup()->run();
            $this->app->audit('backup', basename($r['file']));
            $this->flash('success', 'Backup criado: ' . basename($r['file']) . ' (' . round($r['size'] / 1024) . ' KB).');
        } catch (\Throwable $e) {
            $this->app->logger->error('Backup: ' . $e->getMessage());
            $this->flash('error', 'Não foi possível criar o backup: ' . $e->getMessage());
        }
        return $this->redirect('/admin/sistema');
    }

    public function downloadBackup(Request $request): Response
    {
        $path = $this->app->backup()->path((string) $request->param('file'));
        if ($path === null) {
            throw new HttpException(404);
        }
        $this->app->audit('backup_baixado', basename($path));
        return Response::download((string) file_get_contents($path), basename($path), 'application/gzip');
    }

    public function audit(Request $request): Response
    {
        $result = $this->app->model(AuditLog::class)->paginate($this->pageParam($request), 50);
        return $this->adminView('audit', $result + ['title' => 'Registro de atividades', 'base' => '/admin/auditoria', 'params' => []]);
    }
}
