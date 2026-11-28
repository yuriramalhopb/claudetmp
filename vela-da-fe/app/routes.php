<?php
declare(strict_types=1);

use App\Controllers\Admin;
use App\Controllers\CandleController;
use App\Controllers\HomeController;
use App\Controllers\PrayerController;
use App\Controllers\SeoController;
use App\Controllers\TestimonialController;
use App\Controllers\WallController;
use App\Core\Router;

$router = new Router();

// ------------------------------------------------------------------ site público
$router->get('/', [HomeController::class, 'index']);
$router->get('/api/resumo', [HomeController::class, 'summary']);
$router->get('/igreja', [HomeController::class, 'church']);
$router->get('/privacidade', [HomeController::class, 'privacy']);
$router->get('/robots.txt', [SeoController::class, 'robots']);
$router->get('/sitemap.xml', [SeoController::class, 'sitemap']);

$router->get('/acender', [CandleController::class, 'create']);
$router->post('/acender', [CandleController::class, 'store']);
$router->get('/velas', [WallController::class, 'index']);
$router->get('/vela/{code}', [CandleController::class, 'show']);
$router->post('/vela/{code}/rezar', [CandleController::class, 'pray']);
$router->get('/vela/{code}/minha', [CandleController::class, 'manage']);
$router->post('/vela/{code}/paguei', [CandleController::class, 'claimPaid']);
$router->post('/vela/{code}/cancelar', [CandleController::class, 'cancel']);
$router->get('/vela/{code}/certificado', [CandleController::class, 'certificate']);
$router->get('/minhas-velas', [CandleController::class, 'recoverForm']);
$router->post('/minhas-velas', [CandleController::class, 'recover']);

$router->get('/oracoes', [PrayerController::class, 'index']);
$router->get('/oracoes/{slug}', [PrayerController::class, 'show']);
$router->get('/gracas', [TestimonialController::class, 'index']);
$router->post('/gracas', [TestimonialController::class, 'store']);

// ------------------------------------------------------------------ painel
$router->get('/admin/entrar', [Admin\AuthController::class, 'loginForm'], ['guest']);
$router->post('/admin/entrar', [Admin\AuthController::class, 'login'], ['guest']);

$router->group('/admin', ['staff'], function (Router $r) {
    $r->post('/sair', [Admin\AuthController::class, 'logout']);
    $r->get('', [Admin\DashboardController::class, 'index']);
    $r->get('/conta', [Admin\AuthController::class, 'account']);
    $r->post('/conta', [Admin\AuthController::class, 'updateAccount']);
    $r->post('/conta/senha', [Admin\AuthController::class, 'updatePassword']);

    // velas
    $r->get('/velas', [Admin\CandleController::class, 'index']);
    $r->get('/velas/exportar', [Admin\CandleController::class, 'export']);
    $r->get('/velas/{id}', [Admin\CandleController::class, 'show']);
    $r->post('/velas/{id}/confirmar', [Admin\CandleController::class, 'confirmPayment']);
    $r->post('/velas/{id}/cancelar', [Admin\CandleController::class, 'cancelPayment']);
    $r->post('/velas/{id}/aprovar', [Admin\CandleController::class, 'approve']);
    $r->post('/velas/{id}/recusar', [Admin\CandleController::class, 'reject']);
    $r->post('/velas/{id}/prolongar', [Admin\CandleController::class, 'extend']);
    $r->post('/velas/{id}/apagar', [Admin\CandleController::class, 'extinguish']);
    $r->post('/velas/{id}/remover', [Admin\CandleController::class, 'remove']);
    $r->post('/velas/{id}/restaurar', [Admin\CandleController::class, 'restore']);
    $r->post('/velas/{id}/nota', [Admin\CandleController::class, 'note']);
    $r->post('/velas/{id}/missa', [Admin\CandleController::class, 'massToggle']);
    $r->post('/velas/{id}/excluir', [Admin\CandleController::class, 'delete']);

    // intenções de missa
    $r->get('/missas', [Admin\MassController::class, 'index']);
    $r->get('/missas/imprimir', [Admin\MassController::class, 'printSheet']);
    $r->post('/missas/marcar', [Admin\MassController::class, 'markAll']);

    // graças alcançadas
    $r->get('/gracas', [Admin\TestimonialController::class, 'index']);
    $r->post('/gracas/{id}/status', [Admin\TestimonialController::class, 'setStatus']);
    $r->post('/gracas/{id}/excluir', [Admin\TestimonialController::class, 'delete']);

    // somente administradores
    $r->group('', ['admin'], function (Router $r) {
        $r->get('/financeiro', [Admin\ReportController::class, 'index']);
        $r->get('/financeiro/exportar', [Admin\ReportController::class, 'export']);

        $r->get('/equipe', [Admin\UserController::class, 'index']);
        $r->get('/equipe/novo', [Admin\UserController::class, 'create']);
        $r->post('/equipe', [Admin\UserController::class, 'store']);
        $r->get('/equipe/{id}/editar', [Admin\UserController::class, 'edit']);
        $r->post('/equipe/{id}', [Admin\UserController::class, 'update']);

        $r->get('/configuracoes', [Admin\SettingsController::class, 'index']);
        $r->post('/configuracoes', [Admin\SettingsController::class, 'update']);

        $r->get('/sistema', [Admin\SystemController::class, 'index']);
        $r->post('/sistema/rotinas', [Admin\SystemController::class, 'runCron']);
        $r->post('/sistema/email-teste', [Admin\SystemController::class, 'testMail']);
        $r->post('/sistema/backup', [Admin\SystemController::class, 'backup']);
        $r->get('/sistema/backup/{file}', [Admin\SystemController::class, 'downloadBackup']);
        $r->get('/auditoria', [Admin\SystemController::class, 'audit']);
    });

    // cadastros simples (tipos, durações, orações, mensagens, horários) — deve ficar por último
    $r->get('/{resource}', [Admin\ResourceController::class, 'index']);
    $r->get('/{resource}/novo', [Admin\ResourceController::class, 'create']);
    $r->post('/{resource}', [Admin\ResourceController::class, 'store']);
    $r->get('/{resource}/{id}/editar', [Admin\ResourceController::class, 'edit']);
    $r->post('/{resource}/{id}', [Admin\ResourceController::class, 'update']);
    $r->post('/{resource}/{id}/excluir', [Admin\ResourceController::class, 'delete']);
});

return $router;
