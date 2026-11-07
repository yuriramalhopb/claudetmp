<?php
declare(strict_types=1);

use App\Controllers\AccountController;
use App\Controllers\Admin;
use App\Controllers\AuthController;
use App\Controllers\ContactController;
use App\Controllers\EventController;
use App\Controllers\GalleryController;
use App\Controllers\HomeController;
use App\Controllers\InstagramController;
use App\Controllers\MemberController;
use App\Core\Router;

$r = new Router();

// ---- Público ----
$r->get('/', [HomeController::class, 'index']);
$r->get('/sobre', [HomeController::class, 'about']);
$r->get('/privacidade', [HomeController::class, 'privacy']);
$r->get('/instalar', [HomeController::class, 'install']);

$r->get('/galeria', [GalleryController::class, 'index']);
$r->get('/galeria/{id}', [GalleryController::class, 'show']);

$r->get('/instagram', [InstagramController::class, 'index']);

$r->get('/agenda', [EventController::class, 'index']);
$r->get('/agenda/{id}', [EventController::class, 'show']);
$r->get('/agenda/{id}/calendario.ics', [EventController::class, 'ics']);
$r->get('/trilhas-do-ano', [EventController::class, 'year']);
$r->get('/trilhas-do-ano/{ano}', [EventController::class, 'year']);
$r->get('/trilhas-do-ano/{ano}/calendario.ics', [EventController::class, 'yearIcs']);

$r->get('/trilheiros', [MemberController::class, 'index']);
$r->get('/trilheiros/{id}', [MemberController::class, 'show']);

$r->get('/contato', [ContactController::class, 'form']);
$r->post('/contato', [ContactController::class, 'send']);

// ---- Autenticação ----
$r->get('/cadastro', [AuthController::class, 'registerForm'], ['guest']);
$r->post('/cadastro', [AuthController::class, 'register'], ['guest']);
$r->get('/entrar', [AuthController::class, 'loginForm'], ['guest']);
$r->post('/entrar', [AuthController::class, 'login'], ['guest']);
$r->post('/sair', [AuthController::class, 'logout']);
$r->get('/entrar/2fa', [App\Controllers\TwoFactorController::class, 'challengeForm'], ['guest']);
$r->post('/entrar/2fa', [App\Controllers\TwoFactorController::class, 'challenge'], ['guest']);
$r->get('/esqueci-senha', [AuthController::class, 'forgotForm'], ['guest']);
$r->post('/esqueci-senha', [AuthController::class, 'forgot'], ['guest']);
$r->get('/redefinir-senha', [AuthController::class, 'resetForm'], ['guest']);
$r->post('/redefinir-senha', [AuthController::class, 'reset'], ['guest']);

// ---- Notificações push (app) ----
$r->post('/push/inscrever', [App\Controllers\PushController::class, 'subscribe'], ['auth', 'approved']);
$r->post('/push/cancelar', [App\Controllers\PushController::class, 'unsubscribe'], ['auth']);

// ---- Área do trilheiro ----
$r->group('/minha-conta', ['auth'], function (Router $r): void {
    $r->get('', [AccountController::class, 'index']);
    $r->get('/editar', [AccountController::class, 'edit']);
    $r->post('/editar', [AccountController::class, 'update']);
    $r->post('/foto', [AccountController::class, 'photo']);
    $r->post('/senha', [AccountController::class, 'password']);
    $r->post('/excluir', [AccountController::class, 'destroy']);
    $r->get('/meus-dados.json', [AccountController::class, 'export']);
    $r->get('/2fa', [App\Controllers\TwoFactorController::class, 'setupForm']);
    $r->post('/2fa/ativar', [App\Controllers\TwoFactorController::class, 'enable']);
    $r->post('/2fa/desativar', [App\Controllers\TwoFactorController::class, 'disable']);
});

