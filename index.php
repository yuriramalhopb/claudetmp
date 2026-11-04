<?php
/**
 * Tabuada Divertida — jogo de contas aleatórias para crianças.
 *
 * - Soma, subtração, multiplicação e divisão (escolha quais).
 * - Escolha quantos dígitos tem cada número.
 * - Escolha quantas opções de resposta aparecem.
 * - Modo Tabuada: estude a tabuada do 1 ao 10, em ordem ou misturada.
 * - Ao clicar numa opção, o PHP verifica se está certa.
 * Sem login e sem banco de dados: tudo fica na sessão do navegador.
 */
declare(strict_types=1);

session_start();

const OPERACOES = [
    'soma'          => ['simbolo' => '+', 'nome' => 'Soma'],
    'subtracao'     => ['simbolo' => '−', 'nome' => 'Subtração'],
    'multiplicacao' => ['simbolo' => '×', 'nome' => 'Multiplicação'],
    'divisao'       => ['simbolo' => '÷', 'nome' => 'Divisão'],
];

const PADRAO = [
    'operacoes' => ['soma', 'subtracao', 'multiplicacao', 'divisao'],
    'digitos1'  => 1,
    'digitos2'  => 1,
    'opcoes'    => 4,
    'modo'      => 'aleatorio',   // 'aleatorio' ou 'tabuada'
    'numeros'   => [2],           // tabuadas escolhidas (1 a 10)
    'ordem'     => 'sequencia',   // 'sequencia' (1 a 10) ou 'misturada'
];

