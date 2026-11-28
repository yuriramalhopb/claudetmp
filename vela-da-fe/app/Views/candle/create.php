<?php
$v = $old + $defaults;
$val = static fn (string $k, string $d = '') => old($v, $k, $d);
$flag = static fn (string $k, bool $d) => $old !== [] ? !empty($old[$k]) : (array_key_exists($k, $defaults) ? !empty($defaults[$k]) : $d);
$selType = (int) $val('type_id', (string) ($types[0]['id'] ?? 0));
$selPlan = (int) $val('plan_id', (string) ($plans[0]['id'] ?? 0));
$firstType = null;
foreach ($types as $t) { if ((int) $t['id'] === $selType) { $firstType = $t; } }
$firstType ??= $types[0] ?? ['id' => 0, 'price' => 0];
$selPlanRow = null;
foreach ($plans as $p) { if ((int) $p['id'] === $selPlan) { $selPlanRow = $p; } }
$selPlanRow ??= $plans[0] ?? ['id' => 0, 'price' => 0];
?>
<section class="section">
    <div class="container">
        <h1 class="center">Acender uma vela</h1>
        <p class="lead center">Quatro passos simples. Leva menos de um minuto.</p>
        <?php if (!empty($relitFrom)): ?>
            <p class="notice">Preenchemos o formulário com os dados da sua vela anterior. Confira e acenda novamente. 🕯️</p>
        <?php endif; ?>

        <?php if (!$types || !$plans): ?>
            <p class="empty">Nenhuma vela está disponível no momento. Volte em breve.</p>
        <?php else: ?>
        <form class="candle-form" method="post" action="/acender" data-candle-form autocomplete="off">
            <?= $csrf->field() ?>
            <input type="hidden" name="relit_code" value="<?= e($val('relit_code')) ?>">
            <input type="hidden" name="relit_token" value="<?= e($val('relit_token')) ?>">
            <p class="hp" aria-hidden="true"><label>Não preencha este campo <input type="text" name="website" tabindex="-1" autocomplete="off"></label></p>

            <div class="form-main">
                <fieldset class="step">
                    <legend><span class="step-num">1</span> Escolha a sua vela</legend>
                    <div class="choice-grid">
                        <?php foreach ($types as $t): ?>
                        <label class="choice choice-type">
                            <input type="radio" name="type_id" value="<?= (int) $t['id'] ?>" data-price="<?= (int) $t['price'] ?>" data-ct="ct-<?= (int) $t['id'] ?>" <?= (int) $t['id'] === $selType ? 'checked' : '' ?> required>
                            <span class="choice-box">
                                <?= $view->partial('candle', ['typeId' => $t['id'], 'lit' => true, 'size' => 'sm']) ?>
                                <strong><?= e($t['name']) ?></strong>
                                <?php if ($t['description']): ?><small><?= e($t['description']) ?></small><?php endif; ?>
                                <?php if ((int) $t['price'] > 0): ?><em>+ <?= e(money((int) $t['price'])) ?></em><?php endif; ?>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?= field_error($errors, 'type_id') ?>
                </fieldset>

                <fieldset class="step">
                    <legend><span class="step-num">2</span> Por quanto tempo ela fica acesa?</legend>
                    <div class="pill-row">
                        <?php foreach ($plans as $p): ?>
                        <label class="choice choice-plan">
                            <input type="radio" name="plan_id" value="<?= (int) $p['id'] ?>" data-price="<?= (int) $p['price'] ?>" data-hours="<?= (int) $p['hours'] ?>" <?= (int) $p['id'] === $selPlan ? 'checked' : '' ?> required>
                            <span class="choice-box">
                                <strong><?= e($p['label']) ?></strong>
                                <small><?= e(money((int) $p['price'], true)) ?></small>
                                <?php if ($p['highlight']): ?><em>mais escolhida</em><?php endif; ?>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?= field_error($errors, 'plan_id') ?>
                    <?php if (!$pixOk): ?><p class="hint">Hoje oferecemos apenas velas gratuitas.</p><?php endif; ?>
                </fieldset>

                <fieldset class="step">
                    <legend><span class="step-num">3</span> Sua intenção</legend>
                    <div class="field">
                        <label for="category">Por qual motivo você acende esta vela?</label>
                        <select id="category" name="category" required>
                            <?php foreach (App\Models\Candle::CATEGORIES as $k => [$label, $emoji]): ?>
                                <option value="<?= e($k) ?>"<?= selected($val('category', 'gratidao'), $k) ?>><?= e($emoji . ' ' . $label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= field_error($errors, 'category') ?>
                    </div>
                    <div class="field">
                        <label for="name">Seu nome</label>
                        <input id="name" name="name" maxlength="80" required value="<?= e($val('name')) ?>" data-preview-name>
                        <?= field_error($errors, 'name') ?>
                    </div>
                    <div class="field">
                        <label for="for_whom">Por quem você acende? <small>(opcional)</small></label>
                        <input id="for_whom" name="for_whom" maxlength="100" placeholder="Ex.: minha mãe, Maria; a família Silva…" value="<?= e($val('for_whom')) ?>">
                        <?= field_error($errors, 'for_whom') ?>
                    </div>
                    <div class="field">
                        <label for="intention">Sua intenção, pedido ou agradecimento <small>(opcional)</small></label>
                        <textarea id="intention" name="intention" rows="4" maxlength="500" data-counter placeholder="Escreva com o coração. Deus já sabe, mas é bom partilhar."><?= e($val('intention')) ?></textarea>
                        <?= field_error($errors, 'intention') ?>
                    </div>
                </fieldset>

                <fieldset class="step">
                    <legend><span class="step-num">4</span> Privacidade e avisos</legend>
                    <label class="check"><input type="checkbox" name="show_name" value="1"<?= checked($flag('show_name', true)) ?>> Mostrar o meu nome no mural <small>(senão aparece “Anônimo”)</small></label>
                    <label class="check"><input type="checkbox" name="show_intention" value="1"<?= checked($flag('show_intention', true)) ?>> Mostrar a minha intenção no mural <small>(senão ela fica só entre você e Deus)</small></label>
                    <?php if ($settings->bool('mass_enabled')): ?>
                    <label class="check"><input type="checkbox" name="mass_intention" value="1"<?= checked($flag('mass_intention', false)) ?>> 🕊️ Quero que a minha intenção seja lembrada na Santa Missa <small>(só a equipe da igreja lê o texto)</small></label>
                    <?php endif; ?>
                    <div class="field">
                        <label for="email">E-mail <small>(opcional, para receber o link da sua vela)</small></label>
                        <input id="email" name="email" type="email" maxlength="160" autocomplete="email" value="<?= e($val('email')) ?>">
                        <?= field_error($errors, 'email') ?>
                    </div>
                    <label class="check"><input type="checkbox" name="notify_email" value="1"<?= checked($flag('notify_email', false)) ?>> Avisar-me por e-mail quando a vela estiver perto de se apagar</label>
                    <label class="check"><input type="checkbox" name="terms" value="1" required<?= checked($flag('terms', false)) ?>> Li e concordo com a <a href="/privacidade" target="_blank">política de privacidade</a></label>
                    <?= field_error($errors, 'terms') ?>
                </fieldset>
            </div>

            <aside class="form-side">
                <div class="preview-card">
                    <div class="preview-candle" data-preview-candle>
                        <?= $view->partial('candle', ['typeId' => $firstType['id'], 'lit' => true, 'size' => 'xl']) ?>
                    </div>
                    <p class="preview-name" data-preview-text>Sua vela</p>
                    <dl class="summary">
                        <dt>Duração</dt><dd data-summary-hours><?= e(hours_human((int) ($selPlanRow['hours'] ?? 24))) ?></dd>
                        <dt>Total</dt><dd><strong data-total><?= e(money(App\Models\Candle::price($firstType, $selPlanRow), true)) ?></strong></dd>
                    </dl>
                    <button class="btn btn-gold btn-lg btn-block" type="submit" data-submit-label>🕯️ Acender vela</button>
                    <p class="hint center" data-pay-hint hidden>Você verá o QR Code do Pix na próxima tela.</p>
                </div>
            </aside>
        </form>
        <?php endif; ?>
    </div>
</section>
