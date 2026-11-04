<?php use App\Models\Event; $past = strtotime($event['end_at'] ?: $event['start_at']) < strtotime('today'); ?>
<section class="page-head event-head">
    <?php if ($cover = upload_url('events', $event['cover'])): ?>
        <img class="event-cover" src="<?= e($cover) ?>" alt="">
    <?php endif; ?>
    <div class="container">
        <p class="breadcrumb"><a href="/agenda">Agenda</a> /</p>
        <div class="tags">
            <?php if ($event['is_own']): ?><span class="tag tag-ducks">🦆 Trilha da Ducks</span><?php else: ?><span class="tag">Outra turma</span><?php endif; ?>
            <?php if ($event['level']): ?><span class="tag tag-level-<?= e($event['level']) ?>">Nível <?= e(Event::LEVELS[$event['level']] ?? '') ?></span><?php endif; ?>
            <?php if ($past): ?><span class="tag">Encerrado</span><?php endif; ?>
        </div>
        <h1><?= e($event['title']) ?></h1>
    </div>
</section>

<section class="section">
    <div class="container two-col">
        <div>
            <dl class="info-list">
                <div><dt>Quando</dt><dd><?= e(ucfirst(weekday_br($event['start_at']))) ?>, <?= e(date_br($event['start_at'], true)) ?>
                    <?php if ($event['end_at']): ?><br><small>até <?= e(date_br($event['end_at'], true)) ?></small><?php endif; ?></dd></div>
                <div><dt>Onde</dt><dd><?= e(trim(($event['location'] ? $event['location'] . ' — ' : '') . $event['city'])) ?>
                    <?php if ($mapsLink): ?><br><a href="<?= e($mapsLink) ?>" target="_blank" rel="noopener noreferrer">Abrir no mapa ↗</a><?php endif; ?></dd></div>
                <?php if ($event['organizer']): ?><div><dt>Organização</dt><dd><?= e($event['organizer']) ?></dd></div><?php endif; ?>
                <?php if ($event['distance_km']): ?><div><dt>Distância</dt><dd><?= (int) $event['distance_km'] ?> km</dd></div><?php endif; ?>
                <?php if ($event['price']): ?><div><dt>Valor</dt><dd><?= e($event['price']) ?></dd></div><?php endif; ?>
            </dl>

            <?php if ($event['description']): ?>
                <div class="prose"><?= linkify($event['description']) ?></div>
            <?php endif; ?>

            <div class="actions">
                <?php if ($event['external_link']): ?>
                    <a class="btn" href="<?= e($event['external_link']) ?>" target="_blank" rel="noopener noreferrer nofollow">Inscrição / mais informações ↗</a>
                <?php endif; ?>
                <a class="btn btn-ghost" href="/agenda/<?= (int) $event['id'] ?>/calendario.ics">📅 Adicionar à agenda</a>
                <a class="btn btn-ghost" href="<?= e($googleLink) ?>" target="_blank" rel="noopener noreferrer">Google Agenda</a>
                <button class="btn btn-ghost" type="button" data-share data-title="<?= e($event['title']) ?>" data-url="<?= e($shareUrl) ?>">Compartilhar</button>
                <a class="btn btn-ghost" href="https://wa.me/?text=<?= rawurlencode($event['title'] . ' — ' . date_br($event['start_at']) . ' ' . $shareUrl) ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a>
            </div>
        </div>

        <aside class="card">
            <h3>Quem vai 🏍️</h3>
            <?php if (!$past): ?>
                <?php if ($auth->isApproved()): ?>
                    <form method="post" action="/agenda/<?= (int) $event['id'] ?>/presenca">
                        <?= $csrf->field() ?>
                        <button class="btn <?= $going ? 'btn-ghost' : 'btn-primary' ?> btn-block" type="submit"><?= $going ? 'Cancelar minha presença' : 'Eu vou!' ?></button>
                    </form>
                <?php elseif ($auth->check()): ?>
                    <p class="hint">Seu cadastro precisa ser aprovado para confirmar presença.</p>
                <?php else: ?>
                    <p class="hint"><a href="/entrar">Entre</a> ou <a href="/cadastro">cadastre-se</a> para confirmar presença.</p>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($attendees): ?>
                <ul class="attendees">
                    <?php foreach ($attendees as $a): ?>
                        <li><a href="/trilheiros/<?= (int) $a['id'] ?>"><img src="<?= e(avatar_url($a)) ?>" alt="" loading="lazy"><span><?= e(display_name($a)) ?><?php if ($a['bike_model']): ?><small><?= e($a['bike_model']) ?></small><?php endif; ?></span></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="muted">Ninguém confirmou ainda. Seja o primeiro!</p>
            <?php endif; ?>
        </aside>
    </div>
</section>