function e(string|int $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

/** Menor e maior número com N dígitos (1 dígito = 1 a 9, para não aparecer zero). */
function faixa(int $digitos): array
{
    $min = $digitos === 1 ? 1 : 10 ** ($digitos - 1);
    $max = 10 ** $digitos - 1;
    return [$min, $max];
}

function aleatorio(int $digitos): int
{
    [$min, $max] = faixa($digitos);
    return random_int($min, $max);
}

/**
 * Cria uma conta nova de acordo com as configurações.
 * $passo é a posição na sequência (usado na tabuada em ordem).
 */
function gerarConta(array $cfg, int $passo = 0): array
{
    $conta = $cfg['modo'] === 'tabuada' ? contaTabuada($cfg, $passo) : contaAleatoria($cfg);
    ['op' => $op, 'a' => $a, 'b' => $b, 'resposta' => $r] = $conta;

    // Erros "típicos" para as opções ficarem parecidas com a resposta certa.
    $parecidas = match ($op) {
        'multiplicacao' => [$a * ($b + 1), $a * ($b - 1), ($a + 1) * $b, ($a - 1) * $b],
        'divisao'       => [$r + 1, $r - 1, $r * 2],
        default         => [$r + 1, $r - 1, $r + 10, $r - 10],
    };

    return $conta + [
        'opcoes'    => gerarOpcoes($r, $cfg['opcoes'], $parecidas),
        'escolhida' => null,
    ];
}

/**
 * Tabuada do N (N de 1 a 10), com K indo de 1 a 10:
 *   soma: N + K | subtração: (N + K) − N | multiplicação: N × K | divisão: (N × K) ÷ N
 */
function contaTabuada(array $cfg, int $passo): array
{
    $numeros = $cfg['numeros'];
    $ops     = $cfg['operacoes'];

    if ($cfg['ordem'] === 'sequencia') {
        $i  = $passo % (count($numeros) * count($ops) * 10);
        $n  = $numeros[intdiv($i, count($ops) * 10)];
        $op = $ops[intdiv($i, 10) % count($ops)];
        $k  = $i % 10 + 1;
    } else {
        $n  = $numeros[array_rand($numeros)];
        $op = $ops[array_rand($ops)];
        $k  = random_int(1, 10);
    }

    [$a, $b, $r] = linhaTabuada($op, $n, $k);

    return ['op' => $op, 'a' => $a, 'b' => $b, 'resposta' => $r, 'tabuada' => $n, 'k' => $k];
}

/** Uma linha da tabuada do N para a tabela de estudo: [a, b, resultado]. */
function linhaTabuada(string $op, int $n, int $k): array
{
    return match ($op) {
        'soma'          => [$n, $k, $n + $k],
        'subtracao'     => [$n + $k, $n, $k],
        'multiplicacao' => [$n, $k, $n * $k],
        default         => [$n * $k, $n, $k],
    };
}

/** Conta com números aleatórios, com a quantidade de dígitos escolhida. */
function contaAleatoria(array $cfg): array
{
    $op = $cfg['operacoes'][array_rand($cfg['operacoes'])];
    $d1 = $cfg['digitos1'];
    $d2 = $cfg['digitos2'];

    switch ($op) {
        case 'soma':
            $a = aleatorio($d1);
            $b = aleatorio($d2);
            $r = $a + $b;
            break;

        case 'subtracao':
            // Sem resultado negativo: o maior número fica na frente.
            $a = aleatorio($d1);
            $b = aleatorio($d2);
            if ($b > $a) {
                [$a, $b] = [$b, $a];
            }
            $r = $a - $b;
            break;

        case 'multiplicacao':
            $a = aleatorio($d1);
            $b = aleatorio($d2);
            $r = $a * $b;
            break;

        default: // divisão sempre exata (sem resto)
            $b = $d2 === 1 ? random_int(2, 9) : aleatorio($d2);
            [$min1, $max1] = faixa($d1);
            $qMin = max(1, intdiv($min1 + $b - 1, $b));
            $qMax = intdiv($max1, $b);
            if ($qMin > $qMax) {
                // Não dá para o dividendo ter os dígitos pedidos; usa resultado de 1 dígito.
                $qMin = 1;
                $qMax = 9;
            }
            $r = random_int($qMin, $qMax);
            $a = $b * $r;
            break;
    }

    return ['op' => $op, 'a' => $a, 'b' => $b, 'resposta' => $r];
}

/** Cria respostas erradas parecidas com a certa e embaralha tudo. */
function gerarOpcoes(int $certa, int $quantidade, array $parecidas = []): array
{
    $opcoes = [$certa => true];

    // Até 2 erros "típicos" (ex.: 7 × 8 = 49 ou 64), o resto é sorteado perto da resposta.
    shuffle($parecidas);
    foreach ($parecidas as $p) {
        if (count($opcoes) >= min($quantidade, 3)) {
            break;
        }
        if ($p >= 0) {
            $opcoes[$p] = true;
        }
    }

    $alcance = max(5, (int) round(abs($certa) * 0.2));
    $tentativas = 0;

    while (count($opcoes) < $quantidade && $tentativas < 500) {
        $tentativas++;
        $tipo = random_int(1, 3);
        if ($tipo === 1) {
            $delta = random_int(1, 3);               // bem pertinho
        } elseif ($tipo === 2) {
            $delta = random_int(1, $alcance);        // perto
        } else {
            $delta = 10 * random_int(1, 2);          // erro de dezena
        }
        $falsa = $certa + (random_int(0, 1) ? $delta : -$delta);
        if ($falsa >= 0) {
            $opcoes[$falsa] = true;
        }
    }

    $lista = array_keys($opcoes);
    shuffle($lista);
    return $lista;
}

function lerConfig(array $post): array
{
    $ops = array_values(array_intersect(array_keys(OPERACOES), (array) ($post['operacoes'] ?? [])));
    $int = static fn ($v, int $min, int $max, int $pad): int =>
        filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]) ?: $pad;

    return [
        'operacoes' => $ops ?: PADRAO['operacoes'],
        'digitos1'  => $int($post['digitos1'] ?? null, 1, 4, PADRAO['digitos1']),
        'digitos2'  => $int($post['digitos2'] ?? null, 1, 4, PADRAO['digitos2']),
        'opcoes'    => $int($post['opcoes'] ?? null, 2, 6, PADRAO['opcoes']),
        'modo'      => ($post['modo'] ?? '') === 'tabuada' ? 'tabuada' : 'aleatorio',
        'numeros'   => array_values(array_intersect(range(1, 10), array_map('intval', (array) ($post['numeros'] ?? []))))
                       ?: PADRAO['numeros'],
        'ordem'     => ($post['ordem'] ?? '') === 'misturada' ? 'misturada' : 'sequencia',
    ];
}

