<section class="section">
    <div class="container">
        <h1 class="center"><?= $tab === 'memorial' ? 'Memorial de velas' : 'Mural de velas' ?></h1>
        <p class="lead center">
            <?= $tab === 'memorial' ? 'Velas que se apagaram nos últimos 30 dias. A oração continua.' : 'Reze pelas intenções da nossa comunidade: toque em uma vela para rezar por ela.' ?>
        </p>

        <div class="tabs" role="tablist">
            <a class="tab<?= $tab === 'acesas' ? ' is-active' : '' ?>" href="/velas">🕯️ Acesas agora</a>
            <?php if ($memorialOn): ?><a class="tab<?= $tab === 'memorial' ? ' is-active' : '' ?>" href="/velas?aba=memorial">🕊️ Memorial</a><?php endif; ?>
            <a class="tab tab-cta" href="/acender">+ Acender a minha</a>
        </div>

        <form class="filters" method="get" action="/velas" data-autosubmit>
            <?php if ($tab === 'memorial'): ?><input type="hidden" name="aba" value="memorial"><?php endif; ?>
            <label>Motivo
                <select name="categoria">
                    <option value="">Todos</option>
                    <?php foreach (App\Models\Candle::CATEGORIES as $k => [$label, $emoji]): ?>
                        <option value="<?= e($k) ?>"<?= selected($filters['category'], $k) ?>><?= e($emoji . ' ' . $label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Vela
                <select name="tipo">
                    <option value="">Todas</option>
                    <?php foreach ($types as $t): ?><option value="<?= (int) $t['id'] ?>"<?= selected($filters['type'], $t['id']) ?>><?= e($t['name']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Ordem
                <select name="ordem">
                    <?php foreach (App\Models\Candle::SORTS as $k => $label): ?><option value="<?= e($k) ?>"<?= selected($filters['sort'], $k) ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </label>
            <noscript><button class="btn btn-sm btn-gold" type="submit">Filtrar</button></noscript>
        </form>

        <?php if ($items): ?>
            <p class="muted center"><?= (int) $total ?> <?= $total === 1 ? 'vela' : 'velas' ?></p>
            <div class="candle-grid">
                <?php foreach ($items as $c): ?><?= $view->partial('candle-card', ['c' => $c]) ?><?php endforeach; ?>
            </div>
            <?php require __DIR__ . '/../partials/pagination.php'; ?>
        <?php else: ?>
            <p class="empty">Nenhuma vela por aqui. <a href="/acender">Acenda a primeira!</a></p>
        <?php endif; ?>
    </div>
</section>
