# Pix de teste (Mercado Pago)

Mini-sistema para gerar cobranças Pix na sua conta do Mercado Pago e ver o pagamento ser confirmado.
Stack: PHP 8 (com `curl` e `pdo_mysql`) + MySQL/MariaDB.

---

## Parte 1 — Pegar a chave (Access Token) no Mercado Pago

> A conta do Mercado Livre e a do Mercado Pago são a mesma: entre com o seu login normal.
> Os nomes dos menus podem mudar um pouco; se algo estiver diferente, procure o equivalente.

### 1.1 Cadastrar uma chave Pix na conta (obrigatório)
Sem chave Pix cadastrada, o Mercado Pago não gera cobrança Pix.
1. No app ou site do Mercado Pago: **Seu perfil → Pix → Suas chaves** (ou "Área Pix").
2. Cadastre uma chave (CPF, e-mail, celular ou chave aleatória).

### 1.2 Criar uma aplicação
1. Acesse **https://www.mercadopago.com.br/developers/panel** e entre com sua conta.
2. Vá em **Suas integrações** e clique em **Criar aplicação**.
3. Preencha:
   - **Nome da aplicação:** por exemplo `Pix de teste`.
   - **Solução de pagamento:** *Pagamentos online* (a que permite integrar por API).
   - **Tipo de integração / produto:** a opção de **API de pagamentos / Checkout Transparente**.
   - Se perguntar se usa plataforma de e-commerce (Shopify, Woo etc.): **não** — é integração própria.
4. Aceite os termos e clique em **Criar aplicação**.

### 1.3 Ativar e copiar as credenciais de produção
1. Dentro da aplicação criada, abra **Credenciais** no menu lateral.
2. Selecione o ambiente **Produção** e clique em **Ativar credenciais de produção**.
3. Informe o **setor de atuação** e o **site do negócio** (pode ser qualquer site ou o do seu projeto), aceite os termos, resolva o reCAPTCHA e confirme.
4. Vão aparecer **Public Key** e **Access Token**. Copie o **Access Token** (começa com `APP_USR-`).
   Ele é a senha da sua conta para cobrar: **nunca** publique, mande por chat ou commite no git.

### 1.4 (Opcional) Testar sem dinheiro de verdade
As credenciais de **teste** funcionam logo após criar a aplicação, mas Pix com dinheiro real só sai com o token de **produção**.
Para um teste real, gere uma cobrança de **R$ 0,01** com o token de produção e pague de outro banco.

---

## Parte 2 — Rodar o sistema

### 2.1 Banco de dados
```sql
CREATE DATABASE pix_teste CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pix'@'localhost' IDENTIFIED BY 'troque-esta-senha';
GRANT ALL ON pix_teste.* TO 'pix'@'localhost';
```
A tabela `payments` é criada sozinha no primeiro acesso (o SQL também está em `schema.sql`, se preferir importar à mão).

### 2.2 Configuração
```bash
cd pix-teste
cp .env.example .env
```
Edite o `.env`:
- `DB_NAME`, `DB_USER`, `DB_PASS` — os dados do banco acima.
- `MP_ACCESS_TOKEN` — o Access Token copiado no passo 1.3.
- `PAYER_EMAIL` — seu e-mail (o Mercado Pago exige um e-mail de pagador).

### 2.3 Subir
```bash
php -S 0.0.0.0:8080
```
Abra http://localhost:8080, informe o valor e clique em **Gerar Pix**. Pague pelo app de **outro banco/conta**
(pagar da própria conta Mercado Pago para ela mesma não funciona). A página consulta o status a cada 4 s e muda para **Pago** sozinha.

---

## Webhook (opcional)
Com o sistema numa URL `https://` pública, preencha `APP_URL` no `.env`. O Mercado Pago avisa em `webhook.php`
e o status atualiza mesmo com a página fechada. Para validar a assinatura, em **Suas integrações → Webhooks**
copie a "assinatura secreta" para `MP_WEBHOOK_SECRET`.

Se publicar online, defina também `APP_PASSWORD` (usuário `admin`) para ninguém além de você gerar cobranças.

## Arquivos
| Arquivo | Função |
|---|---|
| `index.php` | formulário e últimas cobranças |
| `criar.php` | cria o pagamento via `POST /v1/payments` |
| `pagamento.php` | QR Code, copia e cola e status ao vivo |
| `status.php` | consulta o status (usado pela página) |
| `webhook.php` | recebe notificações do Mercado Pago |
| `lib.php` | cliente da API, conexão MySQL e utilidades |
| `schema.sql` | estrutura da tabela `payments` |
