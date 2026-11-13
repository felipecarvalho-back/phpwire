<?php

declare(strict_types=1);

namespace App\Components;

use PhpWire\Attributes\Locked;
use PhpWire\Attributes\Validate;
use PhpWire\Component;

/**
 * Exemplo 4: Formulário de Perfil.
 * Demonstra #[Locked], hidratação de BackedEnum (PlanEnum),
 * múltiplas regras #[Validate] e feedback de erro/sucesso em wire:submit.
 */
class ProfileForm extends Component
{
    #[Locked]
    public int $accountId = 1042;

    #[Validate('required|min:3|max:50')]
    public string $name = 'Felipe Carvalho';

    #[Validate('required|email')]
    public string $email = 'felipe@exemplo.com';

    #[Validate('required|in:dev,admin,viewer')]
    public string $role = 'dev';

    public PlanEnum $plan = PlanEnum::Pro;

    public bool $saved = false;

    public function updating(): void
    {
        $this->saved = false;
    }

    public function save(): void
    {
        $this->saved = false;
        $this->validate();
        $this->saved = true;
    }

    public function render(): string
    {
        return $this->view('profile-form');
    }
}
