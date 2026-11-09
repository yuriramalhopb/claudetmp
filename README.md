# 🦆 Ducks Offroad — site e sistema da turma de trilha

Site completo em **PHP 8.1+ orientado a objetos**, sem frameworks nem dependências externas usando **MySQL 5.7+/8 ou MariaDB 10.3+** (roda em qualquer hospedagem com PHP + MySQL).

## Funcionalidades

| Área | O que tem |
|---|---|
| **Início** | Hero, próximas trilhas, últimas fotos, feed do Instagram, novos trilheiros |
| **Trilhas do ano** | Calendário anual (`/trilhas-do-ano/2026`) com banner/flyer, data, cidade/UF, valor, categoria, nível, prazo de inscrição, link do Instagram e da inscrição de cada evento; filtros por estado, mês, categoria, nível e turma; navegação entre anos e por mês; download do calendário inteiro em `.ics` |
| **Agenda** | Trilhas da Ducks e de outras turmas, filtros (tipo, nível, busca), eventos passados, confirmação de presença ("Eu vou!"), lista de quem vai, exportar `.ics`, Google Agenda, mapa, compartilhar (WhatsApp / Web Share) |
| **Galeria** | Álbuns, lightbox com teclado e swipe, paginação, trilheiros enviam fotos (com aprovação do admin) |
| **Instagram** | Sincronização automática via API oficial (imagens baixadas e salvas localmente, token renovado sozinho) **ou** posts adicionados pelo link (incorporação oficial) — inclusive posts de outras turmas |
| **Trilheiros** | Cadastro completo (moto, cilindrada, nível, tipo sanguíneo, contato de emergência, foto), aprovação pelo admin, diretório público, perfil |
| **App no celular (PWA)** | Instalável no Android, iPhone e computador (ícone do pato na tela inicial, tela cheia, atalhos para Agenda/Calendário/Galeria/Conta), botão "Instalar app", instruções automáticas no iPhone, página `/instalar` e tela "sem sinal" quando estiver offline |
| **Minha conta** | Editar perfil e foto, trocar senha, minhas trilhas, baixar meus dados, excluir conta (LGPD) |
| **Cadastro de trilha** | Nome, categoria, nível, distância, organizador, início/término, cidade, estado (UF), local de encontro, valor, inscrições até, vagas (controla o "Eu vou!"), o que está incluso, Instagram do evento, link de inscrição, WhatsApp da organização, banner e descrição |
| **Painel admin** | Dashboard, aprovar/bloquear/promover trilheiros, ficha completa, exportar CSV, CRUD de eventos (com banner) e aprovação de sugestões, álbuns e fotos, Instagram, mensagens de contato, log de auditoria, página **Sistema** (cron, e-mails, notificações, backups) |
| **Avisos automáticos** | E-mail (SMTP) e notificação no celular: cadastro aprovado, trilha nova, lembrete na véspera, pagamento confirmado, marcação em foto. Cada trilheiro escolhe o que recebe |
| **Ficha de segurança** | Por trilha (só admin): telefone, moto, **tipo sanguíneo, contato de emergência**, termo aceito, pagamento e presença no dia. Versão para imprimir e planilha |
| **Termo de responsabilidade** | Aceito no "Eu vou!" com data, hora, IP e versão do texto. Texto editável no painel |
| **Pix** | Trilhas com inscrição paga geram QR Code e "copia e cola" para a chave Pix da turma; trilheiro envia comprovante (privado); admin confirma |
| **Percurso (GPX)** | Upload do GPX do Wikiloc/Strava/Garmin: mapa, km, subida acumulada, perfil de elevação; download só para trilheiros |
| **Fotos** | Curtidas, comentários e marcação de trilheiros (com notificação); "Aparece nas fotos" no perfil |
| **Ranking e selos** | Ranking anual/geral (trilhas e km), selos no perfil (primeira trilha, 10 trilhas, 1.000 km, extremo, veterano…) |
| **SEO** | sitemap.xml, robots.txt, dados estruturados de evento para o Google, Open Graph para compartilhar |
| **App Android** | APK/AAB gerado a partir do site (TWA) — ver `android/README.md` |

## Segurança

