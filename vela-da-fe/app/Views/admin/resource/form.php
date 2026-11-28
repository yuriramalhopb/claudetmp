<?php
$v = $old + (array) $defaults;
$action = $row ? "/admin/{$key}/{$row['id']}" : "/admin/{$key}";
?>
<form class="panel admin-form" method="post" action="<?= e($action) ?>">
    <?= $csrf->field() ?>
    <?php foreach ($res['fields'] as $name => $f): $val = $v[$name] ?? ($f['default'] ?? ''); ?>
        <div class="field">
            <?php if ($f['type'] === 'bool'): ?>
                <label class="check"><input type="checkbox" name="<?= e($name) ?>" value="1"<?= checked($old !== [] ? !empty($old[$name]) : (bool) $val) ?>> <?= e($f['label']) ?></label>
            <?php else: ?>
                <label for="f-<?= e($name) ?>"><?= e($f['label']) ?></label>
                <?php if ($f['type'] === 'textarea'): ?>
                    <textarea id="f-<?= e($name) ?>" name="<?= e($name) ?>" rows="<?= (int) ($f['rows'] ?? 4) ?>"><?= e($val) ?></textarea>
                <?php elseif ($f['type'] === 'select'): ?>
                    <select id="f-<?= e($name) ?>" name="<?= e($name) ?>"><?php foreach ($f['options'] as $ov => $ol): ?><option value="<?= e($ov) ?>"<?= selected($val, $ov) ?>><?= e($ol) ?></option><?php endforeach; ?></select>
                <?php elseif ($f['type'] === 'money'): ?>
                    <input id="f-<?= e($name) ?>" name="<?= e($name) ?>" inputmode="decimal" value="<?= e(is_numeric($val) && !isset($old[$name]) ? cents_input((int) $val) : $val) ?>" placeholder="0,00">
                <?php elseif ($f['type'] === 'color'): ?>
                    <input id="f-<?= e($name) ?>" name="<?= e($name) ?>" type="color" value="<?= e($val) ?>">
                <?php else: ?>
                    <input id="f-<?= e($name) ?>" name="<?= e($name) ?>" type="<?= in_array($f['type'], ['number', 'date', 'time'], true) ? e($f['type']) : 'text' ?>" value="<?= e($val) ?>">
                <?php endif; ?>
            <?php endif; ?>
            <?php if (!empty($f['help'])): ?><p class="field-help"><?= e($f['help']) ?></p><?php endif; ?>
            <?= field_error($errors, $name) ?>
        </div>
    <?php endforeach; ?>
    <button class="btn btn-gold" type="submit">Salvar</button> <a class="btn btn-ghost-dark" href="/admin/<?= e($key) ?>">Cancelar</a>
</form>
