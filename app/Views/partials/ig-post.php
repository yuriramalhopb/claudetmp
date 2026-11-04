<?php $src = upload_url('instagram', $post['local_file'], true); ?>
<?php if ($src !== null): ?>
<a class="ig-card" href="<?= e($post['permalink']) ?>" target="_blank" rel="noopener noreferrer">
    <img src="<?= e($src) ?>" alt="<?= e(excerpt($post['caption'], 100) ?: 'Post do Instagram') ?>" loading="lazy">
    <?php if (in_array($post['media_type'], ['VIDEO', 'CAROUSEL_ALBUM'], true)): ?>
        <span class="ig-badge"><?= $post['media_type'] === 'VIDEO' ? '▶' : '❐' ?></span>
    <?php endif; ?>
    <span class="ig-overlay"><?= e(excerpt($post['caption'], 120)) ?></span>
</a>
<?php else: ?>
<div class="ig-embed">
    <blockquote class="instagram-media" data-instgrm-permalink="<?= e($post['permalink']) ?>" data-instgrm-version="14">
        <a href="<?= e($post['permalink']) ?>" target="_blank" rel="noopener noreferrer">Ver esta publicação no Instagram</a>
    </blockquote>
</div>
<?php endif; ?>
