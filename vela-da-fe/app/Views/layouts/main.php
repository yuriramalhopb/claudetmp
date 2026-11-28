<?php
$pageTitle = ($title ?? '') !== '' ? $title . ' · ' . $church['name'] : $siteName . ' · ' . $church['name'];
$desc = excerpt($metaDescription ?? $settings->get('hero_text'), 160);
$base = (string) $app->config->get('app.url');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($desc) ?>">
    <meta name="csrf-token" content="<?= e($csrf->token()) ?>">
    <meta name="theme-color" content="#15122b">
    <?php if (!empty($metaRobots)): ?><meta name="robots" content="<?= e($metaRobots) ?>"><?php endif; ?>
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e($siteName) ?>">
    <meta property="og:title" content="<?= e($ogTitle ?? $pageTitle) ?>">
    <meta property="og:description" content="<?= e($desc) ?>">
    <meta property="og:url" content="<?= e($base . $currentPath) ?>">
    <link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <style nonce="<?= e($nonce) ?>"><?= $typeCss ?></style>
</head>
<body class="site">
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="/" aria-label="<?= e($siteName) ?> — início">
            <svg class="brand-icon" viewBox="0 0 32 32" width="32" height="32" aria-hidden="true"><path d="M16 2c2.6 3 4 5.3 4 7.6A4 4 0 0 1 16 14a4 4 0 0 1-4-4.4C12 7.300 13.400 5 16 2z" fill="#f3c969"/><rect x="12" y="15" width="8" height="15" rx="2" fill="#fbf7ee"/><rect x="15.300" y="13" width="1.400" height="3" fill="#6b6480"/></svg>
            <span class="brand-text"><strong><?= e($siteName) ?></strong><small><?= e($church['name']) ?></small></span>
        </a>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="menu" data-nav-toggle><span class="sr-only">Menu</span>☰</button>
        <nav id="menu" class="site-nav" aria-label="Principal">
            <a class="<?= trim(active($currentPath, '/velas')) ?>" href="/velas">Mural de velas</a>
            <a class="<?= trim(active($currentPath, '/oracoes')) ?>" href="/oracoes">Orações</a>
            <a class="<?= trim(active($currentPath, '/gracas')) ?>" href="/gracas">Graças alcançadas</a>
            <a class="<?= trim(active($currentPath, '/igreja')) ?>" href="/igreja">A igreja</a>
            <a class="btn btn-gold btn-sm" href="/acender">🕯️ Acender vela</a>
        </nav>
    </div>
</header>

<?php require __DIR__ . '/../partials/flash.php'; ?>

<main id="conteudo">
    <?= $content ?>
</main>

<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <p class="footer-brand"><?= e($church['name']) ?></p>
            <?php if ($settings->get('footer_text') !== ''): ?><p class="muted-on-dark"><?= nl2br_e((string) $settings->get('footer_text')) ?></p><?php endif; ?>
        </div>
        <div>
            <p class="footer-title">Contato</p>
            <ul class="footer-list">
                <?php if ($church['address'] !== ''): ?><li>📍 <?= e($church['address']) ?></li><?php endif; ?>
                <?php if ($church['phone'] !== ''): ?><li>📞 <?= e($church['phone']) ?></li><?php endif; ?>
                <?php if (whatsapp_link($church['whatsapp'])): ?><li>💬 <a href="<?= e(whatsapp_link($church['whatsapp'])) ?>" target="_blank" rel="noopener noreferrer">WhatsApp da secretaria</a></li><?php endif; ?>
                <?php if ($church['email'] !== ''): ?><li>✉️ <a href="mailto:<?= e($church['email']) ?>"><?= e($church['email']) ?></a></li><?php endif; ?>
                <?php if ($church['instagram'] !== ''): ?><li>📷 <a href="https://instagram.com/<?= e($church['instagram']) ?>" target="_blank" rel="noopener noreferrer">@<?= e($church['instagram']) ?></a></li><?php endif; ?>
            </ul>
        </div>
        <div>
            <p class="footer-title">Navegue</p>
            <ul class="footer-list">
                <li><a href="/acender">Acender uma vela</a></li>
                <li><a href="/minhas-velas">Recuperar o link das minhas velas</a></li>
                <li><a href="/privacidade">Privacidade e termos</a></li>
                <li><a href="/admin"><?= $auth->isStaff() ? 'Painel da equipe' : 'Área da equipe' ?></a></li>
            </ul>
        </div>
    </div>
    <p class="container footer-copy">© <?= date('Y') ?> <?= e($church['name']) ?> · <?= e($siteName) ?></p>
</footer>
<script src="<?= e(asset('vendor/qrcode.js')) ?>" defer></script>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
