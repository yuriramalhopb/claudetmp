<section class="page-head">
    <div class="container">
        <p class="breadcrumb"><a href="/galeria">Galeria</a> /</p>
        <h1><?= e($album['title']) ?></h1>
        <?php if ($album['event_date']): ?><p class="meta"><?= e(date_br($album['event_date'])) ?> · <?= (int) $total ?> foto(s)</p><?php endif; ?>
        <?php if ($album['description']): ?><p class="lead"><?= nl2br_e($album['description']) ?></p><?php endif; ?>
        <div class="actions">
            <?php if ($auth->isApproved()): ?><a class="btn btn-primary" href="/galeria/enviar?album=<?= (int) $album['id'] ?>">📷 Enviar fotos para este álbum</a><?php endif; ?>
            <button class="btn btn-ghost" type="button" data-share data-title="<?= e($album['title']) ?>">Compartilhar</button>
        </div>
    </div>
</section>
<section class="section">
    <div class="container">
        <?php if ($photos): ?>
            <div class="photo-grid" data-gallery>
                <?php foreach ($photos as $p): ?>
                    <a class="photo" href="<?= e(upload_url('photos', $p['filename'])) ?>" data-lb-item data-page="/fotos/<?= (int) $p['id'] ?>"
                       data-caption="<?= e(trim(($p['caption'] ?? '') . ($p['author'] ? ' — foto de ' . ($p['author_nick'] ?: $p['author']) : ''))) ?>">
                        <img src="<?= e(upload_url('photos', $p['filename'], true)) ?>" alt="<?= e($p['caption'] ?: 'Foto da trilha') ?>" loading="lazy">
                        <?php if ($p['likes'] || $p['comments']): ?><span class="photo-counts"><?= $p['likes'] ? '❤ ' . (int) $p['likes'] : '' ?> <?= $p['comments'] ? '💬 ' . (int) $p['comments'] : '' ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?= $view->partial('pagination', ['page' => $page, 'pages' => $pages, 'base' => '/galeria/' . (int) $album['id']]) ?>
        <?php else: ?>
            <p class="empty">Este álbum ainda não tem fotos.</p>
        <?php endif; ?>
    </div>
</section>
