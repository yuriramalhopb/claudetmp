<?php if (!empty($res['intro'])): ?><p class="muted"><?= e($res['intro']) ?></p><?php endif; ?>
<p><a class="btn btn-gold btn-sm" href="/admin/<?= e($key) ?>/novo">+ Novo(a) <?= e($res['singular']) ?></a></p>
<div class="panel table-wrap">
<table class="table">
    <thead><tr><?php foreach ($res['columns'] as $col): ?><th><?= e($col[1]) ?></th><?php endforeach; ?><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
        <tr>
            <?php foreach ($res['columns'] as $col): $val = $row[$col[0]] ?? null; $kind = $col[2] ?? 'text'; ?>
                <td>
                <?php if ($kind === 'bool'): ?><span class="badge <?= $val ? 'badge-ok' : '' ?>"><?= $val ? 'Sim' : 'Não' ?></span>
                <?php elseif ($kind === 'color'): ?><span class="dot ct-<?= (int) $row['id'] ?>"></span> <?= e($val) ?>
                <?php elseif ($kind === 'money'): ?><?= e(money((int) $val, true)) ?>
                <?php elseif ($kind === 'weekday'): ?><?= e(App\Admin\Resources::WEEKDAYS[(int) $val] ?? '') ?>
                <?php else: ?><?= e(excerpt((string) $val, 90)) ?><?php endif; ?>
                </td>
            <?php endforeach; ?>
            <td class="num">
                <a class="btn btn-ghost-dark btn-sm" href="/admin/<?= e($key) ?>/<?= (int) $row['id'] ?>/editar">Editar</a>
                <form class="inline-form" method="post" action="/admin/<?= e($key) ?>/<?= (int) $row['id'] ?>/excluir" data-confirm="Excluir este item?"><?= $csrf->field() ?><button class="btn btn-ghost-dark btn-sm" type="submit">Excluir</button></form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="<?= count($res['columns']) + 1 ?>" class="muted center">Nada cadastrado ainda.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
