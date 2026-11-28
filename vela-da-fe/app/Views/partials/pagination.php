<?php if (($pages ?? 1) > 1): ?>
<nav class="pagination" aria-label="Paginação">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
        <?php if ($i === $page): ?>
            <span class="current" aria-current="page"><?= $i ?></span>
        <?php elseif ($i === 1 || $i === $pages || abs($i - $page) <= 2): ?>
            <a href="<?= e($base . query_with(($params ?? []) + ['pagina' => $i])) ?>"><?= $i ?></a>
        <?php elseif (abs($i - $page) === 3): ?>
            <span class="dots">…</span>
        <?php endif; ?>
    <?php endfor; ?>
</nav>
<?php endif; ?>
