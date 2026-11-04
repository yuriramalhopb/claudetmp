<?php use App\Models\User; $u = $user; ?>
<section class="page-head">
    <div class="container">
        <h1>Olá, <?= e(display_name($u)) ?>!</h1>
        <?php if ($u['status'] === 'pending'): ?>
            <div class="notice notice-warning">⏳ Seu cadastro está <strong>aguardando aprovação</strong>. Assim que for aprovado você poderá confirmar presença nas trilhas e enviar fotos.</div>
        <?php endif; ?>
    </div>
</section>
<section class="section">
    <div class="container two-col">
        <div>
            <h2>Minhas próximas trilhas</h2>
            <?php if ($agenda): ?>
                <div class="event-grid one">
                    <?php foreach ($agenda as $event): ?><?= $view->partial('event-card', ['event' => $event]) ?><?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="empty">Você ainda não confirmou presença em nenhuma trilha. <a href="/agenda">Ver agenda</a></p>
            <?php endif; ?>
        </div>
        <aside class="card">
            <img class="profile-photo sm" src="<?= e(avatar_url($u)) ?>" alt="">
            <h3><?= e($u['name']) ?></h3>
            <p class="muted"><?= e($u['email']) ?></p>
            <p><span class="tag"><?= e(User::STATUS[$u['status']] ?? '') ?></span> <span class="tag"><?= e(User::ROLES[$u['role']] ?? '') ?></span></p>
            <dl class="info-list compact">
                <div><dt>Moto</dt><dd><?= e(trim(($u['bike_brand'] ?? '') . ' ' . ($u['bike_model'] ?? ''))) ?: '—' ?></dd></div>
                <div><dt>Nível</dt><dd><?= e(User::EXPERIENCE[$u['experience']] ?? '') ?></dd></div>
                <div><dt>Cidade</dt><dd><?= e($u['city']) ?: '—' ?></dd></div>
            </dl>
            <a class="btn btn-block" href="/minha-conta/editar">Editar perfil</a>
            <?php if ($u['status'] === 'approved'): ?>
                <a class="btn btn-ghost btn-block" href="/trilheiros/<?= (int) $u['id'] ?>">Ver meu perfil público</a>
                <a class="btn btn-ghost btn-block" href="/galeria/enviar">📷 Enviar fotos</a>
            <?php endif; ?>
        </aside>
    </div>
</section>
