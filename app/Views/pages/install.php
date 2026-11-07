<section class="page-head">
    <div class="container narrow center">
        <img class="install-icon" src="/assets/icons/icon-512.png" alt="" width="128" height="128">
        <h1>App Ducks Offroad</h1>
        <p class="lead">Instale o site como aplicativo no seu celular: ícone na tela inicial, abre em tela cheia e carrega mais rápido. Grátis e sem loja de aplicativos.</p>
        <p class="notice notice-success" data-installed-msg hidden>✅ Você já está usando o app instalado!</p>
        <div class="actions center-actions">
            <button class="btn btn-primary btn-lg" type="button" data-install hidden>📲 Instalar agora</button>
            <?php if ($playUrl): ?><a class="btn btn-lg" href="<?= e($playUrl) ?>" target="_blank" rel="noopener noreferrer">▶ Baixar na Google Play</a><?php endif; ?>
            <?php if ($apkUrl): ?><a class="btn btn-lg btn-ghost" href="<?= e($apkUrl) ?>" rel="noopener noreferrer">⬇ Baixar APK (Android)</a><?php endif; ?>
        </div>
    </div>
</section>
<section class="section">
    <div class="container narrow">
        <div class="install-steps">
            <article class="card">
                <h2>🤖 Android</h2>
                <ol>
                    <li>Abra este site no <strong>Chrome</strong>.</li>
                    <li>Toque em <strong>Instalar agora</strong> acima — ou no menu <strong>⋮</strong> e depois em <strong>Instalar app</strong> / <strong>Adicionar à tela inicial</strong>.</li>
                    <li>Confirme. O ícone do pato 🦆 aparece na tela inicial.</li>
                </ol>
            </article>
            <article class="card">
                <h2>🍎 iPhone / iPad</h2>
                <ol>
                    <li>Abra este site no <strong>Safari</strong>.</li>
                    <li>Toque no botão <strong>Compartilhar</strong> <span class="ios-share">⬆︎</span> (barra de baixo).</li>
                    <li>Role e toque em <strong>Adicionar à Tela de Início</strong>, depois em <strong>Adicionar</strong>.</li>
                </ol>
            </article>
            <article class="card">
                <h2>💻 Computador</h2>
                <ol>
                    <li>No <strong>Chrome</strong> ou <strong>Edge</strong>, clique no ícone de instalar na barra de endereço (ou em <strong>Instalar app</strong> no menu do site).</li>
                </ol>
            </article>
        </div>
        <h2>Perguntas rápidas</h2>
        <dl class="info-list">
            <div><dt>Ocupa espaço?</dt><dd>Muito pouco — menos que uma foto.</dd></div>
            <div><dt>Funciona sem internet?</dt><dd>Abre e mostra um aviso de "sem sinal". Agenda, galeria e cadastros precisam de internet para mostrar dados atualizados.</dd></div>
            <div><dt>Meus dados ficam no celular?</dt><dd>Não. Por segurança, páginas com dados pessoais e o painel nunca são guardados no aparelho.</dd></div>
            <div><dt>Como removo?</dt><dd>Como qualquer app: segure o ícone e escolha remover/desinstalar.</dd></div>
        </dl>
    </div>
</section>
