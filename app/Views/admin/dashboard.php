<h1>Visão geral</h1>
<div class="stat-grid">
    <?php foreach ($stats as $s): ?>
        <a class="stat<?= !empty($s['alert']) && $s['value'] > 0 ? ' stat-alert' : '' ?>" href="<?= e($s['link']) ?>">
            <strong><?= (int) $s['value'] ?></strong><span><?= e($s['label']) ?></span>
        </a>
    <?php endforeach; ?>
</div>

<?php if (!$igConfigured): ?>
    <div class="notice notice-warning">📸 O Instagram ainda não está conectado. <a href="/admin/instagram">Configurar agora</a>.</div>
<?php endif; ?>

<div class="two-col">
    <section class="card">
        <h2>Cadastros aguardando aprovação</h2>
        <?php if ($pendingUsers): ?>
            <table class="table">
                <?php foreach ($pendingUsers as $u): ?>
                    <tr>
                        <td><a href="/admin/trilheiros/<?= (int) $u['id'] ?>"><?= e($u['name']) ?></a><br><small class="muted"><?= e($u['city']) ?> · <?= e($u['bike_model']) ?></small></td>
                        <td class="right">
                            <form method="post" action="/admin/trilheiros/<?= (int) $u['id'] ?>/status" class="inline-form">
                                <?= $csrf->field() ?><input type="hidden" name="status" value="approved">
                                <button class="btn btn-sm btn-primary" type="submit">Aprovar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?><p class="muted">Nenhum cadastro pendente. 👍</p><?php endif; ?>
    </section>
    <section class="card">
        <h2>Próximos eventos</h2>
        <?php if ($nextEvents): ?>
            <table class="table">
                <?php foreach ($nextEvents as $ev): ?>
                    <tr><td><?= e(date('d/m H:i', strtotime($ev['start_at']))) ?></td><td><a href="/admin/eventos/<?= (int) $ev['id'] ?>/editar"><?= e($ev['title']) ?></a></td><td class="right"><?= (int) $ev['going'] ?> 🏍️</td></tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?><p class="muted">Nenhum evento agendado.</p><?php endif; ?>
        <a class="btn btn-sm" href="/admin/eventos/novo">+ Novo evento</a>
    </section>
</div>
<p class="muted">Última sincronização do Instagram: <?= $igLastSync ? e(date('d/m/Y H:i', strtotime($igLastSync))) : 'nunca' ?></p>
