<?php

declare(strict_types=1);

namespace PhpWire\Attributes;

use Attribute;

/**
 * Define regras de validação declarativas para uma propriedade pública do componente.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final class Validate
{
    public function __construct(
        public readonly string $rules,
        public readonly ?string $message = null
    ) {
    }
}
