<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Candle;
use App\Models\CandleType;
use App\Models\DailyMessage;
use App\Models\MassTime;
use App\Models\Prayer;
use App\Models\Testimonial;

final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        $prayers = $this->app->model(Prayer::class)->active();
        $dayPrayer = $prayers ? $prayers[((int) date('z')) % count($prayers)] : null;
        $candles = $this->app->model(Candle::class);
        return $this->view('pages/home', [
            'title' => '',
            'metaDescription' => (string) $this->app->settings()->get('hero_text'),
            'counters' => $candles->counters(),
            'latest' => $candles->latestLit(12),
            'message' => $this->app->model(DailyMessage::class)->forToday(),
            'dayPrayer' => $dayPrayer,
            'massTimes' => $this->app->model(MassTime::class)->byWeekday(),
            'testimonials' => $this->app->model(Testimonial::class)->approved(3),
            'types' => $this->app->model(CandleType::class)->active(),
        ]);
    }

    /** Contadores ao vivo da página inicial (consultado a cada 30 s pelo navegador). */
    public function summary(Request $request): Response
    {
        return Response::json($this->app->model(Candle::class)->counters())->withHeader('Cache-Control', 'no-store');
    }

    public function church(Request $request): Response
    {
        return $this->view('pages/church', [
            'title' => 'A igreja',
            'metaDescription' => 'Endereço, contatos e horários das celebrações.',
            'massTimes' => $this->app->model(MassTime::class)->byWeekday(),
        ]);
    }

    public function privacy(Request $request): Response
    {
        return $this->view('pages/privacy', ['title' => 'Privacidade e termos de uso', 'metaDescription' => 'Como cuidamos dos seus dados (LGPD).']);
    }
}
