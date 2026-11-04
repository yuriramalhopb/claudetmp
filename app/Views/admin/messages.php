<h1>Mensagens</h1>
<?php if ($messages): ?>
    <?php foreach ($messages as $m): ?>
        <article class="card message<?= $m['is_read'] ? ' is-muted' : '' ?>">
            <header>
                <strong><?= e($m['name']) ?></strong> · <a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a>
                <?php if ($m['phone']): ?> · <?= e($m['phone']) ?><?php endif; ?>
                <small class="muted"> — <?= e(date('d/m/Y H:i', strtotime($m['created_at']))) ?></small>
            </header>
            <p><?= nl2br_e($m['message']) ?></p>
            <div class="actions">
                <?php if (!$m['is_read']): ?>
                    <form method="post" action="/admin/mensagens/<?= (int) $m['id'] ?>/lida" class="inline-form"><?= $csrf->field() ?><button class="btn btn-sm" type="submit">Marcar como lida</button></form>
                <?php endif; ?>
                <form method="post" action="/admin/mensagens/<?= (int) $m['id'] ?>/excluir" class="inline-form" data-confirm="Excluir mensagem?"><?= $csrf->field() ?><button class="btn btn-sm btn-danger" type="submit">Excluir</button></form>
            </div>
        </article>
    <?php endforeach; ?>
<?php else: ?>
    <p class="empty">Nenhuma mensagem.</p>
<?php endif; ?>
