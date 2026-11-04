<section class="page-head">
    <div class="container narrow">
        <h1>Sugerir trilha / evento</h1>
        <p class="lead">Viu uma trilha bacana de outra turma? Cadastre aqui. Após aprovação ela entra na agenda.</p>
    </div>
</section>
<section class="section">
    <div class="container narrow">
        <form class="card form" method="post" action="/agenda/sugerir" enctype="multipart/form-data">
            <?= $csrf->field() ?>
            <?= $view->partial('event-form-fields', ['errors' => $errors, 'old' => $old, 'event' => null]) ?>
            <button class="btn btn-primary" type="submit">Enviar sugestão</button>
        </form>
    </div>
</section>
