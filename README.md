# 🧮 Tabuada Divertida

Jogo em PHP para crianças treinarem contas com números aleatórios.

- **Soma, subtração, multiplicação e divisão** — escolha quais aparecem.
- **Quantos dígitos** tem o 1º e o 2º número (1 a 4).
- **Quantas opções de resposta** (2 a 6).
- Ao clicar numa opção, o sistema **verifica se está certa**, mostra a resposta correta e conta acertos, erros, acertos seguidos e recorde.
- Subtração nunca dá negativo e divisão é sempre exata (sem resto).
- Sem login e sem banco de dados (usa a sessão do PHP).

## Como rodar

Precisa só do PHP 8+:

```bash
php -S localhost:8000
```

Depois abra http://localhost:8000 no navegador (funciona também no celular).
Em hospedagem comum, basta enviar `index.php` e `estilo.css` para uma pasta do site.

## App no celular (PWA)

A tabuada pode ser instalada como aplicativo, com ícone na tela inicial e abrindo em tela cheia.

- **Android / Chrome / Edge / computador:** aparece o botão **📲 Instalar app** no topo (ou use o menu do navegador → "Instalar app" / "Adicionar à tela inicial").
- **iPhone / iPad (Safari):** toque em **Compartilhar** ⬆️ → **Adicionar à Tela de Início**. O botão "Instalar app" mostra essa dica.

> ⚠️ Para instalar, o site precisa estar em **HTTPS** (qualquer hospedagem com certificado, por exemplo Let's Encrypt). Em `http://localhost` também funciona para testes.

As contas são geradas pelo PHP, então o jogo precisa de internet. Sem conexão, aparece uma tela avisando "sem internet" com um botão para tentar de novo.

Arquivos do PWA: `manifest.webmanifest`, `sw.js` (service worker), `app.js` (registro e botão de instalar), `offline.html` e a pasta `icones/`.
Ao mudar o visual, troque `VERSAO` em `sw.js` (por exemplo, `tabuada-v2`) para os celulares baixarem os arquivos novos.
