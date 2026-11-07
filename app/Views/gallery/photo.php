<section class="section photo-page">
    <div class="container">
        <p class="breadcrumb"><a href="/galeria">Galeria</a> / <a href="/galeria/<?= (int) $photo['album_id'] ?>"><?= e($photo['album_title']) ?></a> /</p>
        <div class="photo-layout">
            <figure class="photo-main">
                <img src="<?= e(upload_url('photos', $photo['filename'])) ?>" alt="<?= e($photo['caption'] ?: 'Foto da trilha') ?>">
                <nav class="photo-nav">
                    <?php if ($newer): ?><a class="btn btn-sm btn-ghost" href="/fotos/<?= (int) $newer ?>">‹ Anterior</a><?php else: ?><span></span><?php endif; ?>
                    <?php if ($older): ?><a class="btn btn-sm btn-ghost" href="/fotos/<?= (int) $older ?>">Próxima ›</a><?php endif; ?>
                </nav>
            </figure>
            <aside class="card photo-side">
                <?php if ($photo['caption']): ?><p class="photo-caption"><?= nl2br_e($photo['caption']) ?></p><?php endif; ?>
                <?php if ($photo['author']): ?><p class="meta">📷 Foto de <?= e($photo['author_nick'] ?: $photo['author']) ?> · <?= e(date('d/m/Y', strtotime($photo['created_at']))) ?></p><?php endif; ?>

                <div id="curtidas" class="likes">
                    <?php if ($auth->isApproved()): ?>
                        <form method="post" action="/fotos/<?= (int) $photo['id'] ?>/curtir" class="inline-form">
                            <?= $csrf->field() ?>
                            <button class="btn btn-sm<?= $liked ? ' btn-primary' : '' ?>" type="submit" aria-pressed="<?= $liked ? 'true' : 'false' ?>"><?= $liked ? '❤️ Curtiu' : '🤍 Curtir' ?></button>
                        </form>
                    <?php endif; ?>
                    <span class="muted"><?= count($likers) ?> curtida(s)<?php if ($likers): ?>: <?= e(implode(', ', array_map('display_name', array_slice($likers, 0, 5)))) ?><?= count($likers) > 5 ? ' e outros' : '' ?><?php endif; ?></span>
                </div>

                <div id="marcacoes" class="tags-block">
                    <h4>Na foto</h4>
                    <?php if ($tags): ?>
                        <ul class="tag-list">
                        <?php foreach ($tags as $t): ?>
                            <li>
                                <a href="/trilheiros/<?= (int) $t['id'] ?>"><img src="<?= e(avatar_url($t)) ?>" alt=""><?= e(display_name($t)) ?></a>
                                <?php if ($auth->check() && ((int) $t['id'] === $auth->id() || (int) $t['tagged_by'] === $auth->id() || $auth->isAdmin())): ?>
                                    <form method="post" action="/fotos/<?= (int) $photo['id'] ?>/marcacoes/<?= (int) $t['id'] ?>/remover" class="inline-form"><?= $csrf->field() ?><button class="link-btn" type="submit" title="Remover marcação">×</button></form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php else: ?><p class="muted">Ninguém marcado ainda.</p><?php endif; ?>
                    <?php if ($auth->isApproved() && $members): ?>
                        <form method="post" action="/fotos/<?= (int) $photo['id'] ?>/marcar" class="tag-form">
                            <?= $csrf->field() ?>
                            <select name="user_id" required aria-label="Marcar trilheiro">
                                <option value="">Marcar trilheiro…</option>
                                <?php foreach ($members as $m): ?><option value="<?= (int) $m['id'] ?>"><?= e(display_name($m)) ?><?= $m['nickname'] ? ' (' . e($m['name']) . ')' : '' ?></option><?php endforeach; ?>
                            </select>
                            <button class="btn btn-sm" type="submit">Marcar</button>
                        </form>
                    <?php endif; ?>
                </div>

                <div id="comentarios" class="comments">
                    <h4>Comentários (<?= count($comments) ?>)</h4>
                    <?php foreach ($comments as $c): ?>
                        <div class="comment">
                            <img src="<?= e(avatar_url($c)) ?>" alt="">
                            <div>
                                <strong><a href="/trilheiros/<?= (int) $c['user_id'] ?>"><?= e(display_name($c)) ?></a></strong>
                                <small class="muted"><?= e(date('d/m H:i', strtotime($c['created_at']))) ?></small>
                                <p><?= nl2br_e($c['body']) ?></p>
                                <?php if ($auth->check() && ((int) $c['user_id'] === $auth->id() || $auth->isAdmin())): ?>
                                    <form method="post" action="/fotos/<?= (int) $photo['id'] ?>/comentarios/<?= (int) $c['id'] ?>/excluir" class="inline-form" data-confirm="Excluir este comentário?"><?= $csrf->field() ?><button class="link-btn" type="submit"><small>excluir</small></button></form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($auth->isApproved()): ?>
                        <form method="post" action="/fotos/<?= (int) $photo['id'] ?>/comentar" class="form comment-form">
                            <?= $csrf->field() ?>
                            <textarea name="body" rows="2" maxlength="1000" required placeholder="Escreva um comentário…"></textarea>
                            <button class="btn btn-sm btn-primary" type="submit">Comentar</button>
                        </form>
                    <?php elseif (!$auth->check()): ?>
                        <p class="hint"><a href="/entrar">Entre</a> para curtir, comentar e marcar a galera.</p>
                    <?php endif; ?>
                </div>
                <button class="btn btn-ghost btn-sm" type="button" data-share data-title="Foto da trilha - Ducks Offroad">Compartilhar</button>
            </aside>
        </div>
    </div>
</section>
