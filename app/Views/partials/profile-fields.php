<?php use App\Models\User; $u = $user ?? []; $v = fn (string $k) => old($old, $k, $u[$k] ?? ''); ?>
<fieldset>
    <legend>Dados pessoais</legend>
    <div class="grid-2">
        <label>Nome completo *
            <input type="text" name="name" required maxlength="120" autocomplete="name" value="<?= e($v('name')) ?>">
            <?= field_error($errors, 'name') ?>
        </label>
        <label>Apelido na trilha
            <input type="text" name="nickname" maxlength="60" value="<?= e($v('nickname')) ?>">
        </label>
    </div>
    <div class="grid-3">
        <label>Celular / WhatsApp *
            <input type="tel" name="phone" required maxlength="20" autocomplete="tel" placeholder="(11) 99999-9999" value="<?= e($v('phone')) ?>">
            <?= field_error($errors, 'phone') ?>
        </label>
        <label>Cidade *
            <input type="text" name="city" required maxlength="80" value="<?= e($v('city')) ?>">
            <?= field_error($errors, 'city') ?>
        </label>
        <label>Data de nascimento
            <input type="date" name="birth_date" value="<?= e($v('birth_date')) ?>">
            <?= field_error($errors, 'birth_date') ?>
        </label>
    </div>
    <div class="grid-2">
        <label>Instagram
            <input type="text" name="instagram" maxlength="31" placeholder="@seuusuario" value="<?= e($v('instagram')) ?>">
            <?= field_error($errors, 'instagram') ?>
        </label>
        <label class="check check-inline">
            <input type="checkbox" name="show_phone" value="1"<?= checked(old($old, 'show_phone', $u['show_phone'] ?? '')) ?>>
            Mostrar meu WhatsApp para outros trilheiros logados
        </label>
    </div>
</fieldset>
<fieldset>
    <legend>Moto e experiência</legend>
    <div class="grid-4">
        <label>Marca
            <input type="text" name="bike_brand" maxlength="60" list="marcas" value="<?= e($v('bike_brand')) ?>">
            <datalist id="marcas"><option value="Honda"><option value="Yamaha"><option value="KTM"><option value="Husqvarna"><option value="Kawasaki"><option value="Suzuki"><option value="Beta"><option value="GasGas"><option value="Sherco"><option value="TM"></datalist>
        </label>
        <label>Modelo
            <input type="text" name="bike_model" maxlength="80" placeholder="Ex.: CRF 250F" value="<?= e($v('bike_model')) ?>">
        </label>
        <label>Cilindrada (cc)
            <input type="number" name="bike_cc" min="50" max="2500" value="<?= e($v('bike_cc')) ?>">
            <?= field_error($errors, 'bike_cc') ?>
        </label>
        <label>Ano
            <input type="number" name="bike_year" min="1950" max="2100" value="<?= e($v('bike_year')) ?>">
            <?= field_error($errors, 'bike_year') ?>
        </label>
    </div>
    <label>Nível na trilha *
        <select name="experience" required>
            <?php foreach (User::EXPERIENCE as $k => $label): ?><option value="<?= e($k) ?>"<?= selected($k, $v('experience') ?: 'iniciante') ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
    </label>
    <label>Sobre você
        <textarea name="bio" rows="3" maxlength="1000" placeholder="Há quanto tempo anda de trilha, trilhas favoritas…"><?= e($v('bio')) ?></textarea>
        <?= field_error($errors, 'bio') ?>
    </label>
</fieldset>
<fieldset>
    <legend>Segurança na trilha <small>(visível apenas para a administração)</small></legend>
    <div class="grid-3">
        <label>Tipo sanguíneo
            <select name="blood_type">
                <option value="">Não sei / prefiro não informar</option>
                <?php foreach (User::BLOOD as $b): ?><option value="<?= e($b) ?>"<?= selected($b, $v('blood_type')) ?>><?= e($b) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label>Contato de emergência
            <input type="text" name="emergency_name" maxlength="120" value="<?= e($v('emergency_name')) ?>">
        </label>
        <label>Telefone de emergência
            <input type="tel" name="emergency_phone" maxlength="20" value="<?= e($v('emergency_phone')) ?>">
            <?= field_error($errors, 'emergency_phone') ?>
        </label>
    </div>
</fieldset>
