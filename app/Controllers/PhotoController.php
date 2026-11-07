<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Photo;
use App\Models\PhotoSocial;
use App\Models\User;

/**
 * Página da foto: curtir, comentar e marcar trilheiros.
 * Ver é público; interagir exige trilheiro aprovado.
 */
final class PhotoController extends Controller
{
    public function show(Request $request): Response
    {
        $photo = $this->photo($request);
        $social = $this->app->model(PhotoSocial::class);
        $userId = $this->auth->id();
        [$newer, $older] = $this->app->model(Photo::class)->neighbors((int) $photo['album_id'], (int) $photo['id']);
        $tags = $social->tags((int) $photo['id']);
        $taggedIds = array_map('intval', array_column($tags, 'id'));
        $members = $this->auth->isApproved()
            ? array_filter($this->app->model(User::class)->directory('', '', 1000, 0), fn ($m) => !in_array((int) $m['id'], $taggedIds, true))
            : [];
        return $this->view('gallery/photo', [
            'title' => $photo['caption'] ?: 'Foto: ' . $photo['album_title'],
            'ogImage' => $this->app->config->get('app.url') . upload_url('photos', $photo['filename']),
            'photo' => $photo,
            'likers' => $social->likers((int) $photo['id']),
            'liked' => $userId ? $social->liked((int) $photo['id'], $userId) : false,
            'comments' => $social->comments((int) $photo['id']),
            'tags' => $tags,
            'members' => $members,
            'newer' => $newer,
            'older' => $older,
        ]);
    }

    public function like(Request $request): Response
    {
        $photo = $this->photo($request);
        $this->throttle('like', 120, 3600);
        $this->app->model(PhotoSocial::class)->toggleLike((int) $photo['id'], (int) $this->auth->id());
        return $this->redirect('/fotos/' . $photo['id'] . '#curtidas');
    }

    public function comment(Request $request): Response
    {
        $photo = $this->photo($request);
        $body = str_replace("\r\n", "\n", $request->input('body'));
        $v = Validator::make(['body' => $body], ['body' => 'required|min:1|max:1000']);
        if ($v->fails()) {
            $this->flash('error', 'O comentário deve ter de 1 a 1000 caracteres.');
            return $this->redirect('/fotos/' . $photo['id'] . '#comentarios');
        }
        $this->throttle('comment', 20, 3600);
        $this->app->model(PhotoSocial::class)->addComment((int) $photo['id'], (int) $this->auth->id(), $body);
        return $this->redirect('/fotos/' . $photo['id'] . '#comentarios');
    }

    public function deleteComment(Request $request): Response
    {
        $photo = $this->photo($request);
        $social = $this->app->model(PhotoSocial::class);
        $comment = $social->findComment((int) $request->param('cid'));
        if ($comment === null || (int) $comment['photo_id'] !== (int) $photo['id']) {
            throw new HttpException(404);
        }
        // só o autor do comentário ou um admin
        if ((int) $comment['user_id'] !== $this->auth->id() && !$this->auth->isAdmin()) {
            throw new HttpException(403);
        }
        $social->delete((int) $comment['id']);
        if ($this->auth->isAdmin() && (int) $comment['user_id'] !== $this->auth->id()) {
            $this->app->audit('excluir_comentario', '#' . $comment['id'] . ' foto #' . $photo['id']);
        }
        $this->flash('success', 'Comentário excluído.');
        return $this->redirect('/fotos/' . $photo['id'] . '#comentarios');
    }

    public function tag(Request $request): Response
    {
        $photo = $this->photo($request);
        $target = $this->app->model(User::class)->find($request->int('user_id'));
        if ($target === null || $target['status'] !== 'approved') {
            $this->flash('error', 'Escolha um trilheiro.');
            return $this->redirect('/fotos/' . $photo['id'] . '#marcacoes');
        }
        $this->throttle('tag', 60, 3600);
        $me = $this->auth->user();
        if ($this->app->model(PhotoSocial::class)->tag((int) $photo['id'], (int) $target['id'], (int) $me['id']) && (int) $target['id'] !== (int) $me['id']) {
            $this->app->notifier()->photoTagged($target, (int) $photo['id'], display_name($me));
        }
        $this->flash('success', display_name($target) . ' foi marcado(a) na foto.');
        return $this->redirect('/fotos/' . $photo['id'] . '#marcacoes');
    }

    public function untag(Request $request): Response
    {
        $photo = $this->photo($request);
        $social = $this->app->model(PhotoSocial::class);
        $uid = (int) $request->param('uid');
        $tag = $social->findTag((int) $photo['id'], $uid);
        if ($tag === null) {
            throw new HttpException(404);
        }
        $me = (int) $this->auth->id();
        // quem foi marcado, quem marcou ou um admin podem remover
        if ($uid !== $me && (int) $tag['tagged_by'] !== $me && !$this->auth->isAdmin()) {
            throw new HttpException(403);
        }
        $social->untag((int) $photo['id'], $uid);
        $this->flash('success', 'Marcação removida.');
        return $this->redirect('/fotos/' . $photo['id'] . '#marcacoes');
    }

    private function photo(Request $request): array
    {
        $photo = $this->app->model(Photo::class)->findApprovedWithAlbum($this->idParam($request));
        if ($photo === null) {
            throw new HttpException(404);
        }
        return $photo;
    }
}
