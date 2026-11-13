<?php

declare(strict_types=1);

namespace App\Components;

use PhpWire\Attributes\Validate;
use PhpWire\Component;

/**
 * ARQUIVO DE REATIVIDADE:
 * Contém o estado (`$count`, `$step`), as ações (`increment`, `decrement`, `resetCounter`)
 * e o método `render()` indicando qual arquivo de visualização usar.
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
