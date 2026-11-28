<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\AuditLog;
use App\Models\Candle;
use App\Models\CandleType;
use App\Models\MailQueue;
use App\Models\Setting;
use App\Models\User;
use App\Services\Backup;
use App\Services\Mailer;
use App\Services\Moderation;
use App\Services\Notifier;
use App\Services\Pix;
use App\Services\Scheduler;
use App\Services\Seeder;
use App\Services\SmtpTransport;

/**
 * Núcleo da aplicação: container de serviços + despacho de rotas com middlewares.
 */
final class App
{
    public readonly Database $db;
    public readonly Session $session;
    public readonly Csrf $csrf;
    public readonly Auth $auth;
    public readonly View $view;
    public readonly Logger $logger;
    public readonly SecurityHeaders $headers;
    public readonly RateLimiter $limiter;
    public readonly Request $request;

    private array $models = [];
    private ?Mailer $mailer = null;
    private ?Notifier $notifier = null;

    public function __construct(public readonly Config $config, public readonly string $basePath)
    {
        $this->logger = new Logger($basePath . '/storage/logs');
        $this->db = new Database($config->get('db'));
        if ($this->db->needsMigration($basePath . '/database')) {
            $applied = $this->db->migrate($basePath . '/database');
            if ($applied) {
                $this->logger->info('Migrações aplicadas: ' . implode(', ', $applied));
            }
        }
        $this->request = Request::capture();
        $this->headers = new SecurityHeaders();
        $this->session = new Session(
            $basePath . '/storage/sessions',
            (int) $config->get('security.session_idle_minutes', 120),
            $this->request->isHttps()
        );
        $this->csrf = new Csrf($this->session);
        $this->limiter = new RateLimiter($this->db);
        $this->auth = new Auth(
            $this->session,
            $this->model(User::class),
            $this->limiter,
            (int) $config->get('security.login_max_attempts', 5),
            (int) $config->get('security.login_lock_minutes', 15)
        );
        $this->view = new View($basePath . '/app/Views');
        (new Seeder($this->db, $this->settings()))->run();
    }

    /** @template T @param class-string<T> $class @return T */
    public function model(string $class): object
    {
        return $this->models[$class] ??= new $class($this->db);
    }

    public function settings(): Setting
    {
        return $this->model(Setting::class);
    }

    public function mailer(): Mailer
    {
        if ($this->mailer !== null) {
            return $this->mailer;
        }
        $smtp = null;
        if ($this->config->get('mail.smtp.host')) {
            $c = $this->config->get('mail.smtp');
            $smtp = new SmtpTransport($c['host'], (int) $c['port'], $c['encryption'], $c['user'], $c['pass'], (int) $c['timeout']);
        }
        return $this->mailer = new Mailer(
            (string) $this->config->get('mail.driver', 'log'),
            (string) $this->config->get('mail.from'),
            (string) $this->config->get('mail.from_name'),
            $this->logger,
            $smtp
        );
    }

    public function notifier(): Notifier
    {
        return $this->notifier ??= new Notifier(
            $this->model(MailQueue::class),
            $this->mailer(),
            $this->logger,
            (string) $this->config->get('app.url'),
            (string) $this->settings()->get('church_name')
        );
    }

    /** Pix da igreja: o que estiver em Admin > Configurações vale mais que o .env. */
    public function pix(): Pix
    {
        $s = $this->settings();
        return new Pix(
            (string) ($s->get('pix_key') ?: $this->config->get('pix.key', '')),
            (string) ($s->get('pix_name') ?: $this->config->get('pix.name', '') ?: $s->get('church_name')),
            (string) ($s->get('pix_city') ?: $this->config->get('pix.city', '') ?: 'BRASIL')
        );
    }

    public function moderation(): Moderation
    {
        return new Moderation($this->settings()->bannedWords());
    }

    public function backup(): Backup
    {
        $dir = (string) $this->config->get('backup.dir', 'storage/backups');
        return new Backup(
            $this->db,
            str_starts_with($dir, '/') ? $dir : $this->basePath . '/' . $dir,
            (int) $this->config->get('backup.keep_days', 30)
        );
    }

    public function audit(string $action, string $details = ''): void
    {
        $this->model(AuditLog::class)->record($this->auth->id(), $action, $details, $this->request->ip());
    }

    /** Cria o administrador inicial a partir do .env se ainda não houver nenhum. */
    public function ensureAdmin(): void
    {
        $email = (string) $this->config->get('admin.email', '');
        $password = (string) $this->config->get('admin.password', '');
        $users = $this->model(User::class);
        if ($email === '' || $password === '' || $users->countAdmins() > 0 || $users->emailExists($email)) {
            return;
        }
        $users->create((string) $this->config->get('admin.name', 'Administrador'), $email, $password, 'admin');
        $this->logger->info('Administrador inicial criado: ' . $email);
    }

