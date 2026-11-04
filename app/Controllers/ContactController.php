<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Message;

final class ContactController extends Controller
{
    public function form(Request $request): Response
    {
        return $this->view('pages/contact', ['title' => 'Contato']);
    }

    public function send(Request $request): Response
    {
        $data = $request->only(['name', 'email', 'phone', 'message']);
        if ($this->isBot($request)) {
            $this->flash('success', 'Mensagem enviada! Responderemos em breve.');
            return $this->redirect('/contato');
        }
        if ($fail = $this->validate($data, [
            'name' => 'required|min:2|max:120',
            'email' => 'required|email',
            'phone' => 'phone',
            'message' => 'required|min:10|max:3000',
        ], '/contato')) {
            return $fail;
        }
        $this->throttle('contact', 5, 3600);
        $this->app->model(Message::class)->create($data, $this->app->request->ip());
        $this->flash('success', 'Mensagem enviada! Responderemos em breve. 🦆');
        return $this->redirect('/contato');
    }
}
