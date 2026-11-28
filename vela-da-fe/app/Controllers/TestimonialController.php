<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Testimonial;

/** Graças alcançadas: testemunhos dos fiéis (publicados só depois de aprovados). */
final class TestimonialController extends Controller
{
    private const PER_PAGE = 12;

    public function index(Request $request): Response
    {
        $model = $this->app->model(Testimonial::class);
        $total = $model->countApproved();
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($this->pageParam($request), $pages);
        return $this->view('pages/testimonials', [
            'title' => 'Graças alcançadas',
            'metaDescription' => 'Testemunhos de quem recebeu graças e quer partilhar a sua fé.',
            'items' => $model->approved(self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'page' => $page, 'pages' => $pages, 'base' => '/gracas', 'params' => [],
        ]);
    }

    public function store(Request $request): Response
    {
        if ($this->isBot($request)) {
            return $this->redirect('/gracas');
        }
        $data = $request->only(['name', 'text']);
        if ($fail = $this->validate($data, ['name' => 'required|min:2|max:80', 'text' => 'required|min:10|max:1500'], '/gracas#enviar')) {
            return $fail;
        }
        $this->throttle('testimonial', 3, 3600);
        $this->app->model(Testimonial::class)->create($data['name'], $data['text'], $this->ipHash());
        $this->flash('success', 'Deus seja louvado! Recebemos o seu testemunho e ele será publicado após a análise da nossa equipe.');
        return $this->redirect('/gracas');
    }
}
