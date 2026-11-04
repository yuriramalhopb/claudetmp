<section class="page-head">
    <div class="container">
        <h1>Instagram</h1>
        <p class="lead">As publicações da turma, direto do <a href="https://www.instagram.com/<?= e($igUsername) ?>/" target="_blank" rel="noopener noreferrer">@<?= e($igUsername) ?></a>.</p>
        <a class="btn btn-primary" href="https://www.instagram.com/<?= e($igUsername) ?>/" target="_blank" rel="noopener noreferrer">Seguir no Instagram</a>
    </div>
</section>
<section class="section">
    <div class="container">
        <?php if ($posts): ?>
            <div class="ig-grid ig-grid-lg">
                <?php foreach ($posts as $post): ?><?= $view->partial('ig-post', ['post' => $post]) ?><?php endforeach; ?>
            </div>
            <?= $view->partial('pagination', ['page' => $page, 'pages' => $pages, 'base' => '/instagram']) ?>
        <?php else: ?>
            <p class="empty">Nenhuma publicação ainda.</p>
        <?php endif; ?>
    </div>
</section>
