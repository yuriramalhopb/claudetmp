#!/usr/bin/env bash
# Gera o APK/AAB no SEU computador (precisa de Node 18+ e Java 17).
# Uso: ./android/build-local.sh ducksoffroad.com.br 1.0.0 1
set -euo pipefail
DOMAIN="${1:?Informe o domínio, ex.: ducksoffroad.com.br}"
VERSION_NAME="${2:-1.0.0}"
VERSION_CODE="${3:-1}"
PACKAGE="${ANDROID_PACKAGE:-br.com.ducksoffroad.app}"
ALIAS="${ANDROID_KEY_ALIAS:-ducks}"

cd "$(dirname "$0")"
mkdir -p build && cd build
sed -e "s/__DOMAIN__/${DOMAIN}/g" -e "s/__PACKAGE__/${PACKAGE}/g" -e "s/__KEY_ALIAS__/${ALIAS}/g" \
    -e "s/__VERSION_NAME__/${VERSION_NAME}/g" -e "s/__VERSION_CODE__/${VERSION_CODE}/g" \
    ../twa-manifest.template.json > twa-manifest.json

if [ ! -f android.keystore ]; then
  echo ">> Criando a chave de assinatura (GUARDE o arquivo android/build/android.keystore e a senha para sempre!)"
  keytool -genkeypair -v -keystore android.keystore -alias "$ALIAS" -keyalg RSA -keysize 2048 -validity 10000 \
    -dname "CN=Ducks Offroad, O=Ducks Offroad, C=BR"
fi

npx --yes @bubblewrap/cli@1 update --skipVersionUpgrade
npx --yes @bubblewrap/cli@1 build --skipPwaValidation

echo
echo ">> Impressão digital SHA-256 (coloque em ANDROID_SHA256_FINGERPRINTS no .env do site):"
keytool -list -v -keystore android.keystore -alias "$ALIAS" | grep -E "SHA256:" | awk '{print $2}'
echo ">> Arquivos gerados: android/build/app-release-signed.apk e android/build/app-release-bundle.aab"
