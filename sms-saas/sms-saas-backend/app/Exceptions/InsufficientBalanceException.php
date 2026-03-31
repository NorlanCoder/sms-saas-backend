<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class InsufficientBalanceException extends Exception
{
    public function __construct(
        string $message = 'Solde insuffisant pour effectuer cette opération',
        int $code = 402,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
