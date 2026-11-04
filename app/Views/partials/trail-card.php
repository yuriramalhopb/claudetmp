<?php
use App\Models\Event;
$ev = $event;
$past = strtotime($ev['end_at'] ?: $ev['start_at']) < strtotime('today');
$banner = upload_url('events', $ev['cover'], true);
$regClosed = !empty($ev['registration_until']) && strtotime($ev['registration_until'] . ' 23:59:59') < time();
?>
<article class="trail-card<?= $ev['is_own'] ? ' is-own' : '' ?><?= $past ? ' is-past' : '' ?>">
    <a class="trail-banner" href="/agenda/<?= (int) $ev['id'] ?>" tabindex="-1" aria-hidden="true">
        <?php if ($banner): ?>
            <img src="<?= e($banner) ?>" alt="" loading="lazy">
        <?php else: ?>
            <span class="trail-placeholder"><strong><?= date('d', strtotime($ev['start_at'])) ?></strong><?= month_short($ev['start_at']) ?></span>
        <?php endif; ?>
        <span class="trail-date"><strong><?= date('d', strtotime($ev['start_at'])) ?></strong><?= month_short($ev['start_at']) ?></span>
        <?php if ($past): ?><span class="trail-flag">Realizada</span><?php elseif ($regClosed): ?><span class="trail-flag">Inscrições encerradas</span><?php endif; ?>
    </a>
    <div class="trail-body">
        <div class="tags">
            <?php if ($ev['is_own']): ?><span class="tag tag-ducks">🦆 Ducks</span><?php endif; ?>
            <?php if (!empty($ev['category'])): ?><span class="tag"><?= e(Event::CATEGORIES[$ev['category']] ?? $ev['category']) ?></span><?php endif; ?>
            <?php if (!empty($ev['level'])): ?><span class="tag tag-level-<?= e($ev['level']) ?>"><?= e(Event::LEVELS[$ev['level']] ?? '') ?></span><?php endif; ?>
        </div>
        <h3><a href="/agenda/<?= (int) $ev['id'] ?>"><?= e($ev['title']) ?></a></h3>
        <ul class="trail-facts">
            <li>📅 <?= e(ucfirst(weekday_br($ev['start_at']))) ?>, <?= e(date('d/m/Y', strtotime($ev['start_at']))) ?><?php if (date('H:i', strtotime($ev['start_at'])) !== '00:00'): ?> · <?= date('H\hi', strtotime($ev['start_at'])) ?><?php endif; ?>
                <?php if ($ev['end_at'] && date('Y-m-d', strtotime($ev['end_at'])) !== date('Y-m-d', strtotime($ev['start_at']))): ?> a <?= e(date('d/m', strtotime($ev['end_at']))) ?><?php endif; ?></li>
            <li>📍 <?= e(trim($ev['city'] . (!empty($ev['state']) ? ' / ' . $ev['state'] : ''))) ?></li>
            <li>💰 <?= e($ev['price'] ?: 'Valor a confirmar') ?></li>
            <?php if (!empty($ev['organizer']) && !$ev['is_own']): ?><li>👥 <?= e($ev['organizer']) ?></li><?php endif; ?>
            <?php if (!empty($ev['distance_km'])): ?><li>🛣️ <?= (int) $ev['distance_km'] ?> km</li><?php endif; ?>
            <?php if (!empty($ev['registration_until']) && !$past): ?><li>📝 Inscrições até <?= e(date('d/m', strtotime($ev['registration_until']))) ?></li><?php endif; ?>
        </ul>
        <div class="trail-actions">
            <a class="btn btn-sm" href="/agenda/<?= (int) $ev['id'] ?>">Detalhes</a>
            <?php if (!empty($ev['instagram_url'])): ?>
                <a class="btn btn-sm btn-ghost" href="<?= e($ev['instagram_url']) ?>" target="_blank" rel="noopener noreferrer nofollow">📸 Instagram</a>
            <?php endif; ?>
            <?php if (!empty($ev['external_link']) && !$past): ?>
                <a class="btn btn-sm btn-ghost" href="<?= e($ev['external_link']) ?>" target="_blank" rel="noopener noreferrer nofollow">Inscrição ↗</a>
            <?php endif; ?>
        </div>
    </div>
</article>