function placarZerado(): array
{
    return ['acertos' => 0, 'erros' => 0, 'sequencia' => 0, 'recorde' => 0];
}

// ---------- Estado ----------
$_SESSION['config'] = array_merge(PADRAO, $_SESSION['config'] ?? []);
$_SESSION['placar'] ??= placarZerado();
$_SESSION['passo']  ??= 0;
$_SESSION['conta']  ??= gerarConta($_SESSION['config'], $_SESSION['passo']);

// ---------- Ações (POST → redireciona, para F5 não repetir a resposta) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'responder' && $_SESSION['conta']['escolhida'] === null) {
        $valor = filter_var($_POST['valor'] ?? null, FILTER_VALIDATE_INT);
        if ($valor !== false && in_array($valor, $_SESSION['conta']['opcoes'], true)) {
            $_SESSION['conta']['escolhida'] = $valor;
            $p = &$_SESSION['placar'];
            if ($valor === $_SESSION['conta']['resposta']) {
                $p['acertos']++;
                $p['sequencia']++;
                $p['recorde'] = max($p['recorde'], $p['sequencia']);
            } else {
                $p['erros']++;
                $p['sequencia'] = 0;
            }
            unset($p);
        }
    } elseif ($acao === 'proxima') {
        $_SESSION['passo']++;
        $_SESSION['conta'] = gerarConta($_SESSION['config'], $_SESSION['passo']);
    } elseif ($acao === 'configurar' || $acao === 'zerar') {
        if ($acao === 'configurar') {
            $_SESSION['config'] = lerConfig($_POST);
        } else {
            $_SESSION['placar'] = placarZerado();
        }
        $_SESSION['passo'] = 0;
        $_SESSION['conta'] = gerarConta($_SESSION['config'], 0);
    }

    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'), true, 303);
    exit;
}

$cfg       = $_SESSION['config'];
$placar    = $_SESSION['placar'];
$conta     = $_SESSION['conta'];
$respondeu = $conta['escolhida'] !== null;
$acertou   = $respondeu && $conta['escolhida'] === $conta['resposta'];

$elogios = ['Muito bem! 🎉', 'Acertou! ⭐', 'Mandou bem! 🚀', 'Isso aí! 🏆', 'Show de bola! ⚽', 'Que craque! 🌟'];
$animos  = ['Quase! Vamos tentar outra? 💪', 'Não foi dessa vez, mas você consegue! 🙂', 'Errar faz parte de aprender! 📚'];
$mensagem = $respondeu ? ($acertou ? $elogios[array_rand($elogios)] : $animos[array_rand($animos)]) : '';

$modoTabuada = $cfg['modo'] === 'tabuada' && isset($conta['tabuada']);
$fimDaTabuada = $modoTabuada && $respondeu && $cfg['ordem'] === 'sequencia' && $conta['k'] === 10;

