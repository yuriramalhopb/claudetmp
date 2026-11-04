# 🦆 Ducks Offroad — site e sistema da turma de trilha

Site completo em **PHP 8.1+ orientado a objetos**, sem frameworks nem dependências externas usando **MySQL 5.7+/8 ou MariaDB 10.3+** (roda em qualquer hospedagem com PHP + MySQL).

## Funcionalidades

| Área | O que tem |
|---|---|
| **Início** | Hero, próximas trilhas, últimas fotos, feed do Instagram, novos trilheiros |
| **Agenda** | Trilhas da Ducks e de outras turmas, filtros (tipo, nível, busca), eventos passados, confirmação de presença ("Eu vou!"), lista de quem vai, exportar `.ics`, Google Agenda, mapa, compartilhar (WhatsApp / Web Share) |
| **Galeria** | Álbuns, lightbox com teclado e swipe, paginação, trilheiros enviam fotos (com aprovação do admin) |
| **Instagram** | Sincronização automática via API oficial (imagens baixadas e salvas localmente, token renovado sozinho) **ou** posts adicionados pelo link (incorporação oficial) — inclusive posts de outras turmas |
| **Trilheiros** | Cadastro completo (moto, cilindrada, nível, tipo sanguíneo, contato de emergência, foto), aprovação pelo admin, diretório público, perfil |
| **Minha conta** | Editar perfil e foto, trocar senha, minhas trilhas, baixar meus dados, excluir conta (LGPD) |
| **Painel admin** | Dashboard, aprovar/bloquear/promover trilheiros, ficha completa, exportar CSV, CRUD de eventos (com capa) e aprovação de sugestões, álbuns e fotos, Instagram, mensagens de contato, log de auditoria |

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
- As tabelas ficam em `database/schema.sql` (também são criadas automaticamente no primeiro acesso, se não existirem).
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

## Estrutura

```
app/
  Core/          App, Router, Request, Response, View, Database, Session, Csrf, Auth, RateLimiter, Validator, SecurityHeaders...
  Models/        User, Event, Album, Photo, InstagramPost, Message, Setting, PasswordReset, AuditLog
  Services/      InstagramService, ImageUploader, Calendar, Mailer
  Controllers/   públicos + Admin/
  Views/         templates PHP (layouts, partials, páginas, admin)
  routes.php     todas as rotas e middlewares (guest, auth, approved, admin)
bin/             install, instagram-sync, seed-demo
database/        schema.sql (MySQL/MariaDB, utf8mb4)
public/          index.php (front controller), assets, uploads
storage/         sessões e logs (fora da web)
```
