<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(($title ?? 'Painel') . ' · Admin · ' . $siteName) ?></title>
    <link rel="icon" href="/assets/img/logo.svg" type="image/svg+xml">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="admin">
<div class="admin-shell">
    <aside class="admin-side">
        <a class="brand" href="/admin"><img src="/assets/img/logo.svg" alt="" width="36" height="36"><span>Painel</span></a>
        <nav>
            <a class="<?= $currentPath === '/admin' ? 'is-active' : '' ?>" href="/admin">📊 Visão geral</a>
            <a class="<?= active($currentPath, '/admin/trilheiros') ?>" href="/admin/trilheiros">🏍️ Trilheiros</a>
            <a class="<?= active($currentPath, '/admin/eventos') ?>" href="/admin/eventos">📅 Eventos</a>
            <a class="<?= active($currentPath, '/admin/galeria') ?>" href="/admin/galeria">🖼️ Galeria</a>
            <a class="<?= active($currentPath, '/admin/instagram') ?>" href="/admin/instagram">📸 Instagram</a>
            <a class="<?= active($currentPath, '/admin/mensagens') ?>" href="/admin/mensagens">✉️ Mensagens</a>
            <a class="<?= active($currentPath, '/admin/auditoria') ?>" href="/admin/auditoria">🔒 Auditoria</a>
            <hr>
            <a href="/">← Ver site</a>
            <form method="post" action="/sair"><?= $csrf->field() ?><button class="link-btn" type="submit">Sair</button></form>
        </nav>
    </aside>
    <div class="admin-main">
        <?= $view->partial('flash', ['flashes' => $flashes ?? []]) ?>
        <div class="admin-content">
            <?= $content ?>
        </div>
    </div>
</div>
<script src="<?= asset('js/app.js') ?>" defer></script>
</body>
</html>
