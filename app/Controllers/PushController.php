<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\PushSubscription;
use App\Services\WebPush;

/**
 * Inscrição do aparelho para notificações (chamado pelo app via fetch, com token CSRF).
 */
final class PushController extends Controller
{
    public function subscribe(Request $request): Response
    {
        $this->throttle('push-subscribe', 20, 3600);
        $endpoint = $request->input('endpoint');
        $p256dh = $request->input('p256dh');
        $auth = $request->input('auth');
        if (!WebPush::isAllowedEndpoint($endpoint)
            || strlen(WebPush::b64uDecode($p256dh)) !== 65
            || strlen(WebPush::b64uDecode($auth)) !== 16) {
            return Response::json(['ok' => false, 'error' => 'Inscrição inválida.'], 422);
        }
        $this->app->model(PushSubscription::class)->save($this->auth->id(), $endpoint, $p256dh, $auth, $request->userAgent());
        return Response::json(['ok' => true]);
    }

    public function unsubscribe(Request $request): Response
    {
        $this->app->model(PushSubscription::class)->removeByEndpoint($request->input('endpoint'), $this->auth->id());
        return Response::json(['ok' => true]);
    }

    /** Admin: envia um teste para os próprios aparelhos. */
    public function test(Request $request): Response
    {
        $this->app->notifier()->push('Teste de notificação 🦆', 'Se você está vendo isto, as notificações funcionam!', '/', [(int) $this->auth->id()]);
        $stats = $this->app->model(PushSubscription::class)->dispatchPending($this->app->webPush());
        $this->flash($stats['sent'] > 0 ? 'success' : 'warning', $stats['sent'] > 0
            ? "Notificação enviada para {$stats['sent']} aparelho(s)."
            : 'Nenhum aparelho seu recebeu. Ative as notificações no botão 🔔 do app primeiro.');
        return $this->back('/admin');
    }
}
