<p class="breadcrumb"><a href="/admin/galeria">← Galeria</a></p>
<div class="admin-head">
    <h1><?= e($album['title']) ?></h1>
    <a class="btn btn-sm btn-ghost" href="/galeria/<?= (int) $album['id'] ?>" target="_blank">Ver no site</a>
</div>
<div class="two-col">
    <form class="card form" method="post" action="/admin/galeria/albuns/<?= (int) $album['id'] ?>/fotos" enctype="multipart/form-data">
        <h2>Enviar fotos</h2>
        <?= $csrf->field() ?>
        <label>Fotos (até 30 por envio)<input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple required></label>
        <label>Legenda (opcional, vale para todas)<input type="text" name="caption" maxlength="255"></label>
        <button class="btn btn-primary" type="submit">Enviar</button>
    </form>
    <form class="card form" method="post" action="/admin/galeria/albuns/<?= (int) $album['id'] ?>">
        <h2>Dados do álbum</h2>
        <?= $csrf->field() ?>
        <label>Título<input type="text" name="title" required maxlength="150" value="<?= e(old($old, 'title', $album['title'])) ?>"><?= field_error($errors, 'title') ?></label>
        <label>Data<input type="date" name="event_date" value="<?= e(old($old, 'event_date', $album['event_date'])) ?>"></label>
        <label>Descrição<textarea name="description" rows="3" maxlength="2000"><?= e(old($old, 'description', $album['description'])) ?></textarea></label>
        <button class="btn" type="submit">Salvar</button>
    </form>
</div>
<section class="card">
    <h2>Fotos (<?= count($photos) ?>)</h2>
    <div class="admin-photos">
        <?php foreach ($photos as $p): ?>
            <figure>
                <a href="<?= e(upload_url('photos', $p['filename'])) ?>" target="_blank"><img src="<?= e(upload_url('photos', $p['filename'], true)) ?>" alt="" loading="lazy"></a>
                <figcaption>
                    <?php if ($p['status'] === 'pending'): ?><span class="tag tag-status-pending">Pendente</span><?php endif; ?>
                    <small><?= e($p['caption']) ?> <?= $p['author'] ? '· ' . e($p['author']) : '' ?></small>
                </figcaption>
                <div class="actions">
                    <?php if ($p['status'] === 'pending'): ?>
                        <form method="post" action="/admin/galeria/fotos/<?= (int) $p['id'] ?>/aprovar" class="inline-form"><?= $csrf->field() ?><button class="btn btn-sm btn-primary" type="submit">Aprovar</button></form>
                    <?php endif; ?>
                    <form method="post" action="/admin/galeria/fotos/<?= (int) $p['id'] ?>/excluir" class="inline-form" data-confirm="Excluir esta foto?"><?= $csrf->field() ?><button class="btn btn-sm btn-danger" type="submit">Excluir</button></form>
                </div>
            </figure>
        <?php endforeach; ?>
    </div>
</section>
<form method="post" action="/admin/galeria/albuns/<?= (int) $album['id'] ?>/excluir" data-confirm="Excluir o álbum e TODAS as fotos dele?">
    <?= $csrf->field() ?>
    <button class="btn btn-danger" type="submit">Excluir álbum</button>
</form>
