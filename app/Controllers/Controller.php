<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;

abstract class Controller
{
    protected Database $db;
    protected Session $session;
    protected Auth $auth;

    public function __construct(protected readonly App $app)
    {
        $this->db = $app->db;
        $this->session = $app->session;
        $this->auth = $app->auth;
    }

    protected function view(string $template, array $data = [], int $status = 200, ?string $layout = 'layouts/main'): Response
    {
        $data += [
            'errors' => $this->session->pull('_errors', []),
            'old' => $this->session->pull('_old', []),
            'flashes' => $this->session->pull('_flash', []),
        ];
        return Response::html($this->app->view->render($template, $data, $layout), $status);
    }

    protected function adminView(string $template, array $data = []): Response
    {
        return $this->view('admin/' . $template, $data, 200, 'layouts/admin');
    }

    protected function redirect(string $to): Response
    {
        return Response::redirect($to);
    }

    protected function back(string $fallback = '/'): Response
    {
        $ref = (string) $this->app->request->header('Referer');
        $host = (string) $this->app->request->header('Host');
        // só volta para páginas do próprio site
        if ($ref !== '' && parse_url($ref, PHP_URL_HOST) . (parse_url($ref, PHP_URL_PORT) ? ':' . parse_url($ref, PHP_URL_PORT) : '') === $host) {
            $path = (string) parse_url($ref, PHP_URL_PATH);
            $query = parse_url($ref, PHP_URL_QUERY);
            return Response::redirect($path . ($query ? '?' . $query : ''));
        }
        return Response::redirect($fallback);
    }

    protected function flash(string $type, string $message): void
    {
        $this->session->flash($type, $message);
    }

    /**
     * Valida e, se falhar, guarda erros + dados antigos (sem senhas) e lança redirect de volta.
     */
    protected function validate(array $data, array $rules, string $back): ?Response
    {
        $v = Validator::make($data, $rules);
        if (!$v->fails()) {
            return null;
        }
        return $this->failWith($v->errors(), $data, $back);
    }

    protected function failWith(array $errors, array $data, string $back): Response
    {
        $safe = array_diff_key($data, array_flip(['password', 'password_confirmation', 'current_password', 'access_token']));
        $this->session->set('_errors', $errors);
        $this->session->set('_old', $safe);
        $first = reset($errors);
        $this->flash('error', is_array($first) ? (string) $first[0] : 'Verifique os campos destacados.');
        return $this->redirect($back);
    }

    protected function idParam(Request $request): int
    {
        $id = (int) $request->param('id');
        if ($id <= 0) {
            throw new HttpException(404);
        }
        return $id;
    }

    protected function notFoundIfNull(?array $row): array
    {
        if ($row === null) {
            throw new HttpException(404);
        }
        return $row;
    }

    /** Limite simples por IP para formulários públicos. */
    protected function throttle(string $action, int $max, int $seconds): void
    {
        if (!$this->app->limiter->attempt($action . ':' . $this->app->request->ip(), $max, $seconds)) {
            throw new HttpException(429);
        }
    }

    /** Honeypot: robôs costumam preencher o campo escondido "website". */
    protected function isBot(Request $request): bool
    {
        return $request->input('website') !== '';
    }
}
