<?php

declare(strict_types=1);

namespace App\Components;

use PhpWire\Attributes\Validate;
use PhpWire\Component;

/**
 * Exemplo 1: Contador Reativo.
 * Demonstra wire:click, parâmetros em ações, wire:model.live e validação com #[Validate].
 */
class Counter extends Component
{
    public int $count = 0;

    #[Validate('required|integer|min:1|max:100')]
    public int $step = 1;

    public function increment(?int $customStep = null): void
    {
        if ($customStep === null) {
            $this->validate();
            $this->count += $this->step;
            return;
        }

        $this->count += $customStep;
    }

    public function decrement(?int $customStep = null): void
    {
        if ($customStep === null) {
            $this->validate();
            $this->count -= $this->step;
            return;
        }

        $this->count -= $customStep;
    }

    public function resetCounter(): void
    {
        $this->count = 0;
        $this->step = 1;
        $this->resetValidation();
    }

    public function render(): string
    {
        return $this->view('counter');
    }
}
