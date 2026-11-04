<?php use App\Models\Event; ?>
<section class="page-head">
    <div class="container">
        <h1>Agenda de trilhas</h1>
        <p class="lead">Trilhas da Ducks e eventos de outras turmas da região.</p>
        <?php if ($auth->isApproved()): ?><a class="btn btn-primary" href="/agenda/sugerir">+ Sugerir trilha de outra turma</a><?php endif; ?>
    </div>
</section>
<section class="section">
    <div class="container">
        <form class="filters" method="get" action="/agenda">
            <input type="search" name="q" placeholder="Buscar trilha, cidade, turma…" value="<?= e($search) ?>" maxlength="60">
            <select name="tipo" aria-label="Tipo">
                <option value="">Todas as trilhas</option>
                <option value="ducks"<?= selected('ducks', $type) ?>>Só da Ducks</option>
                <option value="outras"<?= selected('outras', $type) ?>>Outras turmas</option>
            </select>
            <select name="nivel" aria-label="Nível">
                <option value="">Qualquer nível</option>
                <?php foreach (Event::LEVELS as $k => $label): ?><option value="<?= e($k) ?>"<?= selected($k, $level) ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
            <label class="check"><input type="checkbox" name="passados" value="1"<?= checked($showPast) ?>> Ver eventos passados</label>
            <button class="btn" type="submit">Filtrar</button>
        </form>

        <?php if ($grouped): ?>
            <?php foreach ($grouped as $month => $list): ?>
                <h2 class="month-title"><?= e($month) ?></h2>
                <div class="event-grid">
                    <?php foreach ($list as $event): ?><?= $view->partial('event-card', ['event' => $event]) ?><?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="empty">Nenhum evento encontrado com esses filtros.</p>
        <?php endif; ?>
    </div>
</section>
