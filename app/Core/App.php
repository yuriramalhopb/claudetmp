<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\AuditLog;
use App\Models\InstagramPost;
use App\Models\Setting;
use App\Models\User;
use App\Services\ImageUploader;
use App\Services\InstagramService;
use App\Models\MailQueue;
use App\Services\Backup;
use App\Services\Mailer;
use App\Services\Notifier;
use App\Services\SmtpTransport;
use App\Services\WebPush;

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
    private ?InstagramService $instagram = null;
    private ?ImageUploader $uploader = null;
    private ?Mailer $mailer = null;
    private ?Notifier $notifier = null;
    private ?Crypto $crypto = null;
    private ?WebPush $webPush = null;

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
    }

    /** @template T @param class-string<T> $class @return T */
    public function model(string $class): object
    {
        return $this->models[$class] ??= new $class($this->db);
    }

    public function uploader(): ImageUploader
    {
        return $this->uploader ??= new ImageUploader(
            $this->basePath . '/public/uploads',
            (int) $this->config->get('security.upload_max_mb', 8) * 1024 * 1024
        );
    }

    public function instagram(): InstagramService
    {
        return $this->instagram ??= new InstagramService(
            $this->model(InstagramPost::class),
            $this->model(Setting::class),
            $this->uploader(),
            $this->logger,
            (string) $this->config->get('instagram.token', ''),
            (int) $this->config->get('instagram.sync_minutes', 60)
        );
    }

    public function mailer(): Mailer
    {
        $smtp = null;
        if ($this->config->get('mail.smtp.host')) {
            $c = $this->config->get('mail.smtp');
            $smtp = new SmtpTransport($c['host'], (int) $c['port'], $c['encryption'], $c['user'], $c['pass'], (int) $c['timeout']);
        }
        return $this->mailer ??= new Mailer(
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
            $this->db,
            $this->model(MailQueue::class),
            $this->mailer(),
            (string) $this->config->get('app.url'),
            (string) $this->config->get('app.name')
        );
    }

    public function webPush(): WebPush
    {
        return $this->webPush ??= new WebPush(
            (string) $this->config->get('push.private_key', ''),
            (string) $this->config->get('push.subject', '')
        );
    }

    public function backup(): Backup
    {
        $dir = (string) $this->config->get('backup.dir', 'storage/backups');
        return new Backup(
            $this->db,
            str_starts_with($dir, '/') ? $dir : $this->basePath . '/' . $dir,
            $this->basePath . '/public/uploads',
            (int) $this->config->get('backup.keep_days', 30)
        );
    }

    public function crypto(): Crypto
    {
        return $this->crypto ??= new Crypto((string) $this->config->get('app.key', ''));
    }

    public function audit(string $action, string $details = ''): void
    {
        $this->model(AuditLog::class)->record($this->auth->id(), $action, $details, $this->request->ip());
    }

    /** Cria o admin inicial a partir do .env se não houver nenhum. */
    public function ensureAdmin(): void
    {
        $email = (string) $this->config->get('admin.email', '');
        $password = (string) $this->config->get('admin.password', '');
        $users = $this->model(User::class);
        if ($email === '' || $password === '' || $users->countAdmins() > 0 || $users->emailExists($email)) {
            return;
        }
        $users->create(['name' => $this->config->get('admin.name', 'Administrador'), 'email' => $email], $password, 'approved', 'admin');
        $this->logger->info('Admin inicial criado: ' . $email);
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
     * Reserva para hospedagens sem cron: depois de entregar a página (PHP-FPM),
     * processa um pouco da fila se o cron não roda há mais de 15 minutos.
     */
    private function afterResponse(): void
    {
        if (!function_exists('fastcgi_finish_request')) {
            return;
        }
        try {
            $settings = $this->model(Setting::class);
            $cron = (int) strtotime((string) $settings->get('cron_last_run', ''));
            $light = (int) strtotime((string) $settings->get('light_run_at', ''));
            if (time() - $cron < 900 || time() - $light < 120) {
                return;
            }
            session_write_close();
            fastcgi_finish_request();
            $settings->set('light_run_at', date('Y-m-d H:i:s'));
            (new \App\Services\Scheduler($this))->runLight();
        } catch (\Throwable $e) {
            $this->logger->error('afterResponse: ' . $e->getMessage());
        }
    }

    private function applyMiddleware(string $name): void
    {
        switch ($name) {
            case 'guest':
                if ($this->auth->check()) {
                    throw new RedirectException('/minha-conta');
                }
                break;
            case 'auth':
                if (!$this->auth->check()) {
                    $this->session->set('intended', $this->request->path());
                    $this->session->flash('info', 'Entre na sua conta para continuar.');
                    throw new RedirectException('/entrar');
                }
                break;
            case 'approved':
                if (!$this->auth->isApproved()) {
                    $this->session->flash('warning', 'Seu cadastro ainda está aguardando aprovação da administração.');
                    throw new RedirectException('/minha-conta');
                }
                break;
            case 'admin':
                if (!$this->auth->isAdmin()) {
                    $this->audit('acesso_negado_admin', $this->request->path());
                    throw new HttpException(403);
                }
                // o painel exige verificação em duas etapas ativada
                if (!$this->auth->hasTwoFactor()) {
                    $this->session->flash('warning', 'Por segurança, ative a verificação em duas etapas para acessar o painel.');
                    throw new RedirectException('/minha-conta/2fa');
                }
                break;
            default:
                throw new \LogicException('Middleware desconhecido: ' . $name);
        }
    }

    private function shareViewGlobals(): void
    {
        $this->view->share('app', $this);
        $this->view->share('auth', $this->auth);
        $this->view->share('csrf', $this->csrf);
        $this->view->share('nonce', $this->headers->nonce());
        $this->view->share('siteName', (string) $this->config->get('app.name'));
        $this->view->share('igUsername', (string) $this->config->get('instagram.username'));
        $this->view->share('currentPath', $this->request->path());
        try {
            $this->view->share('vapidPublicKey', $this->webPush()->isConfigured() ? $this->webPush()->publicKey() : '');
        } catch (\Throwable $e) {
            $this->view->share('vapidPublicKey', '');
            $this->logger->error('VAPID: ' . $e->getMessage());
        }
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
