<section class="section">
    <div class="container">
        <h1 class="center">Orações</h1>
        <p class="lead center">Para rezar diante da sua vela, em casa ou onde você estiver.</p>
        <div class="three-col">
            <?php foreach ($prayers as $p): ?>
                <a class="card card-link" href="/oracoes/<?= e($p['slug']) ?>">
                    <h3><?= e($p['title']) ?></h3>
                    <p><?= e(excerpt($p['body'], 110)) ?></p>
                </a>
            <?php endforeach; ?>
        </div>
        <?php if (!$prayers): ?><p class="empty">Nenhuma oração cadastrada ainda.</p><?php endif; ?>
    </div>
</section>
