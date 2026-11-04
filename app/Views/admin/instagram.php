<h1>Instagram</h1>
<div class="two-col">
    <section class="card">
        <h2>Conexão automática (API)</h2>
        <?php if ($configured): ?>
            <p>✅ Token configurado: <code><?= e($tokenHint) ?></code></p>
            <p class="muted">Última sincronização: <?= $lastSync ? e(date('d/m/Y H:i', strtotime($lastSync))) : 'nunca' ?></p>
            <?php if ($lastError): ?><div class="notice notice-error">Último erro: <?= e($lastError) ?></div><?php endif; ?>
            <form method="post" action="/admin/instagram/sincronizar" class="inline-form"><?= $csrf->field() ?><button class="btn btn-primary" type="submit">🔄 Sincronizar agora</button></form>
            <form method="post" action="/admin/instagram/config" class="inline-form" data-confirm="Remover o token do Instagram?"><?= $csrf->field() ?><input type="hidden" name="remove_token" value="1"><button class="btn btn-ghost" type="submit">Remover token</button></form>
        <?php else: ?>
            <p class="muted">Conecte a conta da turma para que as publicações apareçam aqui automaticamente.</p>
        <?php endif; ?>
        <form class="form" method="post" action="/admin/instagram/config" autocomplete="off">
            <?= $csrf->field() ?>
            <label>Token de acesso (long-lived)
                <input type="password" name="access_token" required autocomplete="off" placeholder="IGAA…">
            </label>
            <button class="btn" type="submit"><?= $configured ? 'Trocar token' : 'Salvar token' ?></button>
        </form>
        <details class="help">
            <summary>Como obter o token?</summary>
            <ol>
                <li>Converta o Instagram da turma em conta <strong>Profissional</strong> (Criador ou Empresa).</li>
                <li>Em <a href="https://developers.facebook.com/apps" target="_blank" rel="noopener noreferrer">developers.facebook.com</a>, crie um app e adicione o produto <strong>Instagram</strong> → "API com login do Instagram".</li>
                <li>Em "Gerar tokens de acesso", adicione a conta da turma e gere o token (permissão <code>instagram_business_basic</code>).</li>
                <li>Cole o token aqui. O sistema renova o token automaticamente antes de expirar (60 dias).</li>
                <li>Opcional: agende <code>php bin/instagram-sync.php</code> no cron a cada hora.</li>
            </ol>
        </details>
    </section>
    <form class="card form" method="post" action="/admin/instagram/manual">
        <h2>Adicionar post pelo link</h2>
        <p class="muted">Sem API? Cole o link de qualquer post ou reel público (da turma ou de outras turmas) e ele aparece no site com a incorporação oficial do Instagram.</p>
        <?= $csrf->field() ?>
        <label>Link do post
            <input type="url" name="permalink" required placeholder="https://www.instagram.com/p/XXXXXXX/">
        </label>
        <label>Observação interna (opcional)
            <input type="text" name="caption" maxlength="2000">
        </label>
        <button class="btn btn-primary" type="submit">Adicionar</button>
    </form>
</div>

<section class="card">
    <h2>Publicações no site (<?= count($posts) ?>)</h2>
    <div class="table-wrap">
    <table class="table">
        <thead><tr><th></th><th>Post</th><th>Origem</th><th>Data</th><th>Visível</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($posts as $p): ?>
            <tr class="<?= $p['hidden'] ? 'is-muted' : '' ?>">
                <td><?php if ($t = upload_url('instagram', $p['local_file'], true)): ?><img class="avatar-sm square" src="<?= e($t) ?>" alt=""><?php endif; ?></td>
                <td><a href="<?= e($p['permalink']) ?>" target="_blank" rel="noopener noreferrer"><?= e(excerpt($p['caption'], 80) ?: $p['permalink']) ?></a></td>
                <td><?= $p['source'] === 'api' ? 'API' : 'Link' ?></td>
                <td class="nowrap"><?= $p['posted_at'] ? e(date('d/m/Y', strtotime($p['posted_at']))) : '' ?></td>
                <td><?= $p['hidden'] ? 'Oculto' : 'Sim' ?></td>
                <td class="right nowrap">
                    <form method="post" action="/admin/instagram/<?= (int) $p['id'] ?>/visibilidade" class="inline-form"><?= $csrf->field() ?><button class="btn btn-sm" type="submit"><?= $p['hidden'] ? 'Mostrar' : 'Ocultar' ?></button></form>
                    <form method="post" action="/admin/instagram/<?= (int) $p['id'] ?>/excluir" class="inline-form" data-confirm="Remover este post do site?"><?= $csrf->field() ?><button class="btn btn-sm btn-danger" type="submit">Remover</button></form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>