$r->post('/agenda/{id}/presenca', [EventController::class, 'rsvp'], ['auth', 'approved']);
$r->get('/agenda/sugerir', [EventController::class, 'suggestForm'], ['auth', 'approved']);
$r->post('/agenda/sugerir', [EventController::class, 'suggest'], ['auth', 'approved']);
$r->get('/galeria/enviar', [GalleryController::class, 'uploadForm'], ['auth', 'approved']);
$r->post('/galeria/enviar', [GalleryController::class, 'upload'], ['auth', 'approved']);

// ---- Painel administrativo ----
$r->group('/admin', ['auth', 'admin'], function (Router $r): void {
    $r->get('', [Admin\DashboardController::class, 'index']);
    $r->get('/auditoria', [Admin\DashboardController::class, 'audit']);
    $r->post('/push/teste', [App\Controllers\PushController::class, 'test']);
    $r->get('/sistema', [Admin\SystemController::class, 'index']);
    $r->post('/sistema/email-teste', [Admin\SystemController::class, 'testEmail']);
    $r->post('/sistema/backup', [Admin\SystemController::class, 'backupNow']);

    $r->get('/trilheiros', [Admin\UserController::class, 'index']);
    $r->get('/trilheiros/exportar.csv', [Admin\UserController::class, 'export']);
    $r->get('/trilheiros/{id}', [Admin\UserController::class, 'show']);
    $r->post('/trilheiros/{id}/status', [Admin\UserController::class, 'status']);
    $r->post('/trilheiros/{id}/perfil', [Admin\UserController::class, 'role']);
    $r->post('/trilheiros/{id}/excluir', [Admin\UserController::class, 'destroy']);
    $r->post('/trilheiros/{id}/2fa-reset', [Admin\UserController::class, 'resetTwoFactor']);

    $r->get('/eventos', [Admin\EventController::class, 'index']);
    $r->get('/eventos/novo', [Admin\EventController::class, 'create']);
    $r->post('/eventos/novo', [Admin\EventController::class, 'store']);
    $r->get('/eventos/{id}/editar', [Admin\EventController::class, 'edit']);
    $r->post('/eventos/{id}/editar', [Admin\EventController::class, 'update']);
    $r->post('/eventos/{id}/aprovar', [Admin\EventController::class, 'approve']);
    $r->post('/eventos/{id}/excluir', [Admin\EventController::class, 'destroy']);

    $r->get('/galeria', [Admin\GalleryController::class, 'index']);
    $r->post('/galeria/albuns', [Admin\GalleryController::class, 'storeAlbum']);
    $r->get('/galeria/albuns/{id}', [Admin\GalleryController::class, 'album']);
    $r->post('/galeria/albuns/{id}', [Admin\GalleryController::class, 'updateAlbum']);
    $r->post('/galeria/albuns/{id}/fotos', [Admin\GalleryController::class, 'uploadPhotos']);
    $r->post('/galeria/albuns/{id}/excluir', [Admin\GalleryController::class, 'destroyAlbum']);
    $r->post('/galeria/fotos/{id}/aprovar', [Admin\GalleryController::class, 'approvePhoto']);
    $r->post('/galeria/fotos/{id}/excluir', [Admin\GalleryController::class, 'destroyPhoto']);

    $r->get('/instagram', [Admin\InstagramController::class, 'index']);
    $r->post('/instagram/config', [Admin\InstagramController::class, 'settings']);
    $r->post('/instagram/sincronizar', [Admin\InstagramController::class, 'sync']);
    $r->post('/instagram/manual', [Admin\InstagramController::class, 'addManual']);
    $r->post('/instagram/{id}/visibilidade', [Admin\InstagramController::class, 'toggle']);
    $r->post('/instagram/{id}/excluir', [Admin\InstagramController::class, 'destroy']);

    $r->get('/mensagens', [Admin\MessageController::class, 'index']);
    $r->post('/mensagens/{id}/lida', [Admin\MessageController::class, 'read']);
    $r->post('/mensagens/{id}/excluir', [Admin\MessageController::class, 'destroy']);
});

return $r;
