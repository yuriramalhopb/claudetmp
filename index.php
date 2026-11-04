<?php
/**
 * Jogo das Bandeiras — adivinhe a bandeira de todos os países do mundo
 * e dos estados do Brasil, com 3 opções de resposta.
 *
 * - Escolha quais continentes e/ou os estados brasileiros entram no jogo.
 * - Modo "Qual é o nome?" (vê a bandeira), "Qual é a bandeira?" (vê o nome) ou misturado.
 * - Nível fácil (opções de qualquer lugar) ou difícil (opções do mesmo continente/região).
 * - Ao clicar numa opção, o PHP verifica se está certa.
 * - As bandeiras não se repetem até todas da seleção aparecerem.
 * Sem login e sem banco de dados: tudo fica na sessão do navegador.
 */
declare(strict_types=1);

require __DIR__ . '/dados.php';

session_start();

const OPCOES = 3;

const MODOS = [
    'nome'     => '🏳️ Ver a bandeira e escolher o nome',
    'bandeira' => '🔤 Ver o nome e escolher a bandeira',
    'misto'    => '🔀 Misturado',
];

const NIVEIS = [
    'facil'   => '🙂 Fácil — opções de qualquer lugar',
    'dificil' => '🧠 Difícil — opções do mesmo continente ou região',
];

const PADRAO = [
    'grupos' => ['africa', 'america-sul', 'america-norte', 'asia', 'europa', 'oceania', 'brasil'],
    'modo'   => 'nome',
    'nivel'  => 'facil',
];

