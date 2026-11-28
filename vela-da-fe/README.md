# 🕯️ Vela da Fé — velas virtuais para a sua igreja

Sistema completo em **PHP 8.1+**, sem frameworks e sem dependências externas. Roda em qualquer hospedagem com PHP e **MySQL/MariaDB** — ou com **SQLite** (um único arquivo, zero configuração).

Quem acende a vela **não precisa de conta**. A equipe da igreja usa um painel com login.

## O que o sistema tem

| Área | Recursos |
|---|---|
| **Acender vela** | Tipos de vela (cor e significado), durações (24 h, 3, 7, 15, 30 dias…), motivo (saúde, família, gratidão, falecidos…), nome, "por quem", intenção (500 caracteres), escolha do que aparece no mural (nome e/ou intenção), pré-visualização da vela e do total em tempo real |
| **Mural** | Velas acesas agora (chama animada em CSS puro), filtros por motivo/tipo/ordem, **Memorial** das velas apagadas nos últimos 30 dias, contadores ao vivo |
| **Rezar pela vela** | Botão "Rezar por esta intenção" (uma vez por pessoa, por vela, por dia), contador de orações |
| **Velas pagas (Pix)** | Valor = tipo + duração. Gera **QR Code e "copia e cola" Pix** com o identificador `VELA<código>`; a pessoa avisa "Já fiz o Pix"; a equipe confere no banco e confirma com um clique (a vela acende na hora). O dinheiro cai direto na conta da igreja — sem intermediário |
| **Intenções para a missa** | A pessoa marca "lembrar na Santa Missa"; a equipe vê a lista do período (só ela lê o texto), **imprime a folha** e marca como rezadas |
| **Minha vela** | Link privado (token secreto) para acompanhar, compartilhar (WhatsApp / Web Share), baixar o **certificado** para imprimir, **acender novamente** com um clique e cancelar pedido Pix |
| **E-mails** | Link da vela, Pix recebido/confirmado, aviso "falta menos de 24 h" e "sua vela se apagou — acender de novo" (opcionais, só para quem informou e-mail), reenvio de links em "Minhas velas" |
| **Conteúdo** | Orações (já vem com Pai-Nosso, Ave-Maria, Salve-Rainha, Credo…), **Palavra do dia**, **horários das missas**, **graças alcançadas** (testemunhos com aprovação), página da igreja, política de privacidade (LGPD) |
| **Moderação** | Triagem automática (links, e-mails, telefones, palavras bloqueadas) ou moderação manual de tudo; texto recusado deixa a vela anônima; remover/restaurar/prolongar/apagar velas |
| **Painel** | Visão geral com gráficos, velas, pagamentos, moderação, missas, graças, orações, mensagens, horários, tipos de vela, durações e valores, financeiro (+CSV), equipe (administrador / moderador), configurações, sistema (e-mail de teste, backups, rotinas), registro de atividades |
| **SEO** | `sitemap.xml`, `robots.txt`, Open Graph |

## Instalação (5 minutos)

1. Envie os arquivos para o servidor. **Aponte o domínio para a pasta `public/`.** (Se a hospedagem não permitir, o `.htaccess` da raiz já redireciona e bloqueia o código-fonte.)
2. Copie `.env.example` para `.env` e ajuste:
   - `APP_URL` (endereço público, com `https://`)
   - Banco: deixe `DB_DRIVER=sqlite` para começar sem configurar nada, **ou** crie um banco MySQL/MariaDB e use `DB_DRIVER=mysql` com `DB_HOST/DB_NAME/DB_USER/DB_PASS`.
   - `ADMIN_EMAIL` e `ADMIN_PASSWORD` (primeiro administrador).
3. Garanta permissão de escrita em `storage/` (e na pasta do `.sqlite`, se usar SQLite).
4. Abra o site. As tabelas e o conteúdo inicial são criados **sozinhos** na primeira visita. Entre em `/admin`, depois **apague `ADMIN_PASSWORD` do `.env`**.

Alternativa pela linha de comando: `php bin/install.php admin@igreja.com.br` (pede a senha).

