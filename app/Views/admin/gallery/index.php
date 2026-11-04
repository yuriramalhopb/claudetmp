<h1>Galeria</h1>
<?php if ($pending): ?>
<section class="card">
    <h2>Fotos aguardando aprovação (<?= count($pending) ?>)</h2>
    <div class="admin-photos">
        <?php foreach ($pending as $p): ?>
            <figure>
                <a href="<?= e(upload_url('photos', $p['filename'])) ?>" target="_blank"><img src="<?= e(upload_url('photos', $p['filename'], true)) ?>" alt=""></a>
                <figcaption><small><?= e($p['album_title']) ?> · por <?= e($p['author'] ?? '—') ?></small><?php if ($p['caption']): ?><br><?= e($p['caption']) ?><?php endif; ?></figcaption>
                <div class="actions">
                    <form method="post" action="/admin/galeria/fotos/<?= (int) $p['id'] ?>/aprovar" class="inline-form"><?= $csrf->field() ?><button class="btn btn-sm btn-primary" type="submit">Aprovar</button></form>
                    <form method="post" action="/admin/galeria/fotos/<?= (int) $p['id'] ?>/excluir" class="inline-form" data-confirm="Recusar e excluir esta foto?"><?= $csrf->field() ?><button class="btn btn-sm btn-danger" type="submit">Recusar</button></form>
                </div>
            </figure>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<div class="two-col">
    <section class="card">
        <h2>Álbuns</h2>
        <?php if ($albums): ?>
        <table class="table">
            <?php foreach ($albums as $a): ?>
                <tr>
                    <td><?php if ($a['cover']): ?><img class="avatar-sm square" src="<?= e(upload_url('photos', $a['cover'], true)) ?>" alt=""><?php endif; ?></td>
                    <td><a href="/admin/galeria/albuns/<?= (int) $a['id'] ?>"><?= e($a['title']) ?></a><br><small class="muted"><?= $a['event_date'] ? e(date('d/m/Y', strtotime($a['event_date']))) . ' · ' : '' ?><?= (int) $a['total'] ?> foto(s)</small></td>
                </tr>
            <?php endforeach; ?>
        </table>
        <?php else: ?><p class="muted">Nenhum álbum criado.</p><?php endif; ?>
    </section>
    <form class="card form" method="post" action="/admin/galeria/albuns">
        <h2>Novo álbum</h2>
        <?= $csrf->field() ?>
        <label>Título *<input type="text" name="title" required maxlength="150" value="<?= e(old($old, 'title')) ?>"><?= field_error($errors, 'title') ?></label>
        <label>Data da trilha<input type="date" name="event_date" value="<?= e(old($old, 'event_date')) ?>"></label>
        <label>Descrição<textarea name="description" rows="3" maxlength="2000"><?= e(old($old, 'description')) ?></textarea></label>
        <button class="btn btn-primary" type="submit">Criar álbum</button>
    </form>
</div>
