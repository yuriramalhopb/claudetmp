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
