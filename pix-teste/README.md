# Pix de teste (Mercado Pago)

Mini-sistema para gerar cobranças Pix na sua conta do Mercado Pago e ver o pagamento ser confirmado.

## Rodar

```bash
cd pix-teste
cp .env.example .env      # preencha MP_ACCESS_TOKEN e PAYER_EMAIL
php -S 0.0.0.0:8080
```

Abra http://localhost:8080, informe o valor, **Gerar Pix**, pague pelo app do seu banco
(use outra conta/banco: pagar da própria conta Mercado Pago pra ela mesma não funciona).
A página consulta o status a cada 4 s e muda para **Pago** sozinha.

## Onde pegar o token
Mercado Pago Developers → Suas integrações → (criar aplicação, "Pagamentos online") → Credenciais de produção → **Access Token**.
A conta precisa ter uma chave Pix cadastrada. **Nunca** commite o `.env`.

## Webhook (opcional)
Com o sistema em uma URL `https://` pública, preencha `APP_URL`. A notificação cai em `webhook.php`
e atualiza o status mesmo com a página fechada. Para validar a assinatura, preencha `MP_WEBHOOK_SECRET`.

## Arquivos
| Arquivo | Função |
|---|---|
| `index.php` | formulário e últimas cobranças |
| `criar.php` | cria o pagamento via `POST /v1/payments` |
| `pagamento.php` | QR Code, copia e cola e status ao vivo |
| `status.php` | consulta o status (usado pela página) |
| `webhook.php` | recebe notificações do Mercado Pago |
| `lib.php` | cliente da API, banco SQLite (`data/pix.sqlite`) e utilidades |
