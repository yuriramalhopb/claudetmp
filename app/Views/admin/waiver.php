<h1>Termo de responsabilidade</h1>
<p class="muted">Este texto aparece para o trilheiro aceitar ao confirmar presença nas trilhas que exigem termo. Cada aceite registra data, hora, IP e a versão exata do texto.</p>
<form class="card form" method="post" action="/admin/termo">
    <?= $csrf->field() ?>
    <label>Texto do termo
        <textarea name="waiver_text" rows="22" maxlength="10000" required><?= e(old($old, 'waiver_text', $text)) ?></textarea>
        <?= field_error($errors, 'waiver_text') ?>
    </label>
    <p class="hint">Dica: peça para um advogado revisar o texto da sua turma.</p>
    <button class="btn btn-primary" type="submit">Salvar termo</button>
</form>