### Primeiros passos no painel
1. **Configurações**: nome da igreja, endereço, WhatsApp da secretaria, **chave Pix** (sem ela, só as velas gratuitas aparecem), textos do site.
2. **Tipos de vela** e **Durações e valores**: ajuste ao jeito da sua igreja (valor 0,00 = grátis).
3. **Horários das missas**: troque os horários de exemplo.
4. **Sistema → E-mail de teste**: confira o envio de e-mails.

### E-mail (recomendado)
No `.env`: `MAIL_DRIVER=smtp` + `SMTP_HOST/SMTP_PORT/SMTP_USER/SMTP_PASS` (Gmail com senha de app, Outlook, Zoho, Hostinger, Locaweb, SES…). Com `MAIL_DRIVER=log` as mensagens só são gravadas em `storage/logs/mail-*.log`.

### Rotinas automáticas (cron)
```
*/5 * * * *  php /caminho/do/site/bin/cron.php >/dev/null 2>&1
```
Envia e-mails, avisa sobre velas que vão apagar, cancela Pix não pagos (72 h), apaga e-mails/IPs de velas antigas (LGPD, 6 meses) e faz **backup diário**. Sem cron, o sistema tenta rodar sozinho depois de entregar páginas (hospedagens com PHP-FPM).

### Backup e restauração
- Painel: **Sistema → Criar backup** (e baixar), ou `php bin/backup.php`. Guarde cópias fora do servidor (contêm e-mails).
- Restaurar: `php bin/restore.php vela-AAAAMMDD-HHMMSS.sql.gz`
- Esqueceu a senha? `php bin/reset-password.php email@igreja.com.br`

### Nginx
Veja `deploy/nginx.conf`.

## Segurança e privacidade (LGPD)

- **SQL injection**: 100% prepared statements reais. **XSS**: tudo escapado; **CSP com nonce** (sem scripts/estilos inline). **CSRF** em todo POST.
- Painel: senhas Argon2id, bloqueio por tentativas (e-mail e IP), sessão endurecida, trocar senha derruba as outras sessões, perfis **Administrador** e **Moderador**, ações registradas.
- Vela: endereço público **não sequencial** (12 caracteres aleatórios); gerenciamento só por **token secreto** (guardado como hash); honeypot e limites por IP (por hora e por dia).
- **IP nunca é guardado em texto**: só um HMAC com segredo do site. E-mail e esse HMAC são **apagados automaticamente** depois que a vela se apaga (6 meses).
- Intenções privadas e textos retidos pela moderação **nunca** saem nas páginas públicas. Exportação CSV protegida contra injeção de fórmulas.
- Acesso a dados pessoais pelo painel fica na auditoria.

## Estrutura

```
app/Core        núcleo (rotas, sessão, CSRF, banco MySQL/SQLite, validação)
app/Models      acesso a dados
app/Services    Pix, e-mail (SMTP), moderação, rotinas, backup, conteúdo inicial
app/Controllers site público  ·  app/Controllers/Admin painel
app/Admin       cadastros simples do painel (tipos, durações, orações…) em um só arquivo
app/Views       telas  ·  public/ (CSS/JS/QR)  ·  database/{mysql,sqlite}/schema.sql
bin/            install, cron, backup, restore, reset-password, seed-demo
tests/          39 testes automáticos
```

## Testes

```
php tests/run.php                                   # SQLite temporário
TEST_DB_DRIVER=mysql TEST_DB_PASS=senha php tests/run.php   # MySQL/MariaDB (banco vela_test, apagado e recriado)
```

Para ver o visual com dados de exemplo: `php bin/seed-demo.php` e `php -S 127.0.0.1:8000 -t public public/index.php`.

## Personalizando
- Cores e fontes: variáveis no topo de `public/assets/css/app.css`.
- Textos, moderação, limites e Pix: **Admin → Configurações**.
- Novo motivo de vela: `Candle::CATEGORIES` em `app/Models/Candle.php`.
- Novo cadastro simples no painel: uma entrada em `app/Admin/Resources.php`.
