<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Candle;
use App\Models\CandlePlan;
use App\Models\CandleType;

/**
 * Fluxo da vela: acender (formulário), página pública, "minha vela" (por token secreto),
 * pagamento por Pix, orações pela vela, certificado e recuperação de links por e-mail.
 */
final class CandleController extends Controller
{
    /** Quantas velas por IP por hora (além do limite diário configurável). */
    private const HOURLY_LIMIT = 10;

    // ------------------------------------------------------------------ acender

    public function create(Request $request): Response
    {
        $old = [];
        $relitFrom = null;
        $code = $request->query('repetir');
        if ($code !== '') {
            $prev = $this->app->model(Candle::class)->findByCode($code);
            if ($prev && $this->app->model(Candle::class)->validToken($prev, $request->query('t'))) {
                $relitFrom = $prev;
                $old = [
                    'type_id' => $prev['type_id'], 'category' => $prev['category'], 'name' => $prev['name'],
                    'for_whom' => $prev['for_whom'], 'intention' => $prev['intention'], 'email' => $prev['email'],
                    'show_name' => $prev['show_name'], 'show_intention' => $prev['show_intention'],
                    'notify_email' => $prev['notify_email'], 'mass_intention' => $prev['mass_intention'],
                    'relit_code' => $prev['code'], 'relit_token' => $request->query('t'),
                ];
            }
        }
        if ($request->query('tipo') !== '' && ctype_digit($request->query('tipo'))) {
            $old['type_id'] = $request->query('tipo');
        }
        return $this->view('candle/create', $this->formData($old) + [
            'title' => 'Acender uma vela',
            'relitFrom' => $relitFrom,
            'metaDescription' => 'Acenda uma vela de fé e confie a Deus a sua intenção. A comunidade reza com você.',
        ]);
    }

    private function formData(array $defaults = []): array
    {
        $pixOk = $this->app->pix()->isConfigured();
        $plans = array_values(array_filter(
            $this->app->model(CandlePlan::class)->active(),
            static fn ($p) => $pixOk || (int) $p['price'] === 0
        ));
        $types = array_values(array_filter(
            $this->app->model(CandleType::class)->active(),
            static fn ($t) => $pixOk || (int) $t['price'] === 0
        ));
        return ['types' => $types, 'plans' => $plans, 'pixOk' => $pixOk, 'defaults' => $defaults];
    }

    public function store(Request $request): Response
    {
        if ($this->isBot($request)) {
            return $this->redirect('/');
        }
        $data = $request->only(['type_id', 'plan_id', 'category', 'name', 'for_whom', 'intention', 'email', 'terms', 'relit_code', 'relit_token']);
        foreach (['show_name', 'show_intention', 'notify_email', 'mass_intention'] as $flag) {
            $data[$flag] = $request->bool($flag);
        }
        $back = '/acender';
        $rules = [
            'type_id' => 'required|int', 'plan_id' => 'required|int',
            'category' => 'required|in:' . implode(',', array_keys(Candle::CATEGORIES)),
            'name' => 'required|min:2|max:80', 'for_whom' => 'max:100', 'intention' => 'max:500',
            'email' => 'email', 'terms' => 'accepted',
        ];
        if ($fail = $this->validate($data, $rules, $back)) {
            return $fail;
        }
        $type = $this->app->model(CandleType::class)->find((int) $data['type_id']);
        $plan = $this->app->model(CandlePlan::class)->find((int) $data['plan_id']);
        if (!$type || !$type['active'] || !$plan || !$plan['active']) {
            return $this->failWith(['type_id' => ['Escolha um tipo de vela e uma duração válidos.']], $data, $back);
        }
        $amount = Candle::price($type, $plan);
        if ($amount > 0 && !$this->app->pix()->isConfigured()) {
            return $this->failWith(['plan_id' => ['Esta opção não está disponível no momento. Escolha uma vela gratuita.']], $data, $back);
        }
        if ($data['notify_email'] && $data['email'] === '') {
            return $this->failWith(['email' => ['Informe seu e-mail para receber os avisos.']], $data, $back);
        }

        // limites contra abuso: por hora (IP) e por dia (IP anonimizado)
        $this->throttle('candle', self::HOURLY_LIMIT, 3600);
        $ipHash = $this->ipHash();
        $maxDay = max(1, (int) $this->app->settings()->get('max_per_day', 5));
        if ($this->app->model(Candle::class)->countRecentByIp($ipHash, date('Y-m-d H:i:s', time() - 86400)) >= $maxDay) {
            return $this->failWith(['name' => ["Você já acendeu {$maxDay} velas hoje. Volte amanhã e acenda outra com carinho. 🕯️"]], $data, $back);
        }

        // moderação: só o que aparece em público precisa de triagem
        $public = trim(($data['show_name'] ? $data['name'] : '') . ' ' . ($data['show_intention'] ? $data['for_whom'] . ' ' . $data['intention'] : ''));
        $manual = $this->app->settings()->get('moderation') === 'manual';
        $mod = ($public !== '' && ($manual || $this->app->moderation()->flag($public) !== null)) ? 'pending' : 'approved';

        // "acender de novo": liga a nova vela à anterior (se o token for válido)
        $relit = null;
        if ($data['relit_code'] !== '') {
            $prev = $this->app->model(Candle::class)->findByCode($data['relit_code']);
            if ($prev && $this->app->model(Candle::class)->validToken($prev, $data['relit_token'])) {
                $relit = (int) $prev['id'];
            }
        }

        $token = Candle::newToken();
        $created = $this->app->model(Candle::class)->create([
            'type_id' => $type['id'], 'plan_id' => $plan['id'], 'category' => $data['category'],
            'name' => $data['name'], 'show_name' => $data['show_name'], 'for_whom' => $data['for_whom'],
            'intention' => $data['intention'], 'show_intention' => $data['show_intention'],
            'email' => $data['email'], 'notify_email' => $data['notify_email'],
            'mass_intention' => $this->app->settings()->bool('mass_enabled') && $data['mass_intention'],
            'status' => $amount > 0 ? 'pending_payment' : 'active',
            'mod_status' => $mod, 'amount' => $amount, 'hours' => (int) $plan['hours'],
            'payment_method' => $amount > 0 ? null : 'free', 'ip_hash' => $ipHash, 'relit_from' => $relit,
        ], $token);

        $candle = $this->app->model(Candle::class)->findByCode($created['code']);
        $this->rememberToken($created['code'], $token);
        if ($amount > 0) {
            $this->app->notifier()->paymentRequested($candle, $token);
        } else {
            $this->app->notifier()->candleLit($candle, $token);
        }
        return $this->redirect('/vela/' . $created['code'] . '/minha?nova=1');
    }

