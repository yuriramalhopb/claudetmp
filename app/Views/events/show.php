<?php
use App\Models\Event;
use App\Services\Calendar;
$past = strtotime($event['end_at'] ?: $event['start_at']) < strtotime('today');
$banner = upload_url('events', $event['cover']);
$regClosed = !empty($event['registration_until']) && strtotime($event['registration_until'] . ' 23:59:59') < time();
$full = !empty($event['capacity']) && count($attendees) >= (int) $event['capacity'];
?>
<section class="page-head">
    <div class="container">
        <p class="breadcrumb"><a href="/agenda">Agenda</a> / <a href="/trilhas-do-ano/<?= date('Y', strtotime($event['start_at'])) ?>">Trilhas de <?= date('Y', strtotime($event['start_at'])) ?></a> /</p>
        <div class="tags">
            <?php if ($event['is_own']): ?><span class="tag tag-ducks">🦆 Trilha da Ducks</span><?php else: ?><span class="tag">Outra turma</span><?php endif; ?>
            <?php if ($event['category']): ?><span class="tag"><?= e(Event::CATEGORIES[$event['category']] ?? '') ?></span><?php endif; ?>
            <?php if ($event['level']): ?><span class="tag tag-level-<?= e($event['level']) ?>">Nível <?= e(Event::LEVELS[$event['level']] ?? '') ?></span><?php endif; ?>
            <?php if ($past): ?><span class="tag">Realizada</span><?php elseif ($regClosed): ?><span class="tag tag-status-blocked">Inscrições encerradas</span><?php endif; ?>
        </div>
        <h1><?= e($event['title']) ?></h1>
        <p class="lead">📅 <?= e(ucfirst(weekday_br($event['start_at']))) ?>, <?= e(date_br($event['start_at'], true)) ?> · 📍 <?= e(trim($event['city'] . ($event['state'] ? ' / ' . $event['state'] : ''))) ?></p>
    </div>
</section>

<section class="section">
    <div class="container two-col">
        <div>
            <?php if ($banner): ?>
                <div class="event-banner" data-gallery>
                    <a href="<?= e($banner) ?>" data-lb-item data-caption="<?= e($event['title']) ?>">
                        <img src="<?= e($banner) ?>" alt="Banner do evento <?= e($event['title']) ?>">
                    </a>
                </div>
            <?php endif; ?>

            <dl class="info-list">
                <div><dt>Quando</dt><dd><?= e(ucfirst(weekday_br($event['start_at']))) ?>, <?= e(date_br($event['start_at'], true)) ?>
                    <?php if ($event['end_at']): ?><br><small>até <?= e(date_br($event['end_at'], true)) ?></small><?php endif; ?></dd></div>
                <div><dt>Onde</dt><dd><?= e(Calendar::place($event)) ?>
                    <?php if ($event['state']): ?><br><small><?= e(Event::STATES[$event['state']] ?? '') ?></small><?php endif; ?>
                    <?php if ($mapsLink): ?><br><a href="<?= e($mapsLink) ?>" target="_blank" rel="noopener noreferrer">Abrir no mapa ↗</a><?php endif; ?></dd></div>
                <div><dt>Valor</dt><dd><?= e($event['price'] ?: 'A confirmar') ?></dd></div>
                <?php if ($event['registration_until']): ?><div><dt>Inscrições até</dt><dd><?= e(date_br($event['registration_until'])) ?><?= $regClosed ? ' <span class="tag tag-status-blocked">encerradas</span>' : '' ?></dd></div><?php endif; ?>
                <?php if ($event['capacity']): ?><div><dt>Vagas</dt><dd><?= (int) $event['capacity'] ?><?php if ($event['is_own']): ?> <small class="muted">(<?= count($attendees) ?> confirmados no site)</small><?php endif; ?></dd></div><?php endif; ?>
                <?php if ($event['includes']): ?><div><dt>Incluso</dt><dd><?= nl2br_e($event['includes']) ?></dd></div><?php endif; ?>
                <?php if ($event['organizer']): ?><div><dt>Organização</dt><dd><?= e($event['organizer']) ?></dd></div><?php endif; ?>
                <?php if ($event['distance_km']): ?><div><dt>Distância</dt><dd><?= (int) $event['distance_km'] ?> km</dd></div><?php endif; ?>
                <?php if ($event['category']): ?><div><dt>Categoria</dt><dd><?= e(Event::CATEGORIES[$event['category']] ?? '') ?></dd></div><?php endif; ?>
                <?php if ($wa = whatsapp_link($event['whatsapp'])): ?><div><dt>Contato</dt><dd><a href="<?= e($wa) ?>" target="_blank" rel="noopener noreferrer">WhatsApp <?= e($event['whatsapp']) ?> ↗</a></dd></div><?php endif; ?>
            </dl>

            <?php if ($event['description']): ?>
                <div class="prose"><?= linkify($event['description']) ?></div>
            <?php endif; ?>

            <div class="actions">
                <?php if ($event['external_link'] && !$past): ?>
                    <a class="btn btn-primary" href="<?= e($event['external_link']) ?>" target="_blank" rel="noopener noreferrer nofollow">Inscrição / site ↗</a>
                <?php endif; ?>
                <?php if ($event['instagram_url']): ?>
                    <a class="btn" href="<?= e($event['instagram_url']) ?>" target="_blank" rel="noopener noreferrer nofollow">📸 Instagram do evento</a>
                <?php endif; ?>
                <a class="btn btn-ghost" href="/agenda/<?= (int) $event['id'] ?>/calendario.ics">📅 Adicionar à agenda</a>
                <a class="btn btn-ghost" href="<?= e($googleLink) ?>" target="_blank" rel="noopener noreferrer">Google Agenda</a>
                <button class="btn btn-ghost" type="button" data-share data-title="<?= e($event['title']) ?>" data-url="<?= e($shareUrl) ?>">Compartilhar</button>
                <a class="btn btn-ghost" href="https://wa.me/?text=<?= rawurlencode($event['title'] . ' — ' . date_br($event['start_at']) . ' em ' . $event['city'] . ($event['state'] ? '/' . $event['state'] : '') . ' ' . $shareUrl) ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a>
            </div>
        </div>

        <aside class="card">
            <h3>Quem vai 🏍️</h3>
            <?php if (!$past): ?>
                <?php if ($auth->isApproved()): ?>
                    <?php if ($full && !$going): ?>
                        <p class="hint">Vagas esgotadas.</p>
                    <?php else: ?>
                    <form method="post" action="/agenda/<?= (int) $event['id'] ?>/presenca">
                        <?= $csrf->field() ?>
                        <button class="btn <?= $going ? 'btn-ghost' : 'btn-primary' ?> btn-block" type="submit"><?= $going ? 'Cancelar minha presença' : 'Eu vou!' ?></button>
                    </form>
                    <?php endif; ?>
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
