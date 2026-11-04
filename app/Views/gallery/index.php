<section class="page-head">
    <div class="container">
        <h1>Galeria</h1>
        <p class="lead">Os melhores momentos das nossas trilhas.</p>
        <?php if ($auth->isApproved()): ?><a class="btn btn-primary" href="/galeria/enviar">📷 Enviar fotos</a><?php endif; ?>
    </div>
</section>
<section class="section">
    <div class="container">
        <?php if ($albums): ?>
        <div class="album-grid">
            <?php foreach ($albums as $a): ?>
                <a class="album-card" href="/galeria/<?= (int) $a['id'] ?>">
                    <div class="album-cover">
                        <?php if ($a['cover']): ?>
                            <img src="<?= e(upload_url('photos', $a['cover'], true)) ?>" alt="" loading="lazy">
                        <?php else: ?>
                            <span class="album-empty">🏍️</span>
                        <?php endif; ?>
                    </div>
                    <div class="album-info">
                        <h3><?= e($a['title']) ?></h3>
                        <p class="meta"><?= $a['event_date'] ? e(date_br($a['event_date'])) . ' · ' : '' ?><?= (int) $a['total'] ?> foto(s)</p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <p class="empty">Nenhum álbum ainda.</p>
        <?php endif; ?>
    </div>
</section>