function e(string|int $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

/** Chaves dos itens que entram no jogo com a configuração atual. */
function itensDaSelecao(array $cfg): array
{
    return array_keys(array_filter(
        todosOsItens(),
        static fn (array $i): bool => in_array($i['grupo'], $cfg['grupos'], true)
    ));
}

function tipoDo(string $chave): string
{
    return $chave[0]; // "p" = país, "e" = estado
}

function saoParecidas(string $a, string $b): bool
{
    $a = strtoupper(substr($a, 2));
    $b = strtoupper(substr($b, 2));
    foreach (PARECIDAS as $par) {
        if (in_array($a, $par, true) && in_array($b, $par, true)) {
            return true;
        }
    }
    return false;
}

/** Escolhe 2 respostas erradas do mesmo tipo (país com país, estado com estado) e embaralha. */
function gerarOpcoes(string $certa, array $cfg): array
{
    $itens   = todosOsItens();
    $selecao = itensDaSelecao($cfg);
    $tipo    = tipoDo($certa);

    $serve = static fn (string $k): bool =>
        $k !== $certa && tipoDo($k) === $tipo && !saoParecidas($k, $certa);

    // Ordem de preferência: (difícil: mesmo continente/região) → mesma seleção → qualquer um do mesmo tipo.
    $fontes = [];
    if ($cfg['nivel'] === 'dificil') {
        $fontes[] = array_filter(array_keys($itens), static fn ($k) => $itens[$k]['sub'] === $itens[$certa]['sub']);
    }
    $fontes[] = $selecao;
    $fontes[] = array_keys($itens);

    $opcoes = [$certa];
    foreach ($fontes as $fonte) {
        $candidatas = array_values(array_filter($fonte, $serve));
        shuffle($candidatas);
        foreach ($candidatas as $k) {
            if (count($opcoes) >= OPCOES) {
                break 2;
            }
            $repetida = false;
            foreach ($opcoes as $o) {
                if ($o === $k || saoParecidas($o, $k)) {
                    $repetida = true;
                    break;
                }
            }
            if (!$repetida) {
                $opcoes[] = $k;
            }
        }
    }

    shuffle($opcoes);
    return $opcoes;
}

/** Baralho embaralhado: cada bandeira aparece uma vez antes de repetir. */
function novoBaralho(array $cfg): array
{
    $cartas = itensDaSelecao($cfg);
    shuffle($cartas);
    return ['cartas' => $cartas, 'posicao' => 0, 'voltas' => 0];
}

function gerarPergunta(array $cfg): array
{
    $b = &$_SESSION['baralho'];
    if ($b['posicao'] >= count($b['cartas'])) {
        $voltas = $b['voltas'] + 1;
        $ultima = $b['cartas'][$b['posicao'] - 1] ?? null;
        $b = novoBaralho($cfg);
        $b['voltas'] = $voltas;
        // Não começa a nova volta com a mesma bandeira que acabou de sair.
        if (count($b['cartas']) > 1 && $b['cartas'][0] === $ultima) {
            $b['cartas'][] = array_shift($b['cartas']);
        }
    }
    $chave = $b['cartas'][$b['posicao']];
    $b['posicao']++;
    unset($b);

    $modo = $cfg['modo'] === 'misto' ? (random_int(0, 1) ? 'nome' : 'bandeira') : $cfg['modo'];

    return [
        'item'      => $chave,
        'modo'      => $modo,
        'opcoes'    => gerarOpcoes($chave, $cfg),
        'escolhida' => null,
    ];
}

function lerConfig(array $post): array
{
    $grupos = array_values(array_intersect(array_keys(GRUPOS), (array) ($post['grupos'] ?? [])));
    $modo   = (string) ($post['modo'] ?? '');
    $nivel  = (string) ($post['nivel'] ?? '');

    return [
        'grupos' => $grupos ?: PADRAO['grupos'],
        'modo'   => isset(MODOS[$modo]) ? $modo : PADRAO['modo'],
        'nivel'  => isset(NIVEIS[$nivel]) ? $nivel : PADRAO['nivel'],
    ];
}

function placarZerado(): array
{
    return ['acertos' => 0, 'erros' => 0, 'sequencia' => 0, 'recorde' => 0];
}

function recomecar(array $cfg): void
{
    $_SESSION['baralho']  = novoBaralho($cfg);
    $_SESSION['pergunta'] = gerarPergunta($cfg);
}

// ---------- Estado ----------
$_SESSION['config'] ??= PADRAO;
$_SESSION['placar'] ??= placarZerado();
if (!isset($_SESSION['baralho'], $_SESSION['pergunta']) || !isset(todosOsItens()[$_SESSION['pergunta']['item']])) {
    recomecar($_SESSION['config']);
}

// ---------- Ações (POST → redireciona, para F5 não repetir a resposta) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'responder' && $_SESSION['pergunta']['escolhida'] === null) {
        $valor = (string) ($_POST['valor'] ?? '');
        if (in_array($valor, $_SESSION['pergunta']['opcoes'], true)) {
            $_SESSION['pergunta']['escolhida'] = $valor;
            $p = &$_SESSION['placar'];
            if ($valor === $_SESSION['pergunta']['item']) {
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
        $_SESSION['pergunta'] = gerarPergunta($_SESSION['config']);
    } elseif ($acao === 'configurar') {
        $_SESSION['config'] = lerConfig($_POST);
        recomecar($_SESSION['config']);
    } elseif ($acao === 'zerar') {
        $_SESSION['placar'] = placarZerado();
        recomecar($_SESSION['config']);
    }

    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'), true, 303);
    exit;
}

$itens     = todosOsItens();
$cfg       = $_SESSION['config'];
$placar    = $_SESSION['placar'];
$pergunta  = $_SESSION['pergunta'];
$baralho   = $_SESSION['baralho'];
$certa     = $itens[$pergunta['item']];
$verNome   = $pergunta['modo'] === 'nome';
$respondeu = $pergunta['escolhida'] !== null;
$acertou   = $respondeu && $pergunta['escolhida'] === $pergunta['item'];
$ehEstado  = tipoDo($pergunta['item']) === 'e';

$elogios = ['Muito bem! 🎉', 'Acertou! ⭐', 'Mandou bem! 🚀', 'Isso aí! 🏆', 'Show de bola! ⚽', 'Que craque! 🌟'];
$animos  = ['Quase! Vamos para a próxima? 💪', 'Não foi dessa vez, mas você consegue! 🙂', 'Errar faz parte de aprender! 📚'];
$mensagem = $respondeu ? ($acertou ? $elogios[array_rand($elogios)] : $animos[array_rand($animos)]) : '';

$total = $placar['acertos'] + $placar['erros'];
$aproveitamento = $total > 0 ? (int) round($placar['acertos'] * 100 / $total) : 0;
$qtdSelecao = count($baralho['cartas']);
$contagem = array_count_values(array_column($itens, 'grupo'));
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0b7a3e">
<meta name="description" content="Jogo das bandeiras de todos os países do mundo e dos estados do Brasil, com 3 opções de resposta.">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Bandeiras">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<title>Jogo das Bandeiras</title>
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
        <h1>🌎 Jogo das Bandeiras</h1>
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
            <?= $ehEstado ? 'Estado do Brasil' : 'País' ?>
            · <?= e($baralho['posicao']) ?> de <?= e($qtdSelecao) ?>
            <?php if ($baralho['voltas'] > 0): ?>· <?= e($baralho['voltas'] + 1) ?>ª volta<?php endif; ?>
        </p>

        <?php if ($verNome): ?>
            <p class="pergunta">De quem é esta bandeira?</p>
            <div class="bandeira-grande">
                <img src="<?= e($certa['img']) ?>" alt="Bandeira misteriosa">
            </div>
        <?php else: ?>
            <p class="pergunta">Qual é a bandeira <?= $ehEstado ? 'do estado' : 'do país' ?>…</p>
            <p class="nome-grande"><?= e($certa['nome']) ?>?</p>
        <?php endif; ?>

        <form method="post" class="opcoes <?= $verNome ? 'de-nomes' : 'de-bandeiras' ?>">
            <input type="hidden" name="acao" value="responder">
            <?php foreach ($pergunta['opcoes'] as $opcao):
                $item = $itens[$opcao];
                $classe = '';
                if ($respondeu) {
                    if ($opcao === $pergunta['item']) {
                        $classe = 'correta';
                    } elseif ($opcao === $pergunta['escolhida']) {
                        $classe = 'incorreta';
                    } else {
                        $classe = 'apagada';
                    }
                }
            ?>
                <button type="submit" name="valor" value="<?= e($opcao) ?>"
                        class="opcao <?= $classe ?>" <?= $respondeu ? 'disabled' : '' ?>>
                    <?php if ($verNome): ?>
                        <?= e($item['nome']) ?>
                    <?php else: ?>
                        <img src="<?= e($item['img']) ?>"
                             alt="<?= $respondeu ? 'Bandeira: ' . e($item['nome']) : 'Opção de bandeira' ?>">
                        <?php if ($respondeu): ?><span class="legenda"><?= e($item['nome']) ?></span><?php endif; ?>
                    <?php endif; ?>
                </button>
            <?php endforeach; ?>
        </form>

        <?php if ($respondeu): ?>
            <p class="mensagem"><?= e($mensagem) ?></p>
            <p class="dica">
                <?php if (!$acertou): ?>
                    A resposta certa é <strong><?= e($certa['nome']) ?></strong>.<br>
                <?php else: ?>
                    <strong><?= e($certa['nome']) ?></strong><br>
                <?php endif; ?>
                <span class="curiosidade"><?= e($certa['info']) ?></span>
            </p>
            <form method="post">
                <input type="hidden" name="acao" value="proxima">
                <button type="submit" class="proxima" autofocus>Próxima bandeira ➜</button>
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
                <legend>Quais bandeiras?</legend>
                <div class="checks">
                    <?php foreach (GRUPOS as $chave => $info): ?>
                        <label class="check <?= $chave === 'brasil' ? 'destaque' : '' ?>">
                            <input type="checkbox" name="grupos[]" value="<?= e($chave) ?>"
                                <?= in_array($chave, $cfg['grupos'], true) ? 'checked' : '' ?>>
                            <span><?= e($info['emoji']) ?> <?= e($info['nome']) ?>
                                <small>(<?= e($contagem[$chave]) ?>)</small></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="obs">
                    Para jogar só com os estados brasileiros, deixe marcado apenas
                    <strong>Estados do Brasil</strong>.
                </p>
            </fieldset>

            <fieldset>
                <legend>Como jogar?</legend>
                <div class="radios">
                    <?php foreach (MODOS as $chave => $nome): ?>
                        <label class="check">
                            <input type="radio" name="modo" value="<?= e($chave) ?>"
                                <?= $cfg['modo'] === $chave ? 'checked' : '' ?>>
                            <span><?= e($nome) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <fieldset>
                <legend>Nível</legend>
                <div class="radios">
                    <?php foreach (NIVEIS as $chave => $nome): ?>
                        <label class="check">
                            <input type="radio" name="nivel" value="<?= e($chave) ?>"
                                <?= $cfg['nivel'] === $chave ? 'checked' : '' ?>>
                            <span><?= e($nome) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <p class="obs">
                Sempre 3 opções de resposta. País é comparado com país e estado com estado.
                As bandeiras não se repetem até todas as escolhidas aparecerem.
            </p>

            <button type="submit" class="salvar">Salvar e começar</button>
        </form>
        <form method="post">
            <input type="hidden" name="acao" value="zerar">
            <button type="submit" class="zerar">Zerar placar e recomeçar</button>
        </form>
    </details>

    <p class="rodape">
        Bandeiras: <a href="bandeiras/LICENCAS.md">flag-icons (MIT) e Wikimedia Commons (domínio público)</a>.
    </p>
</main>
</body>
</html>