    public function run(Router $router): void
    {
        $request = $this->request;
        try {
            $this->session->start($request->userAgent());
            $this->ensureAdmin();
            $this->shareViewGlobals();

            // POST maior que post_max_size chega sem corpo: responde 413 em vez de "CSRF inválido"
            if ($request->isPost() && $_POST === [] && $_FILES === [] && $request->contentLength() > 0) {
                throw new HttpException(413);
            }
            $route = $router->match($request->method(), $request->path());
            $request->setRouteParams($route['params']);

            if ($request->isPost()) {
                $this->csrf->validate($request);
            }
            foreach ($route['middleware'] as $mw) {
                $this->applyMiddleware($mw);
            }

            [$class, $method] = $route['handler'];
            $controller = new $class($this);
            $response = $controller->$method($request);
        } catch (RedirectException $e) {
            $response = Response::redirect($e->to);
        } catch (HttpException $e) {
            $response = $this->errorResponse($e->status(), $e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine(), 'uri' => $request->path()]);
            $message = $this->config->get('app.debug') ? $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() : '';
            $response = $this->errorResponse(500, $message);
        }

        $this->headers->send($request->isHttps());
        if ($this->auth->check() || $request->isPost()) {
            header('Cache-Control: no-store, private');
        }
        $response->send();
        $this->afterResponse();
    }

    /**
     * Reserva para hospedagens sem cron: depois de entregar a página (PHP-FPM), roda as
     * rotinas leves se o cron não roda há mais de 15 minutos (e as diárias, uma vez por dia).
     */
    private function afterResponse(): void
    {
        if (!function_exists('fastcgi_finish_request')) {
            return;
        }
        try {
            $settings = $this->settings();
            $cron = (int) strtotime((string) $settings->get('cron_last_run', ''));
            $light = (int) strtotime((string) $settings->get('light_run_at', ''));
            if (time() - $cron < 900 || time() - $light < 120) {
                return;
            }
            session_write_close();
            fastcgi_finish_request();
            $settings->set('light_run_at', date('Y-m-d H:i:s'));
            $scheduler = new Scheduler($this);
            $scheduler->dailyIsDue() ? $scheduler->runDaily() : $scheduler->runLight();
        } catch (\Throwable $e) {
            $this->logger->error('afterResponse: ' . $e->getMessage());
        }
    }

    private function applyMiddleware(string $name): void
    {
        switch ($name) {
            case 'guest':
                if ($this->auth->check()) {
                    throw new RedirectException('/admin');
                }
                break;
            case 'staff':
                if (!$this->auth->isStaff()) {
                    $this->session->flash('info', 'Entre para acessar o painel.');
                    throw new RedirectException('/admin/entrar');
                }
                break;
            case 'admin':
                if (!$this->auth->isAdmin()) {
                    $this->audit('acesso_negado', $this->request->path());
                    throw new HttpException(403);
                }
                break;
            default:
                throw new \LogicException('Middleware desconhecido: ' . $name);
        }
    }

    private function shareViewGlobals(): void
    {
        $s = $this->settings();
        $this->view->share('app', $this);
        $this->view->share('auth', $this->auth);
        $this->view->share('csrf', $this->csrf);
        $this->view->share('nonce', $this->headers->nonce());
        $this->view->share('siteName', (string) $this->config->get('app.name'));
        $this->view->share('currentPath', $this->request->path());
        $this->view->share('settings', $s);
        $this->view->share('church', [
            'name' => (string) $s->get('church_name'),
            'tagline' => (string) $s->get('church_tagline'),
            'address' => (string) $s->get('church_address'),
            'phone' => (string) $s->get('church_phone'),
            'whatsapp' => (string) $s->get('church_whatsapp'),
            'email' => (string) $s->get('church_email'),
            'instagram' => ltrim((string) $s->get('church_instagram'), '@'),
            'maps' => (string) $s->get('church_maps_url'),
        ]);
        // cores dos tipos de vela viram classes CSS (o CSP não permite style="" nas views)
        $css = '';
        foreach ($this->model(CandleType::class)->all() as $t) {
            if (preg_match('/^#[0-9a-fA-F]{6}$/', (string) $t['color'])) {
                $css .= '.ct-' . (int) $t['id'] . '{--c:' . $t['color'] . '}';
            }
        }
        $this->view->share('typeCss', $css);
    }

    private function errorResponse(int $status, string $message): Response
    {
        try {
            $this->shareViewGlobals();
            $html = $this->view->render('errors/error', [
                'status' => $status,
                'message' => $message ?: (new HttpException($status))->getMessage(),
                'title' => 'Erro ' . $status,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Falha ao renderizar erro: ' . $e->getMessage());
            $html = '<h1>Erro ' . $status . '</h1>';
        }
        return Response::html($html, $status);
    }
}
