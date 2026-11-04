# 🌎 Jogo das Bandeiras

Jogo em PHP para aprender as bandeiras de **todos os países do mundo** e dos **estados do Brasil**,
sempre com **3 opções de resposta**.

- **195 países** (os 193 membros da ONU + Vaticano e Palestina) separados por continente:
  África, América do Sul, América do Norte/Central/Caribe, Ásia, Europa e Oceania.
- **27 estados brasileiros** (26 estados + Distrito Federal) — dá para jogar só com eles, só com os
  países ou com tudo misturado.
- **Modos:** ver a bandeira e escolher o nome, ver o nome e escolher a bandeira, ou misturado.
- **Nível fácil** (opções de qualquer lugar) ou **difícil** (opções do mesmo continente ou da
  mesma região do Brasil).
- Ao clicar numa opção, o sistema **verifica se está certa**, mostra a resposta correta e uma
  curiosidade (continente; para estados: região, capital e sigla).
- Placar com acertos, erros, acertos seguidos, recorde e aproveitamento.
- As bandeiras **não se repetem** até todas as escolhidas aparecerem (mostra "12 de 222").
- País só é comparado com país e estado com estado; bandeiras quase iguais (Chade/Romênia,
  Indonésia/Mônaco) nunca aparecem juntas.
- Sem login, sem banco de dados e sem internet externa: as bandeiras são arquivos SVG do próprio
  projeto (usa a sessão do PHP).

## Como rodar

Precisa só do PHP 8+:

```bash
php -S localhost:8000
```

Depois abra http://localhost:8000 no navegador (funciona também no celular).
Em hospedagem comum, basta enviar todos os arquivos e pastas para uma pasta do site.

## Arquivos

| Arquivo | O que é |
|---|---|
| `index.php` | O jogo (perguntas, respostas, placar e configurações) |
| `dados.php` | Lista de países (nome e continente) e estados (nome, região e capital) |
| `bandeiras/paises/` | 195 bandeiras de países em SVG ([flag-icons](https://github.com/lipis/flag-icons), MIT) |
| `bandeiras/estados/` | 27 bandeiras dos estados em SVG (Wikimedia Commons, domínio público) |
| `bandeiras/LICENCAS.md` | Origem e licença de cada bandeira |
| `estilo.css` | Visual |

## App no celular (PWA)

O jogo pode ser instalado como aplicativo, com ícone na tela inicial e abrindo em tela cheia.

- **Android / Chrome / Edge / computador:** aparece o botão **📲 Instalar app** no topo (ou use o
  menu do navegador → "Instalar app" / "Adicionar à tela inicial").
- **iPhone / iPad (Safari):** toque em **Compartilhar** ⬆️ → **Adicionar à Tela de Início**.
  O botão "Instalar app" mostra essa dica.

> ⚠️ Para instalar, o site precisa estar em **HTTPS**. Em `http://localhost` também funciona para testes.

As perguntas são geradas pelo PHP, então o jogo precisa de conexão com o servidor. Sem conexão,
aparece uma tela avisando "sem internet" com um botão para tentar de novo.

Arquivos do PWA: `manifest.webmanifest`, `sw.js` (service worker), `app.js` (registro e botão de
instalar), `offline.html` e a pasta `icones/`. Ao mudar o visual, troque `VERSAO` em `sw.js`
(por exemplo, `bandeiras-v2`) para os celulares baixarem os arquivos novos.
