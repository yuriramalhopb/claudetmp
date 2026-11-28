<?php
$badges = (new App\Models\Stats($app->db))->badges();
$me = $auth->user();
$isAdmin = $auth->isAdmin();
$statusNow = $app->request->query('status');
$link = static function (string $href, string $label, int $count = 0) use ($currentPath, $statusNow): string {
    $path = (string) strtok($href, '?');
    parse_str((string) parse_url($href, PHP_URL_QUERY), $q);
    $on = $path === '/admin'
        ? $currentPath === '/admin'
        : ($currentPath === $path || str_starts_with($currentPath, $path . '/'));
    if ($on && $path === '/admin/velas') {
        $on = ($q['status'] ?? '') === $statusNow || (($q['status'] ?? '') === '' && $statusNow === '' && $currentPath === '/admin/velas') || ($currentPath !== '/admin/velas' && ($q['status'] ?? '') === '');
    }
    return '<a class="' . ($on ? 'is-active' : '') . '" href="' . e($href) . '"><span>' . e($label) . '</span>'
        . ($count > 0 ? '<span class="count-pill">' . $count . '</span>' : '') . '</a>';
};
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($title ?? 'Painel') . ' · Painel · ' . $church['name']) ?></title>
    <meta name="csrf-token" content="<?= e($csrf->token()) ?>">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <style nonce="<?= e($nonce) ?>"><?= $typeCss ?></style>
</head>
<body class="admin">
<div class="admin-shell">
    <aside class="admin-side" id="admin-side">
        <a class="brand" href="/admin"><svg viewBox="0 0 32 32" width="30" height="30" aria-hidden="true"><path d="M16 2c2.6 3 4 5.3 4 7.6A4 4 0 0 1 16 14a4 4 0 0 1-4-4.4C12 7.3 13.4 5 16 2z" fill="#f3c969"/><rect x="12" y="15" width="8" height="15" rx="2" fill="#fbf7ee"/></svg>
            <span class="brand-text"><strong><?= e($siteName) ?></strong><small>Painel da equipe</small></span></a>
        <nav class="admin-nav" aria-label="Painel">
            <?= $link('/admin', 'Visão geral') ?>
            <p class="nav-title">Velas</p>
            <?= $link('/admin/velas', 'Todas as velas') ?>
            <?= $link('/admin/velas?status=pending_payment', 'Pagamentos (Pix)', $badges['payments']) ?>
            <?= $link('/admin/velas?status=moderation', 'Moderação', $badges['moderation']) ?>
            <?= $link('/admin/missas', 'Intenções p/ missa', $badges['mass']) ?>
            <?= $link('/admin/gracas', 'Graças alcançadas', $badges['testimonials']) ?>
            <p class="nav-title">Conteúdo</p>
            <?= $link('/admin/oracoes', 'Orações') ?>
            <?= $link('/admin/mensagens', 'Palavra do dia') ?>
            <?= $link('/admin/horarios', 'Horários das missas') ?>
            <?php if ($isAdmin): ?>
                <p class="nav-title">Catálogo</p>
                <?= $link('/admin/tipos', 'Tipos de vela') ?>
                <?= $link('/admin/duracoes', 'Durações e valores') ?>
                <p class="nav-title">Administração</p>
                <?= $link('/admin/financeiro', 'Financeiro') ?>
                <?= $link('/admin/equipe', 'Equipe') ?>
                <?= $link('/admin/configuracoes', 'Configurações') ?>
                <?= $link('/admin/sistema', 'Sistema e backup') ?>
                <?= $link('/admin/auditoria', 'Atividades') ?>
            <?php endif; ?>
            <p class="nav-title">Conta</p>
            <?= $link('/admin/conta', 'Minha conta') ?>
            <a href="/" target="_blank" rel="noopener">Ver o site ↗</a>
        </nav>
        <form method="post" action="/admin/sair" class="center">
            <?= $csrf->field() ?>
            <p class="muted-on-dark side-user"><?= e($me['name']) ?> · <?= $isAdmin ? 'Admin' : 'Moderador' ?></p>
            <button class="btn btn-ghost btn-sm" type="submit">Sair</button>
        </form>
    </aside>
    <div class="admin-main">
        <div class="admin-top">
            <div><button class="btn btn-ghost-dark btn-sm admin-menu-btn" type="button" data-admin-menu aria-controls="admin-side">☰ Menu</button>
            <h1><?= e($title ?? 'Painel') ?></h1></div>
        </div>
        <?php require __DIR__ . '/../partials/flash.php'; ?>
        <?= $content ?>
    </div>
</div>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