$total = $placar['acertos'] + $placar['erros'];
$aproveitamento = $total > 0 ? (int) round($placar['acertos'] * 100 / $total) : 0;
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#6c5ce7">
<meta name="description" content="Jogo de contas de soma, subtração, multiplicação e divisão para crianças.">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Tabuada">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<title>Tabuada Divertida</title>
<link rel="manifest" href="manifest.webmanifest">
<link rel="icon" href="icones/icone.svg" type="image/svg+xml">
<link rel="icon" href="icones/favicon-32.png" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="icones/apple-touch-icon.png">
<link rel="stylesheet" href="estilo.css">
<script src="app.js" defer></script>
</head>
<body>
<main class="app">
    <header class="topo">
        <h1>🧮 Tabuada Divertida</h1>
        <button type="button" id="instalar" class="instalar" hidden>📲 Instalar app</button>
        <p id="dica-ios" class="dica-ios" hidden>
            No iPhone/iPad (Safari): toque em <strong>Compartilhar</strong> (quadrado com seta ⬆️)
            e depois em <strong>Adicionar à Tela de Início</strong>.
        </p>
        <div class="placar">
            <span class="item certo" title="Acertos">✅ <?= e($placar['acertos']) ?></span>
            <span class="item errado" title="Erros">❌ <?= e($placar['erros']) ?></span>
            <span class="item" title="Acertos seguidos">🔥 <?= e($placar['sequencia']) ?></span>
            <span class="item" title="Recorde de acertos seguidos">🏆 <?= e($placar['recorde']) ?></span>
            <?php if ($total > 0): ?>
                <span class="item" title="Aproveitamento">🎯 <?= e($aproveitamento) ?>%</span>
            <?php endif; ?>
        </div>
    </header>

    <section class="cartao <?= $respondeu ? ($acertou ? 'acertou' : 'errou') : '' ?>">
        <p class="tipo">
            <?php if ($modoTabuada): ?>
                Tabuada do <?= e($conta['tabuada']) ?> · <?= e(OPERACOES[$conta['op']]['nome']) ?>
                <?php if ($cfg['ordem'] === 'sequencia'): ?>
                    <span class="progresso"><?= e($conta['k']) ?> de 10</span>
                <?php endif; ?>
            <?php else: ?>
                <?= e(OPERACOES[$conta['op']]['nome']) ?>
            <?php endif; ?>
        </p>
        <p class="conta">
            <span><?= e($conta['a']) ?></span>
            <span class="sinal"><?= e(OPERACOES[$conta['op']]['simbolo']) ?></span>
            <span><?= e($conta['b']) ?></span>
            <span class="sinal">=</span>
            <span class="resultado"><?= $respondeu ? e($conta['resposta']) : '?' ?></span>
        </p>

        <form method="post" class="opcoes">
            <input type="hidden" name="acao" value="responder">
            <?php foreach ($conta['opcoes'] as $opcao):
                $classe = '';
                if ($respondeu) {
                    if ($opcao === $conta['resposta']) {
                        $classe = 'correta';
                    } elseif ($opcao === $conta['escolhida']) {
                        $classe = 'incorreta';
                    } else {
                        $classe = 'apagada';
                    }
                }
            ?>
                <button type="submit" name="valor" value="<?= e($opcao) ?>"
                        class="opcao <?= $classe ?>" <?= $respondeu ? 'disabled' : '' ?>>
                    <?= e($opcao) ?>
                </button>
            <?php endforeach; ?>
        </form>

        <?php if ($respondeu): ?>
            <p class="mensagem"><?= e($mensagem) ?></p>
            <?php if (!$acertou): ?>
                <p class="dica">A resposta certa é <strong><?= e($conta['resposta']) ?></strong>.</p>
            <?php endif; ?>
            <?php if ($fimDaTabuada): ?>
                <p class="mensagem">Você chegou ao fim da tabuada do <?= e($conta['tabuada']) ?>! 🥳</p>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="acao" value="proxima">
                <button type="submit" class="proxima" autofocus>Próxima conta ➜</button>
            </form>
        <?php else: ?>
            <p class="mensagem">Escolha a resposta certa! 👆</p>
        <?php endif; ?>
    </section>

    <?php if ($modoTabuada): ?>
        <details class="estudo">
            <summary>📖 Ver a tabuada do <?= e($conta['tabuada']) ?> (<?= e(OPERACOES[$conta['op']]['nome']) ?>)</summary>
            <ol class="tabela">
                <?php for ($k = 1; $k <= 10; $k++):
                    [$la, $lb, $lr] = linhaTabuada($conta['op'], $conta['tabuada'], $k); ?>
                    <li><?= e($la) ?> <?= e(OPERACOES[$conta['op']]['simbolo']) ?> <?= e($lb) ?> = <strong><?= e($lr) ?></strong></li>
                <?php endfor; ?>
            </ol>
        </details>
    <?php endif; ?>

    <details class="config" <?= $total === 0 ? 'open' : '' ?>>
        <summary>⚙️ Configurações</summary>
        <form method="post">
            <input type="hidden" name="acao" value="configurar">

            <fieldset>
                <legend>Como quer jogar?</legend>
                <div class="checks modos">
                    <label class="check">
                        <input type="radio" name="modo" value="aleatorio" <?= $cfg['modo'] === 'aleatorio' ? 'checked' : '' ?>>
                        <span>🎲 Contas aleatórias</span>
                    </label>
                    <label class="check">
                        <input type="radio" name="modo" value="tabuada" id="modo-tabuada" <?= $cfg['modo'] === 'tabuada' ? 'checked' : '' ?>>
                        <span>📘 Tabuada (1 a 10)</span>
                    </label>
                </div>
            </fieldset>

            <fieldset class="so-tabuada">
                <legend>Tabuada de quais números?</legend>
                <div class="numeros">
                    <?php for ($i = 1; $i <= 10; $i++): ?>
                        <label class="numero">
                            <input type="checkbox" name="numeros[]" value="<?= $i ?>"
                                <?= in_array($i, $cfg['numeros'], true) ? 'checked' : '' ?>>
                            <span><?= $i ?></span>
                        </label>
                    <?php endfor; ?>
                </div>
                <label class="ordem">Ordem
                    <select name="ordem">
                        <option value="sequencia" <?= $cfg['ordem'] === 'sequencia' ? 'selected' : '' ?>>Em ordem (do 1 ao 10)</option>
                        <option value="misturada" <?= $cfg['ordem'] === 'misturada' ? 'selected' : '' ?>>Misturada</option>
                    </select>
                </label>
            </fieldset>

            <fieldset>
                <legend>Quais contas?</legend>
                <div class="checks">
                    <?php foreach (OPERACOES as $chave => $info): ?>
                        <label class="check">
                            <input type="checkbox" name="operacoes[]" value="<?= e($chave) ?>"
                                <?= in_array($chave, $cfg['operacoes'], true) ? 'checked' : '' ?>>
                            <span><?= e($info['simbolo']) ?> <?= e($info['nome']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div class="linha">
                <label class="so-aleatorio">Dígitos do 1º número
                    <select name="digitos1">
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                            <option value="<?= $i ?>" <?= $cfg['digitos1'] === $i ? 'selected' : '' ?>>
                                <?= $i ?> (<?= e(implode(' a ', faixa($i))) ?>)
                            </option>
                        <?php endfor; ?>
                    </select>
                </label>
                <label class="so-aleatorio">Dígitos do 2º número
                    <select name="digitos2">
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                            <option value="<?= $i ?>" <?= $cfg['digitos2'] === $i ? 'selected' : '' ?>>
                                <?= $i ?> (<?= e(implode(' a ', faixa($i))) ?>)
                            </option>
                        <?php endfor; ?>
                    </select>
                </label>
                <label>Opções de resposta
                    <select name="opcoes">
                        <?php for ($i = 2; $i <= 6; $i++): ?>
                            <option value="<?= $i ?>" <?= $cfg['opcoes'] === $i ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </label>
            </div>
            <p class="obs so-aleatorio">Na subtração o resultado nunca é negativo, e a divisão é sempre exata (sem resto).</p>

            <button type="submit" class="salvar">Salvar e começar</button>
        </form>
        <form method="post">
            <input type="hidden" name="acao" value="zerar">
            <button type="submit" class="zerar">Zerar placar</button>
        </form>
    </details>
</main>
</body>
</html>
