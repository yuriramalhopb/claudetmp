<?php use App\Models\Event; $ev = $event ?? []; $v = fn (string $k) => old($old, $k, $ev[$k] ?? ''); ?>
<label>Título *
    <input type="text" name="title" required maxlength="150" value="<?= e($v('title')) ?>">
    <?= field_error($errors, 'title') ?>
</label>
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
<div class="grid-2">
    <label>Local de encontro / largada
        <input type="text" name="location" maxlength="200" value="<?= e($v('location')) ?>">
    </label>
    <label>Cidade *
        <input type="text" name="city" required maxlength="80" value="<?= e($v('city')) ?>">
        <?= field_error($errors, 'city') ?>
    </label>
</div>
<div class="grid-3">
    <label>Organizador / turma
        <input type="text" name="organizer" maxlength="120" value="<?= e($v('organizer')) ?>">
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
<div class="grid-2">
    <label>Valor / inscrição
        <input type="text" name="price" maxlength="60" placeholder="Ex.: Gratuito, R$ 80 com almoço" value="<?= e($v('price')) ?>">
    </label>
    <label>Link (inscrição, post, grupo)
        <input type="url" name="external_link" maxlength="500" placeholder="https://" value="<?= e($v('external_link')) ?>">
        <?= field_error($errors, 'external_link') ?>
    </label>
</div>
<label>Descrição
    <textarea name="description" rows="6" maxlength="5000"><?= e($v('description')) ?></textarea>
    <?= field_error($errors, 'description') ?>
</label>