    // ------------------------------------------------------------------ página pública

    public function show(Request $request): Response
    {
        $candle = $this->activeCandleOr404($request);
        $c = Candle::present($candle);
        $owner = $this->tokenFor($candle, $request) !== null;
        $name = $c['display_name'] !== 'Anônimo' ? ' por ' . $c['display_name'] : '';
        return $this->view('candle/show', [
            'c' => $c,
            'owner' => $owner,
            'alreadyPrayed' => $this->alreadyPrayed((int) $candle['id']),
            'title' => 'Vela acesa' . $name,
            'metaDescription' => 'Uma vela de fé foi acesa' . $name . '. Reze junto e acenda a sua também.',
            'ogTitle' => 'Vela acesa' . $name . ' 🕯️',
        ]);
    }

    private function alreadyPrayed(int $candleId): bool
    {
        return (bool) $this->db->fetchValue(
            'SELECT 1 FROM candle_prayers WHERE candle_id = ? AND visitor_hash = ? AND day = ?',
            [$candleId, $this->visitorHash(), date('Y-m-d')]
        );
    }

    public function pray(Request $request): Response
    {
        $candle = $this->activeCandleOr404($request);
        $this->throttle('pray', 60, 3600);
        $model = $this->app->model(Candle::class);
        $new = $model->pray((int) $candle['id'], $this->visitorHash());
        $count = (int) $this->db->fetchValue('SELECT prayer_count FROM candles WHERE id = ?', [(int) $candle['id']]);
        if ($this->wantsJson($request)) {
            return Response::json(['ok' => true, 'new' => $new, 'count' => $count]);
        }
        $this->flash($new ? 'success' : 'info', $new ? 'Obrigado por rezar por esta intenção. 🙏' : 'Você já rezou por esta vela hoje. Volte amanhã! 🙏');
        return $this->redirect('/vela/' . $candle['code']);
    }

    // ------------------------------------------------------------------ minha vela (token)

    public function manage(Request $request): Response
    {
        [$candle, $token] = $this->ownedCandle($request);
        $data = [
            'c' => Candle::present($candle),
            'raw' => $candle,
            'token' => $token,
            'isNew' => $request->query('nova') === '1',
            'title' => 'Minha vela',
            'metaRobots' => 'noindex',
            'manageUrl' => $this->app->config->get('app.url') . '/vela/' . $candle['code'] . '/minha?t=' . $token,
            'publicUrl' => $this->app->config->get('app.url') . '/vela/' . $candle['code'],
        ];
        if ($candle['status'] === 'pending_payment') {
            $pix = $this->app->pix();
            $data['pixPayload'] = $pix->isConfigured() ? $pix->payload((int) $candle['amount'] / 100, 'VELA' . $candle['code'], 'Vela da Fe') : '';
        }
        return $this->view('candle/manage', $data);
    }

