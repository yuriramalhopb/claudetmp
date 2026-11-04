<section class="page-head">
    <div class="container narrow">
        <h1>Enviar fotos</h1>
        <p class="lead">Mande suas fotos das trilhas! Elas aparecem na galeria depois da aprovação da administração.</p>
    </div>
</section>
<section class="section">
    <div class="container narrow">
        <form class="card form" method="post" action="/galeria/enviar" enctype="multipart/form-data">
            <?= $csrf->field() ?>
            <label>Álbum
                <select name="album_id" required>
                    <option value="">Escolha…</option>
                    <?php foreach ($albums as $a): ?>
                        <option value="<?= (int) $a['id'] ?>"<?= selected($a['id'], $selected) ?>><?= e($a['title']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= field_error($errors, 'album_id') ?>
            </label>
            <label>Fotos (até 10 por vez, JPG/PNG/WEBP, máx. 8 MB cada)
                <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple required>
                <?= field_error($errors, 'photos') ?>
            </label>
            <label>Legenda (opcional)
                <input type="text" name="caption" maxlength="255" value="<?= e(old($old, 'caption')) ?>">
            </label>
            <p class="hint">🔒 Por segurança, removemos automaticamente os dados de localização (GPS) das fotos.</p>
            <button class="btn btn-primary" type="submit">Enviar fotos</button>
        </form>
    </div>
</section>
