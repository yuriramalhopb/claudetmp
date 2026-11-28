<form class="admin-form" method="post" action="/admin/configuracoes">
    <?= $csrf->field() ?>
    <?php foreach ($groups as $group => $defs): ?>
    <fieldset class="panel">
        <legend><h2><?= e($group) ?></h2></legend>
        <?php foreach ($defs as $key => $d): $val = $old[$key] ?? $values[$key] ?? ''; ?>
            <div class="field">
                <?php if ($d['type'] === 'bool'): ?>
                    <label class="check"><input type="checkbox" name="<?= e($key) ?>" value="1"<?= checked((string) $val === '1') ?>> <?= e($d['label']) ?></label>
                <?php else: ?>
                    <label for="s-<?= e($key) ?>"><?= e($d['label']) ?></label>
                    <?php if ($d['type'] === 'textarea'): ?>
                        <textarea id="s-<?= e($key) ?>" name="<?= e($key) ?>" rows="3" maxlength="5000"><?= e($val) ?></textarea>
                    <?php elseif ($d['type'] === 'select'): ?>
                        <select id="s-<?= e($key) ?>" name="<?= e($key) ?>"><?php foreach ($d['options'] as $ov => $ol): ?><option value="<?= e($ov) ?>"<?= selected($val, $ov) ?>><?= e($ol) ?></option><?php endforeach; ?></select>
                    <?php else: ?>
                        <input id="s-<?= e($key) ?>" name="<?= e($key) ?>" type="<?= in_array($d['type'], ['email', 'url', 'number'], true) ? e($d['type']) : 'text' ?>" value="<?= e($val) ?>" maxlength="250"<?= $d['type'] === 'number' ? ' min="1" max="100"' : '' ?>>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (!empty($d['help'])): ?><p class="field-help"><?= e($d['help']) ?></p><?php endif; ?>
                <?= field_error($errors, $key) ?>
            </div>
        <?php endforeach; ?>
    </fieldset>
    <?php endforeach; ?>
    <button class="btn btn-gold btn-lg" type="submit">Salvar configurações</button>
</form>
