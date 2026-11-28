<section class="section">
    <div class="container narrow center">
        <p class="error-code"><?= (int) $status ?></p>
        <h1><?= $status === 404 ? 'Página não encontrada' : 'Algo não saiu como esperado' ?></h1>
        <p class="lead"><?= e($message) ?></p>
        <p><a class="btn btn-gold" href="/">Voltar ao início</a></p>
    </div>
</section>
