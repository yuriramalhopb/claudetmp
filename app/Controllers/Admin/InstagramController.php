<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\InstagramPost;
use App\Models\Setting;

final class InstagramController extends Controller
{
    public function index(Request $request): Response
    {
        $settings = $this->app->model(Setting::class);
        $token = $this->app->instagram()->token();
        return $this->adminView('instagram', [
            'title' => 'Instagram',
            'posts' => $this->app->model(InstagramPost::class)->all(),
            'configured' => $token !== '',
            // nunca exibimos o token completo
            'tokenHint' => $token !== '' ? substr($token, 0, 6) . '…' . substr($token, -4) : '',
            'lastSync' => $this->app->instagram()->lastSync(),
            'lastError' => $settings->get('instagram_last_error'),
        ]);
    }

    public function settings(Request $request): Response
    {
        $token = $request->input('access_token');
        if ($request->bool('remove_token')) {
            $this->app->model(Setting::class)->set('instagram_token', null);
            $this->app->audit('instagram_token_removido');
            $this->flash('success', 'Token removido.');
            return $this->redirect('/admin/instagram');
        }
        if ($token === '' || !preg_match('/^[A-Za-z0-9._|-]{20,1000}$/', $token)) {
            $this->flash('error', 'Token inválido.');
            return $this->redirect('/admin/instagram');
        }
        $settings = $this->app->model(Setting::class);
        $settings->set('instagram_token', $token);
        $settings->set('instagram_token_refreshed', null);
        $settings->set('instagram_last_error', null);
        $this->app->audit('instagram_token_atualizado');
        $this->flash('success', 'Token salvo. Clique em "Sincronizar agora" para importar os posts.');
        return $this->redirect('/admin/instagram');
    }

    public function sync(Request $request): Response
    {
        $this->throttle('ig-sync', 10, 600);
        try {
            $n = $this->app->instagram()->sync();
            $this->app->audit('instagram_sync', $n . ' posts');
            $this->flash('success', "{$n} publicação(ões) sincronizada(s) do Instagram.");
        } catch (\Throwable $e) {
            $this->app->logger->error('Instagram sync manual: ' . $e->getMessage());
            $this->flash('error', 'Falha ao sincronizar: ' . $e->getMessage());
        }
        return $this->redirect('/admin/instagram');
    }

    /** Adiciona um post pelo link (exibido via incorporação oficial do Instagram). */
    public function addManual(Request $request): Response
    {
        $permalink = $request->input('permalink');
        if (!preg_match(InstagramPost::PERMALINK_REGEX, $permalink)) {
            $this->flash('error', 'Cole o link de um post ou reel público, ex.: https://www.instagram.com/p/ABC123/');
            return $this->redirect('/admin/instagram');
        }
        $posts = $this->app->model(InstagramPost::class);
        if ($posts->findByPermalink(InstagramPost::normalizePermalink($permalink))) {
            $this->flash('warning', 'Esse post já está no site.');
            return $this->redirect('/admin/instagram');
        }
        $posts->addManual($permalink, mb_substr($request->input('caption'), 0, 2000));
        $this->app->audit('instagram_post_manual', $permalink);
        $this->flash('success', 'Post adicionado ao site.');
        return $this->redirect('/admin/instagram');
    }

    public function toggle(Request $request): Response
    {
        $this->app->model(InstagramPost::class)->toggleHidden($this->idParam($request));
        $this->flash('success', 'Visibilidade alterada.');
        return $this->redirect('/admin/instagram');
    }

    public function destroy(Request $request): Response
    {
        $posts = $this->app->model(InstagramPost::class);
        $post = $this->notFoundIfNull($posts->find($this->idParam($request)));
        $this->app->uploader()->delete('instagram', $post['local_file']);
        $posts->delete((int) $post['id']);
        $this->flash('success', 'Post removido do site (posts da API voltam na próxima sincronização; use "ocultar" para escondê-los).');
        return $this->redirect('/admin/instagram');
    }
}
