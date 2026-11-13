<?php

declare(strict_types=1);

namespace PhpWire\Exceptions;

use RuntimeException;

/**
 * Lançada quando a validação de propriedades do componente falha.
 */
final class ValidationException extends RuntimeException
{
    /**
     * @param array<string, string> $errors
     */
    public function __construct(
        public readonly array $errors,
        string $message = 'Os dados fornecidos são inválidos.'
    ) {
        parent::__construct($message);
    }
}
