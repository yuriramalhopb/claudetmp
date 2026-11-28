<section class="section">
    <div class="container narrow">
        <h1><?= e($church['name']) ?></h1>
        <?php if ($church['tagline'] !== ''): ?><p class="lead"><?= e($church['tagline']) ?></p><?php endif; ?>
        <div class="card info-card">
            <ul class="info-list">
                <?php if ($church['address'] !== ''): ?><li><strong>Endereço</strong><span><?= e($church['address']) ?></span></li><?php endif; ?>
                <?php if ($church['phone'] !== ''): ?><li><strong>Telefone</strong><span><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $church['phone'])) ?>"><?= e($church['phone']) ?></a></span></li><?php endif; ?>
                <?php if (whatsapp_link($church['whatsapp'])): ?><li><strong>WhatsApp</strong><span><a href="<?= e(whatsapp_link($church['whatsapp'])) ?>" target="_blank" rel="noopener noreferrer">Falar com a secretaria</a></span></li><?php endif; ?>
                <?php if ($church['email'] !== ''): ?><li><strong>E-mail</strong><span><a href="mailto:<?= e($church['email']) ?>"><?= e($church['email']) ?></a></span></li><?php endif; ?>
                <?php if ($church['instagram'] !== ''): ?><li><strong>Instagram</strong><span><a href="https://instagram.com/<?= e($church['instagram']) ?>" target="_blank" rel="noopener noreferrer">@<?= e($church['instagram']) ?></a></span></li><?php endif; ?>
            </ul>
            <?php if (preg_match('#^https://#', $church['maps'])): ?>
                <p><a class="btn btn-gold" href="<?= e($church['maps']) ?>" target="_blank" rel="noopener noreferrer">📍 Como chegar</a></p>
            <?php endif; ?>
        </div>

        <h2>Horários das celebrações</h2>
        <?php if ($massTimes): ?>
            <table class="times">
                <?php foreach ($massTimes as $wd => $rows): ?>
                    <?php foreach ($rows as $i => $r): ?>
                    <tr>
                        <th scope="row"><?= $i === 0 ? e(App\Admin\Resources::WEEKDAYS[$wd] ?? '') : '' ?></th>
                        <td><strong><?= e($r['time']) ?></strong> — <?= e($r['label']) ?><?= $r['location'] ? ' · ' . e($r['location']) : '' ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <p class="muted">Os horários ainda não foram cadastrados.</p>
        <?php endif; ?>
    </div>
</section>
