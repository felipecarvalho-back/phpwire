<?php

declare(strict_types=1);

namespace PhpWire\Attributes;

use Attribute;

/**
 * Impede que uma propriedade pública do componente seja modificada pelo cliente (wire:model / updates).
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Locked
{
}
