<?php /* Layout sem menu: login da equipe, certificado e folhas para imprimir. */ ?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($title ?? '') . ' · ' . $church['name']) ?></title>
    <meta name="csrf-token" content="<?= e($csrf->token()) ?>">
    <meta name="robots" content="noindex">
    <link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <style nonce="<?= e($nonce) ?>"><?= $typeCss ?></style>
</head>
<body class="plain">
<?php require __DIR__ . '/../partials/flash.php'; ?>
<?= $content ?>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
