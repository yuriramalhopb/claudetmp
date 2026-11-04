<?php use App\Models\User; ?>
<section class="page-head">
    <div class="container">
        <h1>Trilheiros</h1>
        <p class="lead"><?= (int) $total ?> trilheiro(s) na turma.</p>
        <?php if (!$auth->check()): ?><a class="btn btn-primary" href="/cadastro">Quero fazer parte</a><?php endif; ?>
    </div>
</section>
<section class="section">
    <div class="container">
        <form class="filters" method="get" action="/trilheiros">
            <input type="search" name="q" placeholder="Nome, cidade ou moto…" value="<?= e($search) ?>" maxlength="60">
            <select name="nivel" aria-label="Nível">
                <option value="">Todos os níveis</option>
                <?php foreach (User::EXPERIENCE as $k => $label): ?><option value="<?= e($k) ?>"<?= selected($k, $level) ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
            <button class="btn" type="submit">Buscar</button>
        </form>
        <?php if ($members): ?>
            <div class="member-grid">
                <?php foreach ($members as $m): ?>
                    <a class="member-card" href="/trilheiros/<?= (int) $m['id'] ?>">
                        <img src="<?= e(avatar_url($m)) ?>" alt="" loading="lazy">
                        <h3><?= e(display_name($m)) ?></h3>
                        <?php if ($m['nickname']): ?><p class="muted"><?= e($m['name']) ?></p><?php endif; ?>
                        <p class="meta"><?= e(trim(($m['bike_brand'] ?? '') . ' ' . ($m['bike_model'] ?? ''))) ?><?= $m['bike_cc'] ? ' · ' . (int) $m['bike_cc'] . 'cc' : '' ?></p>
                        <p class="meta"><?= e($m['city']) ?></p>
                        <span class="tag tag-exp-<?= e($m['experience']) ?>"><?= e(User::EXPERIENCE[$m['experience']] ?? '') ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
            <?= $view->partial('pagination', ['page' => $page, 'pages' => $pages, 'base' => '/trilheiros', 'params' => ['q' => $search, 'nivel' => $level]]) ?>
        <?php else: ?>
            <p class="empty">Nenhum trilheiro encontrado.</p>
        <?php endif; ?>
    </div>
</section>
