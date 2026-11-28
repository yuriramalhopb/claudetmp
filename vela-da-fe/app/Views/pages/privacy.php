<section class="section">
    <div class="container narrow prose">
        <h1>Privacidade e termos de uso</h1>
        <?php if (trim((string) $settings->get('privacy_text')) !== ''): ?>
            <p><?= nl2br_e((string) $settings->get('privacy_text')) ?></p>
        <?php else: ?>
            <p>Este site pertence a <strong><?= e($church['name']) ?></strong> e existe para que você possa acender uma vela de fé e confiar a Deus as suas intenções. Tratamos os seus dados conforme a Lei Geral de Proteção de Dados (LGPD, Lei 13.709/2018).</p>
            <h2>Quais dados coletamos</h2>
            <ul>
                <li><strong>Nome, motivo, "por quem" e intenção</strong>, que você escreve ao acender a vela. Você escolhe se o seu nome e a sua intenção aparecem no mural; se não marcar, a vela aparece de forma anônima.</li>
                <li><strong>E-mail (opcional)</strong>, usado somente para enviar o link da sua vela e, se você marcar, avisos de que ela está perto de se apagar. Não enviamos propaganda nem compartilhamos o seu e-mail.</li>
                <li><strong>Intenção para a missa (opcional)</strong>: o texto é lido apenas pela equipe da igreja para ser lembrado na celebração.</li>
                <li><strong>Dados técnicos</strong>: guardamos apenas uma "impressão" anônima e irreversível da sua conexão, para limitar abusos (por exemplo, muitas velas no mesmo dia). Não guardamos o seu endereço de IP em texto.</li>
            </ul>
            <h2>Por quanto tempo guardamos</h2>
            <p>Depois que a vela se apaga, o e-mail e a impressão da conexão são apagados automaticamente (em até 6 meses). Os textos das intenções que você marcou como públicas podem permanecer no memorial por 30 dias.</p>
            <h2>Seus direitos</h2>
            <p>Você pode pedir a qualquer momento a correção ou a exclusão da sua vela e dos seus dados, bastando falar com a secretaria<?= $church['email'] !== '' ? ' pelo e-mail ' . e($church['email']) : '' ?>, informando o código da vela (os 12 caracteres do endereço da página da vela).</p>
            <h2>Uso do mural</h2>
            <p>Pedimos respeito: textos com ofensas, links, propaganda ou dados pessoais de terceiros (telefones, endereços) são retidos ou removidos pela moderação. Não é possível acender velas para promover pessoas ou negócios.</p>
            <h2>Pagamentos</h2>
            <p>As velas e ofertas opcionais são pagas por Pix diretamente para a conta da igreja. Não coletamos dados de cartão nem de conta bancária.</p>
        <?php endif; ?>
    </div>
</section>
