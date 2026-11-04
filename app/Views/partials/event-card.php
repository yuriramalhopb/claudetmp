<?php
use App\Models\Event;
$ev = $event;
?>
<article class="event-card<?= $ev['is_own'] ? ' is-own' : '' ?>">
    <a class="event-date" href="/agenda/<?= (int) $ev['id'] ?>">
        <strong><?= date('d', strtotime($ev['start_at'])) ?></strong>
        <span><?= month_short($ev['start_at']) ?></span>
        <small><?= e(weekday_br($ev['start_at'])) ?></small>
    </a>
    <div class="event-body">
        <div class="tags">
            <?php if ($ev['is_own']): ?><span class="tag tag-ducks">🦆 Ducks</span><?php else: ?><span class="tag">Outra turma</span><?php endif; ?>
            <?php if (!empty($ev['level'])): ?><span class="tag tag-level-<?= e($ev['level']) ?>"><?= e(Event::LEVELS[$ev['level']] ?? $ev['level']) ?></span><?php endif; ?>
        </div>
        <h3><a href="/agenda/<?= (int) $ev['id'] ?>"><?= e($ev['title']) ?></a></h3>
        <p class="meta">
            📍 <?= e(trim($ev['city'] . (!empty($ev['state']) ? ' / ' . $ev['state'] : ''))) ?>
            <?php if (date('H:i', strtotime($ev['start_at'])) !== '00:00'): ?> · 🕗 <?= date('H\hi', strtotime($ev['start_at'])) ?><?php endif; ?>
            <?php if (!empty($ev['distance_km'])): ?> · <?= (int) $ev['distance_km'] ?> km<?php endif; ?>
        </p>
        <?php if (!empty($ev['price'])): ?><p class="meta">💰 <?= e($ev['price']) ?></p><?php endif; ?>
        <?php if (!empty($ev['organizer']) && !$ev['is_own']): ?><p class="meta">Organização: <?= e($ev['organizer']) ?></p><?php endif; ?>
        <?php if (isset($ev['going']) && (int) $ev['going'] > 0): ?><p class="meta going">🏍️ <?= (int) $ev['going'] ?> trilheiro(s) confirmado(s)</p><?php endif; ?>
    </div>
</article>
