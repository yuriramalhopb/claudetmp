<?php
declare(strict_types=1);

namespace App\Core;

/** Interrompe o fluxo e redireciona (usado pelos middlewares). */
final class RedirectException extends \RuntimeException
{
    public function __construct(public readonly string $to)
    {
        parent::__construct('redirect');
    }
}
