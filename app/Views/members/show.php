<?php use App\Models\User; $m = $member; ?>
<section class="section">
    <div class="container narrow">
        <p class="breadcrumb"><a href="/trilheiros">Trilheiros</a> /</p>
        <div class="profile card">
            <img class="profile-photo" src="<?= e(avatar_url($m)) ?>" alt="">
            <div>
                <h1><?= e(display_name($m)) ?></h1>
                <?php if ($m['nickname']): ?><p class="muted"><?= e($m['name']) ?></p><?php endif; ?>
                <span class="tag tag-exp-<?= e($m['experience']) ?>"><?= e(User::EXPERIENCE[$m['experience']] ?? '') ?></span>
                <dl class="info-list compact">
                    <?php if ($m['bike_model'] || $m['bike_brand']): ?><div><dt>Moto</dt><dd><?= e(trim($m['bike_brand'] . ' ' . $m['bike_model'])) ?><?= $m['bike_cc'] ? ' ' . (int) $m['bike_cc'] . 'cc' : '' ?><?= $m['bike_year'] ? ' (' . (int) $m['bike_year'] . ')' : '' ?></dd></div><?php endif; ?>
                    <?php if ($m['city']): ?><div><dt>Cidade</dt><dd><?= e($m['city']) ?></dd></div><?php endif; ?>
                    <div><dt>Na turma desde</dt><dd><?= e(date_br($m['created_at'])) ?></dd></div>
                    <?php if ($m['instagram']): ?><div><dt>Instagram</dt><dd><a href="https://www.instagram.com/<?= e($m['instagram']) ?>/" target="_blank" rel="noopener noreferrer">@<?= e($m['instagram']) ?></a></dd></div><?php endif; ?>
                    <?php if ($showPhone && ($wa = whatsapp_link($m['phone']))): ?><div><dt>WhatsApp</dt><dd><a href="<?= e($wa) ?>" target="_blank" rel="noopener noreferrer"><?= e($m['phone']) ?></a></dd></div><?php endif; ?>
                </dl>
                <?php if ($m['bio']): ?><p class="prose"><?= nl2br_e($m['bio']) ?></p><?php endif; ?>
            </div>
        </div>
        <div class="member-stats">
            <div><strong><?= (int) $stats['trails'] ?></strong><span>trilhas com a turma</span></div>
            <div><strong><?= e(number_format($stats['km'], 0, ',', '.')) ?></strong><span>km rodados</span></div>
            <div><strong><?= count($badges) ?></strong><span>selos</span></div>
        </div>
        <?php if ($badges): ?>
            <ul class="badges">
                <?php foreach ($badges as $b): ?><li title="<?= e($b['desc']) ?>"><span><?= e($b['icon']) ?></span><?= e($b['name']) ?></li><?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if (!empty($taggedPhotos)): ?>
            <h2>Aparece nas fotos 📸</h2>
            <div class="photo-grid small">
                <?php foreach ($taggedPhotos as $tp): ?>
                    <a class="photo" href="/fotos/<?= (int) $tp['id'] ?>"><img src="<?= e(upload_url('photos', $tp['filename'], true)) ?>" alt="<?= e($tp['caption'] ?: 'Foto') ?>" loading="lazy"></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($agenda): ?>
            <h2>Próximas trilhas confirmadas</h2>
            <div class="event-grid">
                <?php foreach ($agenda as $event): ?><?= $view->partial('event-card', ['event' => $event]) ?><?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
