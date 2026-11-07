<section class="page-head">
    <div class="container">
        <h1>🏆 Ranking <?= $year ? e((string) $year) : 'geral' ?></h1>
        <p class="lead">Quem mais rodou com a turma: trilhas completadas e quilômetros.</p>
        <nav class="month-nav" aria-label="Período">
            <?php foreach (array_unique(array_merge([(int) date('Y')], $years)) as $y): ?>
                <a href="/ranking?ano=<?= (int) $y ?>"<?= $year === (int) $y ? ' aria-current="page" class="is-current"' : '' ?>><?= (int) $y ?></a>
            <?php endforeach; ?>
            <a href="/ranking?ano=geral"<?= $year === null ? ' aria-current="page" class="is-current"' : '' ?>>Geral</a>
        </nav>
    </div>
</section>
<section class="section">
    <div class="container narrow">
        <?php if ($ranking): ?>
            <div class="podium">
                <?php foreach ([1, 0, 2] as $pos): if (!isset($ranking[$pos])) continue; $r = $ranking[$pos]; ?>
                    <a class="podium-item p<?= $pos + 1 ?>" href="/trilheiros/<?= (int) $r['id'] ?>">
                        <span class="medal"><?= ['🥇', '🥈', '🥉'][$pos] ?></span>
                        <img src="<?= e(avatar_url($r)) ?>" alt="">
                        <strong><?= e(display_name($r)) ?></strong>
                        <small><?= (int) $r['trails'] ?> trilha(s) · <?= e(number_format((int) $r['km'], 0, ',', '.')) ?> km</small>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="table-wrap">
            <table class="table">
                <thead><tr><th>#</th><th>Trilheiro</th><th>Trilhas</th><th>Km</th><th>Última</th></tr></thead>
                <tbody>
                <?php foreach ($ranking as $i => $r): ?>
                    <tr>
                        <td><?= $i + 1 ?>º</td>
                        <td><a class="rank-user" href="/trilheiros/<?= (int) $r['id'] ?>"><img class="avatar-sm" src="<?= e(avatar_url($r)) ?>" alt=""><span><?= e(display_name($r)) ?><?php if ($r['bike_model']): ?><br><small class="muted"><?= e($r['bike_model']) ?></small><?php endif; ?></span></a></td>
                        <td><strong><?= (int) $r['trails'] ?></strong></td>
                        <td><?= e(number_format((int) $r['km'], 0, ',', '.')) ?></td>
                        <td><small><?= e(date('d/m/Y', strtotime($r['last_trail']))) ?></small></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php else: ?>
            <p class="empty">Ainda não há trilhas completadas neste período. Bora rodar! 🏍️</p>
        <?php endif; ?>
        <p class="hint">Conta as trilhas já realizadas em que o trilheiro confirmou presença pelo site. Km = percurso GPX ou distância informada da trilha.</p>
    </div>
</section>
