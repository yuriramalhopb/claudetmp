<?php
use App\Models\Event;
$f = $filters;
$base = '/trilhas-do-ano/' . $year;
$hasFilter = $f['state'] !== '' || $f['month'] > 0 || $f['type'] !== '' || $f['category'] !== '' || $f['level'] !== '' || $f['q'] !== '';
?>
<section class="page-head year-head">
    <div class="container">
        <p class="eyebrow">Calendário de trilhas</p>
        <div class="year-title">
            <?php if (in_array($year - 1, $years, true)): ?><a class="year-arrow" href="/trilhas-do-ano/<?= $year - 1 ?>" aria-label="Ano anterior">‹</a><?php endif; ?>
            <h1>Trilhas de <span><?= $year ?></span></h1>
            <?php if (in_array($year + 1, $years, true)): ?><a class="year-arrow" href="/trilhas-do-ano/<?= $year + 1 ?>" aria-label="Próximo ano">›</a><?php endif; ?>
        </div>
        <p class="lead">Todas as trilhas e eventos off-road do ano — da Ducks e de outras turmas — com data, local, valor e Instagram de cada evento.</p>
        <dl class="hero-stats">
            <div><dt>Eventos</dt><dd><?= (int) $total ?></dd></div>
            <div><dt>Cidades</dt><dd><?= (int) $cities ?></dd></div>
            <div><dt>Estados</dt><dd><?= (int) $statesCount ?></dd></div>
        </dl>
        <div class="actions">
            <a class="btn btn-ghost" href="<?= e($base) ?>/calendario.ics">📅 Baixar calendário <?= $year ?> (.ics)</a>
            <button class="btn btn-ghost" type="button" data-share data-title="Trilhas de <?= $year ?>">Compartilhar</button>
            <?php if ($auth->isApproved()): ?><a class="btn btn-primary" href="/agenda/sugerir">+ Cadastrar trilha</a><?php endif; ?>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <form class="filters" method="get" action="<?= e($base) ?>">
            <input type="search" name="q" placeholder="Nome, cidade, turma…" value="<?= e($f['q']) ?>" maxlength="60">
            <select name="estado" aria-label="Estado">
                <option value="">Todos os estados</option>
                <?php foreach ($states as $uf): ?><option value="<?= e($uf) ?>"<?= selected($uf, $f['state']) ?>><?= e($uf . ' — ' . (Event::STATES[$uf] ?? '')) ?></option><?php endforeach; ?>
            </select>
            <select name="mes" aria-label="Mês">
                <option value="">Ano inteiro</option>
                <?php for ($m = 1; $m <= 12; $m++): ?><option value="<?= $m ?>"<?= selected($m, $f['month']) ?>><?= e(ucfirst(MESES[$m])) ?></option><?php endfor; ?>
            </select>
            <select name="categoria" aria-label="Categoria">
                <option value="">Todas as categorias</option>
                <?php foreach (Event::CATEGORIES as $k => $label): ?><option value="<?= e($k) ?>"<?= selected($k, $f['category']) ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
            <select name="nivel" aria-label="Nível">
                <option value="">Qualquer nível</option>
                <?php foreach (Event::LEVELS as $k => $label): ?><option value="<?= e($k) ?>"<?= selected($k, $f['level']) ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
            <select name="tipo" aria-label="Organização">
                <option value="">Todas as turmas</option>
                <option value="ducks"<?= selected('ducks', $f['type']) ?>>Só da Ducks</option>
                <option value="outras"<?= selected('outras', $f['type']) ?>>Outras turmas</option>
            </select>
            <button class="btn" type="submit">Filtrar</button>
            <?php if ($hasFilter): ?><a class="btn btn-ghost" href="<?= e($base) ?>">Limpar</a><?php endif; ?>
        </form>

        <nav class="month-nav" aria-label="Meses">
            <?php for ($m = 1; $m <= 12; $m++): $n = count($byMonth[$m]); ?>
                <?php if ($n): ?>
                    <a href="#mes-<?= $m ?>"><?= e(mb_substr(ucfirst(MESES[$m]), 0, 3)) ?> <small><?= $n ?></small></a>
                <?php else: ?>
                    <span><?= e(mb_substr(ucfirst(MESES[$m]), 0, 3)) ?></span>
                <?php endif; ?>
            <?php endfor; ?>
        </nav>

        <?php if ($total === 0): ?>
            <p class="empty">Nenhuma trilha cadastrada<?= $hasFilter ? ' com esses filtros' : ' para ' . $year ?>.</p>
        <?php endif; ?>

        <?php for ($m = 1; $m <= 12; $m++): if (!$byMonth[$m]) continue; ?>
            <h2 class="month-title" id="mes-<?= $m ?>"><?= e(ucfirst(MESES[$m])) ?> <small class="muted"><?= count($byMonth[$m]) ?> evento(s)</small></h2>
            <div class="trail-grid">
                <?php foreach ($byMonth[$m] as $event): ?><?= $view->partial('trail-card', ['event' => $event]) ?><?php endforeach; ?>
            </div>
        <?php endfor; ?>
    </div>
</section>
