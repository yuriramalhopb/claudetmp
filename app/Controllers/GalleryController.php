<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Album;
use App\Models\Photo;

final class GalleryController extends Controller
{
    private const PER_PAGE = 36;
    private const MAX_FILES = 10;

    public function index(Request $request): Response
    {
        return $this->view('gallery/index', [
            'title' => 'Galeria',
            'albums' => $this->app->model(Album::class)->allWithCover(),
        ]);
    }

    public function show(Request $request): Response
    {
        $album = $this->notFoundIfNull($this->app->model(Album::class)->find($this->idParam($request)));
        $photos = $this->app->model(Photo::class);
        $total = $photos->countByAlbum((int) $album['id']);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, $request->int('pagina', 1)), $pages);

        return $this->view('gallery/show', [
            'title' => $album['title'],
            'album' => $album,
            'photos' => $photos->byAlbum((int) $album['id'], self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ]);
    }

    public function uploadForm(Request $request): Response
    {
        return $this->view('gallery/upload', [
            'title' => 'Enviar fotos',
            'albums' => $this->app->model(Album::class)->options(),
            'selected' => $request->int('album'),
        ]);
    }

    /** Fotos enviadas por trilheiros ficam pendentes até um admin aprovar. */
    public function upload(Request $request): Response
    {
        $this->throttle('photo-upload', 30, 3600);
        $albumId = $request->int('album_id');
        $album = $this->app->model(Album::class)->find($albumId);
        if ($album === null) {
            return $this->failWith(['album_id' => ['Escolha um álbum.']], $request->only(['caption']), '/galeria/enviar');
        }
        $files = array_slice($request->files('photos'), 0, self::MAX_FILES);
        if ($files === []) {
            return $this->failWith(['photos' => ['Selecione pelo menos uma foto.']], [], '/galeria/enviar');
        }
        $caption = mb_substr($request->input('caption'), 0, 255);
        $isAdmin = $this->auth->isAdmin();
        $ok = 0;
        $errors = [];
        foreach ($files as $file) {
            try {
                $name = $this->app->uploader()->store($file, 'photos', 1920, 480);
                $this->app->model(Photo::class)->create($albumId, $name, $caption, $this->auth->id(), $isAdmin ? 'approved' : 'pending');
                $ok++;
            } catch (\RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }
        if ($ok > 0) {
            $this->flash('success', $isAdmin
                ? "{$ok} foto(s) publicada(s)!"
                : "{$ok} foto(s) enviada(s)! Elas aparecem na galeria após aprovação da administração.");
        }
        foreach (array_unique($errors) as $err) {
            $this->flash('error', $err);
        }
        return $this->redirect('/galeria/' . $albumId);
    }
}
