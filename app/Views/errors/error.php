<section class="section">
    <div class="container narrow center">
        <p class="error-code"><?= (int) $status ?></p>
        <h1><?= $status === 404 ? 'Atolou! Página não encontrada' : 'Ops, algo deu errado' ?></h1>
        <p class="lead"><?= e($message) ?></p>
        <a class="btn btn-primary" href="/">Voltar ao início</a>
    </div>
</section>
