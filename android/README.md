# App Android (APK e Play Store)

O app Android é o próprio site empacotado como **TWA (Trusted Web Activity)**, o formato
oficial do Google para publicar PWAs. Vantagens: tudo que mudar no site aparece no app na hora
(sem lançar versão nova), mesmo login, notificações e segurança.

## Antes de começar
1. O site precisa estar **no ar com HTTPS** no domínio definitivo (ex.: `ducksoffroad.com.br`).
2. Escolha o ID do app (não pode mudar depois): padrão `br.com.ducksoffroad.app`.

## Opção A — gerar no GitHub (recomendado)
1. No seu computador, crie a chave de assinatura **uma única vez** (precisa do Java):
   ```bash
   keytool -genkeypair -v -keystore android.keystore -alias ducks -keyalg RSA -keysize 2048 -validity 10000
   ```
   **Guarde o arquivo `android.keystore` e as senhas em lugar seguro. Sem eles não é possível atualizar o app.**
2. Converta a chave para texto:
   ```bash
   base64 -w0 android.keystore
   ```
3. No GitHub: **Settings → Secrets and variables → Actions → New repository secret** e crie:
   `ANDROID_KEYSTORE_BASE64` (o texto do passo 2), `ANDROID_KEYSTORE_PASSWORD`, `ANDROID_KEY_PASSWORD`, `ANDROID_KEY_ALIAS` (ex.: `ducks`).
4. **Actions → "App Android (APK/AAB)" → Run workflow**, informe o domínio e a versão.
5. Ao terminar, baixe em **Artifacts**: `app-release-signed.apk` (instalar direto) e `app-release-bundle.aab` (Play Store).
   O log mostra a **impressão SHA-256** da chave.

## Opção B — gerar no seu computador
Precisa de Node 18+ e Java 17:
```bash
./android/build-local.sh ducksoffroad.com.br 1.0.0 1
```

## Ligar o app ao site (obrigatório)
No `.env` do servidor:
```
ANDROID_PACKAGE=br.com.ducksoffroad.app
ANDROID_SHA256_FINGERPRINTS=AA:BB:...   (a impressão SHA-256 mostrada no build)
```
Confira em `https://SEU-DOMINIO/.well-known/assetlinks.json`. Sem isso, o app abre com uma barra de endereço no topo.

**Play Store:** com o "Play App Signing", o Google assina o app com outra chave. Copie a impressão SHA-256
em *Play Console → Configuração → Integridade do app → Assinatura do app* e adicione no `.env`, separada por vírgula.

## Distribuir
- **APK direto:** hospede o `.apk` (ex.: Google Drive) e configure `ANDROID_APK_URL` no `.env` — o botão aparece em `/instalar`.
  No celular, é preciso permitir "instalar apps de fontes desconhecidas".
- **Play Store:** conta de desenvolvedor Google (taxa única de US$ 25), envie o `.aab`, preencha a ficha da loja
  (ícone 512 px em `public/assets/icons/icon-512.png`, capturas de tela, política de privacidade: `https://SEU-DOMINIO/privacidade`).
  Depois de publicado, configure `ANDROID_PLAY_URL`.

## Atualizar o app
Só é necessário gerar nova versão se mudar ícone/nome/cores. Aumente o `version_code` (1, 2, 3...) a cada envio à Play Store.
