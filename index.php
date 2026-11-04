<?php
/**
 * Tabuada Divertida — jogo de contas aleatórias para crianças.
 *
 * - Soma, subtração, multiplicação e divisão (escolha quais).
 * - Escolha quantos dígitos tem cada número.
 * - Escolha quantas opções de resposta aparecem.
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

/** Cria uma conta nova de acordo com as configurações. */
function gerarConta(array $cfg): array
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

    return [
        'op'        => $op,
        'a'         => $a,
        'b'         => $b,
        'resposta'  => $r,
        'opcoes'    => gerarOpcoes($r, $cfg['opcoes']),
        'escolhida' => null,
    ];
}

/** Cria respostas erradas parecidas com a certa e embaralha tudo. */
function gerarOpcoes(int $certa, int $quantidade): array
{
    $opcoes = [$certa => true];
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
    ];
}

function placarZerado(): array
{
    return ['acertos' => 0, 'erros' => 0, 'sequencia' => 0, 'recorde' => 0];
}

// ---------- Estado ----------
$_SESSION['config'] ??= PADRAO;
$_SESSION['placar'] ??= placarZerado();
$_SESSION['conta']  ??= gerarConta($_SESSION['config']);

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
        $_SESSION['conta'] = gerarConta($_SESSION['config']);
    } elseif ($acao === 'configurar') {
        $_SESSION['config'] = lerConfig($_POST);
        $_SESSION['conta'] = gerarConta($_SESSION['config']);
    } elseif ($acao === 'zerar') {
        $_SESSION['placar'] = placarZerado();
        $_SESSION['conta'] = gerarConta($_SESSION['config']);
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

$total = $placar['acertos'] + $placar['erros'];
$aproveitamento = $total > 0 ? (int) round($placar['acertos'] * 100 / $total) : 0;
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tabuada Divertida</title>
<link rel="stylesheet" href="estilo.css">
</head>
<body>
<main class="app">
    <header class="topo">
        <h1>🧮 Tabuada Divertida</h1>
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
        <p class="tipo"><?= e(OPERACOES[$conta['op']]['nome']) ?></p>
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
            <form method="post">
                <input type="hidden" name="acao" value="proxima">
                <button type="submit" class="proxima" autofocus>Próxima conta ➜</button>
            </form>
        <?php else: ?>
            <p class="mensagem">Escolha a resposta certa! 👆</p>
        <?php endif; ?>
    </section>

    <details class="config" <?= $total === 0 ? 'open' : '' ?>>
        <summary>⚙️ Configurações</summary>
        <form method="post">
            <input type="hidden" name="acao" value="configurar">

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
                <label>Dígitos do 1º número
                    <select name="digitos1">
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                            <option value="<?= $i ?>" <?= $cfg['digitos1'] === $i ? 'selected' : '' ?>>
                                <?= $i ?> (<?= e(implode(' a ', faixa($i))) ?>)
                            </option>
                        <?php endfor; ?>
                    </select>
                </label>
                <label>Dígitos do 2º número
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
            <p class="obs">Na subtração o resultado nunca é negativo, e a divisão é sempre exata (sem resto).</p>

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
