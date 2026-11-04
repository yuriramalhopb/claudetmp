<section class="hero">
    <div class="hero-bg" aria-hidden="true"></div>
    <div class="container hero-content">
        <p class="eyebrow">Turma de trilha de moto</p>
        <h1>Ducks <span>Offroad</span></h1>
        <p class="lead">Barro na roda, poeira na viseira e a turma sempre junta. Confira a agenda de trilhas, as fotos dos rolês e faça parte dos trilheiros.</p>
        <div class="hero-actions">
            <a class="btn btn-primary btn-lg" href="/agenda">Ver próximas trilhas</a>
            <?php if (!$auth->check()): ?>
                <a class="btn btn-ghost btn-lg" href="/cadastro">Cadastrar como trilheiro</a>
            <?php else: ?>
                <a class="btn btn-ghost btn-lg" href="/galeria">Ver galeria</a>
            <?php endif; ?>
        </div>
        <dl class="hero-stats">
            <div><dt>Trilheiros</dt><dd><?= (int) $stats['members'] ?></dd></div>
            <div><dt>Trilhas da turma</dt><dd><?= (int) $stats['events'] ?></dd></div>
            <div><dt>Fotos</dt><dd><?= (int) $stats['photos'] ?></dd></div>
        </dl>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head">
            <h2>Próximas trilhas</h2>
            <a class="link-more" href="/agenda">Agenda completa →</a>
        </div>
        <?php if ($events): ?>
            <div class="event-grid">
                <?php foreach ($events as $event): ?><?= $view->partial('event-card', ['event' => $event]) ?><?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="empty">Nenhuma trilha agendada no momento. Fique de olho no nosso Instagram!</p>
        <?php endif; ?>
    </div>
</section>

<section class="section section-alt">
    <div class="container">
        <div class="section-head">
            <h2>Direto do Instagram</h2>
            <a class="link-more" href="https://www.instagram.com/<?= e($igUsername) ?>/" target="_blank" rel="noopener noreferrer">@<?= e($igUsername) ?> →</a>
        </div>
        <?php if ($posts): ?>
            <div class="ig-grid">
                <?php foreach ($posts as $post): ?><?= $view->partial('ig-post', ['post' => $post]) ?><?php endforeach; ?>
            </div>
            <p class="center"><a class="btn btn-ghost" href="/instagram">Ver todas as publicações</a></p>
        <?php else: ?>
            <p class="empty">As publicações do Instagram aparecerão aqui em breve.</p>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head">
            <h2>Últimas fotos</h2>
            <a class="link-more" href="/galeria">Galeria completa →</a>
        </div>
        <?php if ($photos): ?>
            <div class="photo-grid" data-gallery>
                <?php foreach ($photos as $p): ?>
                    <a class="photo" href="<?= e(upload_url('photos', $p['filename'])) ?>" data-lb-item data-caption="<?= e($p['caption'] ?: $p['album_title']) ?>">
                        <img src="<?= e(upload_url('photos', $p['filename'], true)) ?>" alt="<?= e($p['caption'] ?: 'Foto da trilha') ?>" loading="lazy">
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="empty">Nenhuma foto publicada ainda.</p>
        <?php endif; ?>
    </div>
</section>

<?php if ($newMembers): ?>
<section class="section section-alt">
    <div class="container">
        <div class="section-head">
            <h2>Chegaram na turma</h2>
            <a class="link-more" href="/trilheiros">Todos os trilheiros →</a>
        </div>
        <div class="member-strip">
            <?php foreach ($newMembers as $m): ?>
                <a href="/trilheiros/<?= (int) $m['id'] ?>" class="member-chip">
                    <img src="<?= e(avatar_url($m)) ?>" alt="" loading="lazy">
                    <span><?= e(display_name($m)) ?></span>
                    <?php if ($m['bike_model']): ?><small><?= e($m['bike_model']) ?></small><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section cta">
    <div class="container narrow center">
        <h2>Bora pra trilha?</h2>
        <p class="lead">Faça seu cadastro de trilheiro, confirme presença nas trilhas e envie suas fotos para a galeria.</p>
        <a class="btn btn-primary btn-lg" href="<?= $auth->check() ? '/minha-conta' : '/cadastro' ?>"><?= $auth->check() ? 'Minha conta' : 'Quero me cadastrar' ?></a>
    </div>
</section>
