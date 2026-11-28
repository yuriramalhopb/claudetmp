<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class HttpException extends RuntimeException
{
    public function __construct(private readonly int $status, string $message = '')
    {
        parent::__construct($message !== '' ? $message : self::defaultMessage($status), $status);
    }

    public function status(): int
    {
        return $this->status;
    }

    private static function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'Requisição inválida.',
            403 => 'Você não tem permissão para acessar esta página.',
            404 => 'Página não encontrada.',
            405 => 'Método não permitido.',
            413 => 'Arquivo muito grande.',
            419 => 'Sua sessão expirou. Recarregue a página e tente novamente.',
            429 => 'Muitas tentativas. Aguarde alguns minutos.',
            default => 'Erro interno. Tente novamente mais tarde.',
        };
    }
}
