<?php
use App\Models\Event;
$ev = $event ?? [];
$v = fn (string $k) => old($old, $k, $ev[$k] ?? '');
?>
<fieldset>
    <legend>A trilha</legend>
    <label>Nome do evento *
        <input type="text" name="title" required maxlength="150" placeholder="Ex.: 5ª Trilha Solidária dos Lobos" value="<?= e($v('title')) ?>">
        <?= field_error($errors, 'title') ?>
    </label>
    <div class="grid-3">
        <label>Categoria
            <select name="category">
                <option value="">—</option>
                <?php foreach (Event::CATEGORIES as $k => $label): ?><option value="<?= e($k) ?>"<?= selected($k, $v('category') ?: 'trilha') ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
            <?= field_error($errors, 'category') ?>
        </label>
        <label>Nível
            <select name="level">
                <option value="">—</option>
                <?php foreach (Event::LEVELS as $k => $label): ?><option value="<?= e($k) ?>"<?= selected($k, $v('level')) ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label>Distância (km)
            <input type="number" name="distance_km" min="0" max="5000" value="<?= e($v('distance_km')) ?>">
            <?= field_error($errors, 'distance_km') ?>
        </label>
    </div>
    <label>Organizador / turma
        <input type="text" name="organizer" maxlength="120" value="<?= e($v('organizer')) ?>">
    </label>
</fieldset>

<fieldset>
    <legend>Data e local</legend>
    <div class="grid-2">
        <label>Início *
            <input type="datetime-local" name="start_at" required value="<?= e(old($old, 'start_at', dt_input($ev['start_at'] ?? null))) ?>">
            <?= field_error($errors, 'start_at') ?>
        </label>
        <label>Término
            <input type="datetime-local" name="end_at" value="<?= e(old($old, 'end_at', dt_input($ev['end_at'] ?? null))) ?>">
            <?= field_error($errors, 'end_at') ?>
        </label>
    </div>
    <div class="grid-3">
        <label>Cidade *
            <input type="text" name="city" required maxlength="80" value="<?= e($v('city')) ?>">
            <?= field_error($errors, 'city') ?>
        </label>
        <label>Estado *
            <select name="state" required>
                <option value="">UF…</option>
                <?php foreach (Event::STATES as $uf => $name): ?><option value="<?= e($uf) ?>"<?= selected($uf, $v('state')) ?>><?= e($uf . ' — ' . $name) ?></option><?php endforeach; ?>
            </select>
            <?= field_error($errors, 'state') ?>
        </label>
        <label>Local de encontro / largada
            <input type="text" name="location" maxlength="200" placeholder="Ex.: Parque de Exposições" value="<?= e($v('location')) ?>">
        </label>
    </div>
</fieldset>

<fieldset>
    <legend>Inscrição e valores</legend>
    <div class="grid-3">
        <label>Valor
            <input type="text" name="price" maxlength="60" placeholder="Ex.: R$ 80 ou Gratuito" value="<?= e($v('price')) ?>">
            <?= field_error($errors, 'price') ?>
        </label>
        <label>Inscrições até
            <input type="date" name="registration_until" value="<?= e($v('registration_until')) ?>">
            <?= field_error($errors, 'registration_until') ?>
        </label>
        <label>Vagas
            <input type="number" name="capacity" min="1" max="100000" value="<?= e($v('capacity')) ?>">
            <?= field_error($errors, 'capacity') ?>
        </label>
    </div>
    <label>O que está incluso
        <textarea name="includes" rows="2" maxlength="2000" placeholder="Ex.: café da manhã, almoço, camiseta, seguro, resgate"><?= e($v('includes')) ?></textarea>
        <?= field_error($errors, 'includes') ?>
    </label>
</fieldset>

<fieldset>
    <legend>Divulgação e contato</legend>
    <div class="grid-2">
        <label>Instagram do evento
            <input type="url" name="instagram_url" maxlength="300" placeholder="https://www.instagram.com/p/… ou perfil do evento" value="<?= e($v('instagram_url')) ?>">
            <?= field_error($errors, 'instagram_url') ?>
        </label>
        <label>Link de inscrição / site
            <input type="url" name="external_link" maxlength="500" placeholder="https://" value="<?= e($v('external_link')) ?>">
            <?= field_error($errors, 'external_link') ?>
        </label>
    </div>
    <label>WhatsApp da organização
        <input type="tel" name="whatsapp" maxlength="20" placeholder="(11) 99999-9999" value="<?= e($v('whatsapp')) ?>">
        <?= field_error($errors, 'whatsapp') ?>
    </label>
    <label>Banner / flyer do evento
        <?php if (!empty($ev['cover']) && ($c = upload_url('events', $ev['cover'], true))): ?>
            <img class="cover-preview" src="<?= e($c) ?>" alt="Banner atual">
            <?php if (!empty($allowRemoveCover)): ?><span class="check"><input type="checkbox" name="remove_cover" value="1"> Remover banner atual</span><?php endif; ?>
        <?php endif; ?>
        <input type="file" name="cover" accept="image/jpeg,image/png,image/webp">
        <small class="hint">JPG, PNG ou WEBP até 8 MB. Pode ser o flyer do Instagram (formato retrato).</small>
        <?= field_error($errors, 'cover') ?>
    </label>
    <label>Descrição / programação
        <textarea name="description" rows="6" maxlength="5000"><?= e($v('description')) ?></textarea>
        <?= field_error($errors, 'description') ?>
    </label>
</fieldset>
