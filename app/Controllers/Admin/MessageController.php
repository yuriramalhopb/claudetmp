<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Message;

final class MessageController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->adminView('messages', [
            'title' => 'Mensagens',
            'messages' => $this->app->model(Message::class)->latest(),
        ]);
    }

    public function read(Request $request): Response
    {
        $this->app->model(Message::class)->markRead($this->idParam($request));
        return $this->redirect('/admin/mensagens');
    }

    public function destroy(Request $request): Response
    {
        $this->app->model(Message::class)->delete($this->idParam($request));
        $this->app->audit('excluir_mensagem', '#' . $this->idParam($request));
        $this->flash('success', 'Mensagem excluída.');
        return $this->redirect('/admin/mensagens');
    }
}
