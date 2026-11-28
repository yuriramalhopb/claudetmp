<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Testimonial;

final class TestimonialController extends Controller
{
    public function index(Request $request): Response
    {
        $status = array_key_exists($request->query('status'), Testimonial::STATUS) ? $request->query('status') : 'pending';
        $model = $this->app->model(Testimonial::class);
        return $this->adminView('testimonials', [
            'title' => 'Graças alcançadas',
            'status' => $status,
            'items' => $model->listByStatus($status),
            'counts' => [
                'pending' => $model->count("status = 'pending'"),
                'approved' => $model->count("status = 'approved'"),
                'rejected' => $model->count("status = 'rejected'"),
            ],
        ]);
    }

    public function setStatus(Request $request): Response
    {
        $id = $this->idParam($request);
        $status = $request->input('status');
        $model = $this->app->model(Testimonial::class);
        if ($model->find($id) !== null && in_array($status, ['approved', 'rejected', 'pending'], true)) {
            $model->setStatus($id, $status);
            $this->app->audit('testemunho_' . $status, '#' . $id);
            $this->flash('success', $status === 'approved' ? 'Testemunho publicado.' : 'Testemunho atualizado.');
        }
        return $this->back('/admin/gracas');
    }

    public function delete(Request $request): Response
    {
        $id = $this->idParam($request);
        $this->app->model(Testimonial::class)->delete($id);
        $this->app->audit('testemunho_excluido', '#' . $id);
        $this->flash('success', 'Testemunho excluído.');
        return $this->back('/admin/gracas');
    }
}