- **SQL injection:** 100% prepared statements (PDO MySQL, emulação desligada, `utf8mb4`, modo estrito); identificadores validados.
- **XSS:** todo dado impresso com `e()`; **Content-Security-Policy com nonce**, sem scripts/handlers inline.
- **CSRF:** token por sessão validado em todo `POST` (comparação em tempo constante).
- **Senhas:** Argon2id (fallback bcrypt), rehash automático, política mínima (8+ caracteres, letras e números).
- **Força bruta:** bloqueio por e-mail e por IP (persistido no banco); limites em cadastro, contato, recuperação de senha e uploads; honeypot anti-robô.
- **Sessão:** cookie `HttpOnly` + `SameSite=Lax` + `Secure`/`__Host-` em HTTPS, modo estrito, ID regenerado no login e a cada 15 min, expiração por inatividade, vínculo ao navegador. Trocar senha ou bloquear usuário **derruba todas as sessões** dele.
- **Uploads:** tipo detectado pelo conteúdo (finfo + getimagesize), limite de tamanho e resolução, **imagem recodificada com GD** (remove EXIF/GPS e qualquer código embutido), nome aleatório, pasta sem execução de PHP.
- **Recuperação de senha:** token aleatório de 256 bits, só o hash é salvo, validade de 60 min, uso único, resposta que não revela se o e-mail existe.
- **Privacidade:** tipo sanguíneo, contato de emergência, e-mail e nascimento só para admins (acesso à ficha fica no log); telefone só para trilheiros logados e se o membro autorizar.
- **Verificação em duas etapas (2FA):** obrigatória para administradores (Google Authenticator/Authy), opcional para trilheiros; segredo criptografado no banco, códigos de recuperação de uso único, proteção contra reutilização do código.
- **Dados sensíveis:** comprovantes de pagamento e arquivos GPX ficam fora da pasta pública e só são entregues após checar permissão; ficha de segurança e comprovantes ficam registrados na auditoria.
- **Notificações e e-mail:** push com criptografia ponta a ponta (padrão Web Push) e somente para os serviços oficiais dos navegadores; SMTP sempre com certificado verificado.
- **Outros:** cabeçalhos HSTS, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy; proteção contra open redirect; CSV protegido contra injeção de fórmulas; SSRF bloqueado no download de imagens do Instagram (só domínios do Instagram via HTTPS); erros nunca exibidos em produção (vão para `storage/logs`); código fora da pasta pública.

## Instalação

```bash
cp .env.example .env
```

Crie o banco e um usuário no MySQL (ou pelo painel da hospedagem):

```sql
CREATE DATABASE ducks_offroad CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ducks'@'localhost' IDENTIFIED BY 'uma-senha-forte';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, INDEX, ALTER, REFERENCES ON ducks_offroad.* TO 'ducks'@'localhost';
```

Edite o `.env` (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `APP_URL`, admin inicial). Depois crie as tabelas e o primeiro admin:

```bash
php bin/install.php
```

Rodar localmente:

```bash
php -S localhost:8000 -t public public/index.php
```

Dados de exemplo (opcional):

```bash
php bin/seed-demo.php
```

### Hospedagem