    public function claimPaid(Request $request): Response
    {
        [$candle] = $this->ownedCandle($request);
        $this->throttle('claimpaid', 10, 3600);
        $this->app->model(Candle::class)->claimPaid((int) $candle['id']);
        $this->flash('success', 'Obrigado! Avisamos a nossa equipe. Assim que o Pix for conferido, sua vela será acesa.');
        return $this->redirect('/vela/' . $candle['code'] . '/minha');
    }

    public function cancel(Request $request): Response
    {
        [$candle] = $this->ownedCandle($request);
        if ($candle['status'] === 'pending_payment') {
            $this->app->model(Candle::class)->setStatus((int) $candle['id'], 'cancelled');
            $this->flash('info', 'Pedido cancelado. Quando quiser, acenda uma nova vela.');
            return $this->redirect('/acender');
        }
        return $this->redirect('/vela/' . $candle['code'] . '/minha');
    }

    public function certificate(Request $request): Response
    {
        [$candle] = $this->ownedCandle($request);
        if ($candle['status'] !== 'active') {
            throw new HttpException(404);
        }
        return $this->view('candle/certificate', ['c' => Candle::present($candle), 'title' => 'Certificado da vela', 'metaRobots' => 'noindex'], 200, 'layouts/plain');
    }

    // ------------------------------------------------------------------ "Minhas velas" por e-mail

    public function recoverForm(Request $request): Response
    {
        return $this->view('candle/recover', ['title' => 'Minhas velas', 'metaRobots' => 'noindex']);
    }

    public function recover(Request $request): Response
    {
        if ($this->isBot($request)) {
            return $this->redirect('/');
        }
        $email = mb_strtolower($request->input('email'));
        if ($fail = $this->validate(['email' => $email], ['email' => 'required|email'], '/minhas-velas')) {
            return $fail;
        }
        $this->throttle('recover', 5, 3600);
        if ($this->app->limiter->attempt('recover-mail:' . $email, 3, 3600)) {
            $model = $this->app->model(Candle::class);
            $items = [];
            foreach ($model->recentByEmail($email) as $c) {
                $items[] = [$c, $model->rotateToken((int) $c['id'])];
            }
            if ($items) {
                $this->app->notifier()->manageLinks($email, $items);
            }
        }
        // resposta igual exista ou não vela para o e-mail (não revela cadastros)
        $this->flash('success', 'Se houver velas recentes nesse e-mail, enviamos os links agora. Confira também a caixa de spam.');
        return $this->redirect('/minhas-velas');
    }

    // ------------------------------------------------------------------ utilidades

    private function activeCandleOr404(Request $request): array
    {
        $candle = $this->app->model(Candle::class)->findByCode((string) $request->param('code'));
        if ($candle === null || $candle['status'] !== 'active') {
            throw new HttpException(404, 'Vela não encontrada.');
        }
        return $candle;
    }

    /** @return array{0:array, 1:string} vela + token válido (da URL ou da sessão) */
    private function ownedCandle(Request $request): array
    {
        $candle = $this->app->model(Candle::class)->findByCode((string) $request->param('code'));
        if ($candle === null) {
            throw new HttpException(404, 'Vela não encontrada.');
        }
        $token = $this->tokenFor($candle, $request);
        if ($token === null) {
            throw new HttpException(403, 'Este link não é válido. Peça o link da sua vela em "Minhas velas".');
        }
        $this->rememberToken($candle['code'], $token);
        return [$candle, $token];
    }

    /** Token válido para a vela: ?t= na URL (GET) ou campo do formulário (POST) ou sessão. */
    private function tokenFor(array $candle, Request $request): ?string
    {
        $model = $this->app->model(Candle::class);
        $sessionTokens = (array) $this->session->get('candle_tokens', []);
        foreach ([$request->input('t'), $sessionTokens[$candle['code']] ?? ''] as $t) {
            if (is_string($t) && $t !== '' && $model->validToken($candle, $t)) {
                return $t;
            }
        }
        return null;
    }

    private function rememberToken(string $code, string $token): void
    {
        $tokens = (array) $this->session->get('candle_tokens', []);
        $tokens[$code] = $token;
        $this->session->set('candle_tokens', array_slice($tokens, -20, null, true));
    }
}
