#!/usr/bin/env python3
"""Liga/desliga redes Wi-Fi de roteadores TP-Link (Archer C6U, AX, etc.) pela linha de comando.

Uso:
  ./wifi.py status
  ./wifi.py off --redes 5g,guest2g
  ./wifi.py on  --redes todas

Senha (nunca vai na linha de comando, para não aparecer em `ps`/histórico):
  1) variável de ambiente TPLINK_PASSWORD
  2) arquivo indicado por --senha-arquivo (ou TPLINK_PASSWORD_FILE), chmod 600
  3) pergunta interativa (se rodar num terminal)
"""
import argparse
import getpass
import os
import stat
import sys

from tplinkrouterc6u import Connection, TplinkRouterProvider

REDES = {
    "2g": Connection.HOST_2G,
    "5g": Connection.HOST_5G,
    "5g2": Connection.HOST_5G_2,
    "6g": Connection.HOST_6G,
    "guest2g": Connection.GUEST_2G,
    "guest5g": Connection.GUEST_5G,
    "guest6g": Connection.GUEST_6G,
    "iot2g": Connection.IOT_2G,
    "iot5g": Connection.IOT_5G,
}


def ler_senha(args):
    if os.environ.get("TPLINK_PASSWORD"):
        return os.environ["TPLINK_PASSWORD"]
    caminho = args.senha_arquivo or os.environ.get("TPLINK_PASSWORD_FILE")
    if caminho:
        modo = os.stat(caminho).st_mode
        if modo & (stat.S_IRGRP | stat.S_IROTH):
            sys.exit(f"Erro: {caminho} está legível por outros usuários. Rode: chmod 600 {caminho}")
        with open(caminho, encoding="utf-8") as f:
            return f.read().rstrip("\r\n")
    if sys.stdin.isatty():
        return getpass.getpass("Senha do roteador: ")
    sys.exit("Erro: informe a senha via TPLINK_PASSWORD, --senha-arquivo ou execute em um terminal.")


def escolher_redes(texto):
    if texto.strip().lower() == "todas":
        return list(REDES.items())
    escolhidas = []
    for nome in (n.strip().lower() for n in texto.split(",") if n.strip()):
        if nome not in REDES:
            sys.exit(f"Rede desconhecida: '{nome}'. Opções: {', '.join(REDES)} ou 'todas'.")
        escolhidas.append((nome, REDES[nome]))
    return escolhidas


def main():
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument("acao", choices=["on", "off", "status"], help="ligar, desligar ou ver o estado das redes")
    p.add_argument("--redes", default="todas", help="lista separada por vírgula (2g,5g,guest2g,...) ou 'todas'")
    p.add_argument("--host", default=os.environ.get("TPLINK_HOST", "http://192.168.0.1"),
                   help="endereço do roteador (padrão: http://192.168.0.1)")
    p.add_argument("--usuario", default=os.environ.get("TPLINK_USER", "admin"))
    p.add_argument("--senha-arquivo", help="arquivo com a senha (permissão 600)")
    p.add_argument("--sem-verificar-ssl", action="store_true", help="para roteadores com certificado https próprio")
    args = p.parse_args()

    senha = ler_senha(args)
    router = TplinkRouterProvider.get_client(
        args.host, senha, args.usuario, verify_ssl=not args.sem_verificar_ssl
    )
    try:
        router.authorize()
        status = router.get_status()
        atual = {
            "2g": status.wifi_2g_enable, "5g": status.wifi_5g_enable, "5g2": status.wifi_5g_2_enable, "6g": status.wifi_6g_enable,
            "guest2g": status.guest_2g_enable, "guest5g": status.guest_5g_enable,
            "guest6g": status.guest_6g_enable, "iot2g": status.iot_2g_enable, "iot5g": status.iot_5g_enable,
        }
        if args.acao == "status":
            for nome in REDES:
                valor = atual.get(nome)
                if valor is not None:
                    print(f"{nome:8} {'LIGADA' if valor else 'desligada'}")
            return
        ligar = args.acao == "on"
        for nome, conexao in escolher_redes(args.redes):
            if atual.get(nome) is None:
                print(f"{nome}: não existe neste roteador, ignorando")
                continue
            router.set_wifi(conexao, ligar)
            print(f"{nome}: {'ligada' if ligar else 'desligada'}")
    finally:
        try:
            router.logout()
        except Exception:
            pass


if __name__ == "__main__":
    main()