- Aponte o **DocumentRoot para `public/`** (Apache já tem `.htaccess`; Nginx: veja `deploy/nginx.conf`).
- Se não puder mudar o DocumentRoot, o `.htaccess` da raiz redireciona para `public/` e bloqueia as pastas internas.
- As tabelas ficam em `database/schema.sql` e as alterações posteriores em `database/migrations/`. Ambas são aplicadas automaticamente (no primeiro acesso ou com `php bin/install.php`); cada migração roda uma única vez e **preserva os dados** existentes.
- Use um usuário do MySQL **exclusivo do site**, só com permissão no banco `ducks_offroad` (nunca o `root`).
- Dê permissão de escrita para `storage/` e `public/uploads/`.
- Use **HTTPS** (Let's Encrypt).

### Instagram

1. Deixe o Instagram da turma como conta **Profissional** (Criador ou Empresa).
2. Em developers.facebook.com crie um app → produto **Instagram** → "API com login do Instagram" → gere o token da conta.
3. Cole o token em **Painel → Instagram** (ou em `INSTAGRAM_ACCESS_TOKEN`).
4. O site sincroniza sozinho a cada `INSTAGRAM_SYNC_MINUTES`. Opcionalmente agende o cron:

```bash
0 * * * * php /caminho/do/site/bin/instagram-sync.php
```

Sem token, ainda dá para adicionar qualquer post público pelo link no painel.

## Depois de instalar (importante)

1. **Agendador (cron)** — uma linha no painel da hospedagem, a cada 5 minutos. Sem ele, avisos, lembretes, notificações e backups automáticos não acontecem:
   ```
   */5 * * * * php /caminho/do/site/bin/cron.php >> /caminho/do/site/storage/logs/cron.log 2>&1
   ```
2. **E-mail** — no `.env`, `MAIL_DRIVER=smtp` e os dados `SMTP_*` (exemplos para Gmail, Hostinger e Outlook no `.env.example`). Teste em **Painel → Sistema → Enviar e-mail de teste**.
3. **2FA** — no primeiro acesso ao painel, o admin configura o Google Authenticator e **guarda os códigos de recuperação**.
4. **Pix** — preencha `PIX_KEY`, `PIX_NAME` e `PIX_CITY` no `.env` para cobrar inscrições.
5. **Termo** — revise o texto em **Painel → Termo**.
6. **Backup** — automático todo dia (`BACKUP_HOUR`), mantido por 30 dias em `storage/backups`. Copie de vez em quando para fora do servidor. Comandos:
   ```bash
   php bin/backup.php
   ```
   ```bash
   php bin/restore.php
   ```
7. **Esqueceu a senha do admin?** Redefina pelo servidor (também libera bloqueios de login):
   ```bash
   sudo -u www-data php bin/reset-password.php seu@email.com
   ```
8. **Guarde o `.env`** em local seguro: a `APP_KEY` é necessária para o 2FA continuar funcionando.

## Testes automáticos

Os testes rodam no GitHub a cada envio (aba **Actions → Testes**). Para rodar no seu computador, use um banco **separado** cujo nome termina em `_test` (ele é apagado e recriado):
```bash
TEST_DB_USER=root TEST_DB_PASS=senha php tests/run.php
```

## App no celular (PWA)

O site já é um aplicativo instalável — não precisa de Play Store/App Store.

- **Requisito:** o site precisa estar em **HTTPS** (no teste local, `localhost` também funciona).
- **Android (Chrome):** aparece o botão **📲 Instalar app** no menu do site, ou menu ⋮ → *Instalar app*.
- **iPhone (Safari):** um aviso explica: *Compartilhar → Adicionar à Tela de Início*.
- **Instruções para a turma:** compartilhe o link `https://seu-dominio/instalar`.
- **Privacidade:** o service worker (`public/sw.js`) só guarda CSS/JS/ícones e imagens públicas. Páginas HTML (que podem ter dados pessoais, painel admin, tokens) **nunca** ficam no aparelho; ao sair da conta o cache de imagens é limpo.
- **Atualizações:** ao mudar o `sw.js`, aumente `VERSION` nele para os celulares baixarem a versão nova. `sw.js` e `manifest.webmanifest` são servidos com `Cache-Control: no-cache` (`.htaccess` e `deploy/nginx.conf`).
- **Ícones:** `public/assets/icons/` (192/512, versões *maskable* para Android e `apple-touch-icon` para iPhone).

## Estrutura

```
app/
  Core/          App, Router, Request, Response, View, Database, Session, Csrf, Auth, RateLimiter, Validator, SecurityHeaders...
  Models/        User, Event, Album, Photo, InstagramPost, Message, Setting, PasswordReset, AuditLog
  Services/      InstagramService, ImageUploader, Calendar, Mailer
  Controllers/   públicos + Admin/
  Views/         templates PHP (layouts, partials, páginas, admin)
  routes.php     todas as rotas e middlewares (guest, auth, approved, admin)
bin/             install, cron, backup, restore, instagram-sync, seed-demo
tests/           testes unitários e de ponta a ponta (php tests/run.php)
android/         geração do app Android (APK/AAB)
database/        schema.sql + migrations/ (MySQL/MariaDB, utf8mb4)
public/          index.php (front controller), assets, uploads
storage/         sessões e logs (fora da web)
```
