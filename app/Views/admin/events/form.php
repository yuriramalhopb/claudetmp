<p class="breadcrumb"><a href="/admin/eventos">← Eventos</a></p>
<h1><?= e($title) ?></h1>
<form class="card form" method="post" action="<?= $event ? '/admin/eventos/' . (int) $event['id'] . '/editar' : '/admin/eventos/novo' ?>" enctype="multipart/form-data">
    <?= $csrf->field() ?>
    <label class="check"><input type="checkbox" name="is_own" value="1"<?= checked(old($old, 'is_own', $event['is_own'] ?? 1)) ?>> 🦆 Trilha organizada pela Ducks Offroad</label>
    <?= $view->partial('event-form-fields', ['errors' => $errors, 'old' => $old, 'event' => $event, 'allowRemoveCover' => true]) ?>
    <button class="btn btn-primary" type="submit">Salvar</button>
</form>
