<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Album;
use App\Models\Photo;

final class GalleryController extends Controller
{
    private const RULES = ['title' => 'required|min:2|max:150', 'description' => 'max:2000', 'event_date' => 'date'];

    public function index(Request $request): Response
    {
        return $this->adminView('gallery/index', [
            'title' => 'Galeria',
            'albums' => $this->app->model(Album::class)->allWithCover(),
            'pending' => $this->app->model(Photo::class)->pending(),
        ]);
    }

    public function storeAlbum(Request $request): Response
    {
        $data = $request->only(['title', 'description', 'event_date']);
        if ($fail = $this->validate($data, self::RULES, '/admin/galeria')) {
            return $fail;
        }
        $id = $this->app->model(Album::class)->create($data['title'], $data['description'], $data['event_date']);
        $this->app->audit('criar_album', "#{$id} {$data['title']}");
        $this->flash('success', 'Álbum criado. Agora envie as fotos.');
        return $this->redirect('/admin/galeria/albuns/' . $id);
    }

    public function album(Request $request): Response
    {
        $album = $this->notFoundIfNull($this->app->model(Album::class)->find($this->idParam($request)));
        return $this->adminView('gallery/album', [
            'title' => $album['title'],
            'album' => $album,
            'photos' => $this->app->model(Photo::class)->adminByAlbum((int) $album['id']),
        ]);
    }

    public function updateAlbum(Request $request): Response
    {
        $album = $this->notFoundIfNull($this->app->model(Album::class)->find($this->idParam($request)));
        $data = $request->only(['title', 'description', 'event_date']);
        $back = '/admin/galeria/albuns/' . $album['id'];
        if ($fail = $this->validate($data, self::RULES, $back)) {
            return $fail;
        }
        $this->app->model(Album::class)->updateAlbum((int) $album['id'], $data['title'], $data['description'], $data['event_date']);
        $this->flash('success', 'Álbum atualizado.');
        return $this->redirect($back);
    }

    public function uploadPhotos(Request $request): Response
    {
        $album = $this->notFoundIfNull($this->app->model(Album::class)->find($this->idParam($request)));
        $files = array_slice($request->files('photos'), 0, 30);
        $caption = mb_substr($request->input('caption'), 0, 255);
        $ok = 0;
        foreach ($files as $file) {
            try {
                $name = $this->app->uploader()->store($file, 'photos', 1920, 480);
                $this->app->model(Photo::class)->create((int) $album['id'], $name, $caption, $this->auth->id(), 'approved');
                $ok++;
            } catch (\RuntimeException $e) {
                $this->flash('error', ($file['name'] ?? 'arquivo') . ': ' . $e->getMessage());
            }
        }
        if ($ok) {
            $this->app->audit('enviar_fotos', "{$ok} foto(s) no álbum #{$album['id']}");
            $this->flash('success', "{$ok} foto(s) publicada(s).");
        }
        return $this->redirect('/admin/galeria/albuns/' . $album['id']);
    }

    public function destroyAlbum(Request $request): Response
    {
        $albums = $this->app->model(Album::class);
        $album = $this->notFoundIfNull($albums->find($this->idParam($request)));
        foreach ($this->app->model(Photo::class)->filenamesByAlbum((int) $album['id']) as $file) {
            $this->app->uploader()->delete('photos', $file);
        }
        $albums->delete((int) $album['id']); // fotos removidas em cascata
        $this->app->audit('excluir_album', '#' . $album['id'] . ' ' . $album['title']);
        $this->flash('success', 'Álbum excluído.');
        return $this->redirect('/admin/galeria');
    }

    public function approvePhoto(Request $request): Response
    {
        $id = $this->idParam($request);
        $this->app->model(Photo::class)->approve($id);
        $this->app->audit('aprovar_foto', '#' . $id);
        $this->flash('success', 'Foto aprovada.');
        return $this->back('/admin/galeria');
    }

    public function destroyPhoto(Request $request): Response
    {
        $photos = $this->app->model(Photo::class);
        $photo = $this->notFoundIfNull($photos->find($this->idParam($request)));
        $this->app->uploader()->delete('photos', $photo['filename']);
        $photos->delete((int) $photo['id']);
        $this->app->audit('excluir_foto', '#' . $photo['id']);
        $this->flash('success', 'Foto excluída.');
        return $this->back('/admin/galeria');
    }
}
