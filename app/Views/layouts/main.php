<?php /** @var \App\Core\View $view */ ?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($title ?? '') !== '' ? $title . ' · ' . $siteName : $siteName) ?></title>
    <meta name="description" content="<?= e($metaDescription ?? 'Ducks Offroad — turma de trilha de moto. Agenda de trilhas, galeria de fotos, Instagram e cadastro de trilheiros.') ?>">
    <meta property="og:title" content="<?= e($title ?? $siteName) ?>">
    <meta property="og:site_name" content="<?= e($siteName) ?>">
    <meta property="og:type" content="website">
    <?php if (!empty($ogImage)): ?><meta property="og:image" content="<?= e($ogImage) ?>"><?php endif; ?>
<?= $view->partial('pwa-head') ?>
    <meta name="csrf-token" content="<?= e($csrf->token()) ?>">
    <?php if ($auth->isApproved() && $vapidPublicKey !== ''): ?><meta name="vapid-key" content="<?= e($vapidPublicKey) ?>"><?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<?php foreach ($styles ?? [] as $href): ?>    <link rel="stylesheet" href="<?= asset($href) ?>">
<?php endforeach; ?>
</head>
<body>
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="/" aria-label="<?= e($siteName) ?> - início">
            <img src="/assets/img/logo.svg" alt="" width="44" height="44">
            <span><?= e($siteName) ?></span>
        </a>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="menu" data-nav-toggle>
            <span></span><span></span><span></span><span class="sr-only">Menu</span>
        </button>
        <nav id="menu" class="main-nav" aria-label="Principal">
            <a class="<?= active($currentPath, '/') ?>" href="/">Início</a>
            <a class="<?= active($currentPath, '/agenda') ?>" href="/agenda">Agenda</a>
            <a class="<?= active($currentPath, '/trilhas-do-ano') ?>" href="/trilhas-do-ano">Trilhas <?= date('Y') ?></a>
            <a class="<?= active($currentPath, '/galeria') ?>" href="/galeria">Galeria</a>
            <a class="<?= active($currentPath, '/instagram') ?>" href="/instagram">Instagram</a>
            <a class="<?= active($currentPath, '/trilheiros') ?>" href="/trilheiros">Trilheiros</a>
            <a class="<?= active($currentPath, '/contato') ?>" href="/contato">Contato</a>
            <button class="link-btn install-btn" type="button" data-install hidden>📲 Instalar app</button>
            <button class="link-btn push-btn" type="button" data-push-toggle hidden>🔔 Ativar avisos</button>
            <span class="nav-sep"></span>
            <?php if ($auth->check()): ?>
                <?php if ($auth->isAdmin()): ?><a class="<?= active($currentPath, '/admin') ?>" href="/admin">Painel</a><?php endif; ?>
                <a class="nav-user<?= active($currentPath, '/minha-conta') ?>" href="/minha-conta">
                    <img src="<?= e(avatar_url($auth->user())) ?>" alt="" width="28" height="28">
                    <?= e(display_name($auth->user())) ?>
                </a>
                <form method="post" action="/sair" class="inline-form"><?= $csrf->field() ?><button class="link-btn" type="submit">Sair</button></form>
            <?php else: ?>
                <a class="<?= active($currentPath, '/entrar') ?>" href="/entrar">Entrar</a>
                <a class="btn btn-sm btn-primary" href="/cadastro">Quero ser trilheiro</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<?= $view->partial('flash', ['flashes' => $flashes ?? []]) ?>

<main id="conteudo">
    <?= $content ?>
</main>

<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <a class="brand" href="/"><img src="/assets/img/logo.svg" alt="" width="40" height="40"><span><?= e($siteName) ?></span></a>
            <p class="muted">Turma de trilha de moto. Barro, poeira, amizade e respeito à natureza.</p>
        </div>
        <div>
            <h4>Navegue</h4>
            <a href="/agenda">Agenda de trilhas</a>
            <a href="/trilhas-do-ano">Calendário <?= date('Y') ?></a>
            <a href="/galeria">Galeria</a>
            <a href="/trilheiros">Trilheiros</a>
            <a href="/sobre">Sobre a turma</a>
        </div>
        <div>
            <h4>Siga a gente</h4>
            <a href="https://www.instagram.com/<?= e($igUsername) ?>/" target="_blank" rel="noopener noreferrer">@<?= e($igUsername) ?> no Instagram</a>
            <a href="/contato">Fale com a turma</a>
            <a href="/instalar">📲 Instalar o app no celular</a>
            <a href="/privacidade">Privacidade (LGPD)</a>
        </div>
    </div>
    <div class="container footer-bottom">© <?= date('Y') ?> <?= e($siteName) ?>. Pilote com responsabilidade — use equipamento de proteção.</div>
</footer>

<div class="ios-install" data-ios-install hidden>
    <img src="/assets/icons/apple-touch-icon.png" alt="" width="44" height="44">
    <p><strong>Instale o app Ducks</strong><br>Toque em <span class="ios-share" aria-label="Compartilhar">⬆︎</span> <em>Compartilhar</em> e depois em <em>Adicionar à Tela de Início</em>.</p>
    <button type="button" class="flash-close" data-ios-close aria-label="Fechar">×</button>
</div>

<div class="lightbox" data-lightbox hidden>
    <button class="lb-close" type="button" data-lb-close aria-label="Fechar">×</button>
    <button class="lb-prev" type="button" data-lb-prev aria-label="Anterior">‹</button>
    <figure><img alt="" data-lb-img><figcaption data-lb-caption></figcaption></figure>
    <button class="lb-next" type="button" data-lb-next aria-label="Próxima">›</button>
</div>

<script src="<?= asset('js/app.js') ?>" defer></script>
<?php foreach ($scripts ?? [] as $src): ?><script src="<?= asset($src) ?>" defer></script>
<?php endforeach; ?>
<?php if (!empty($instagramEmbed)): ?>
<script async src="https://www.instagram.com/embed.js" nonce="<?= e($nonce) ?>"></script>
<?php endif; ?>
</body>
</html>
