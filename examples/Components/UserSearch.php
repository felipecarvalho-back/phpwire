<?php

declare(strict_types=1);

namespace App\Components;

use PhpWire\Component;

/**
 * Exemplo 3: Busca em Tempo Real.
 * Demonstra wire:model.live (preservando foco com Idiomorph), <select wire:model>,
 * lifecycle hooks (updatingSearch / updatedSearch) e wire:loading.
 */
class UserSearch extends Component
{
    public string $search = '';

    public string $role = 'all';

    public string $lastHookEvent = '';

    /** @var array<int, array{name: string, email: string, role: string}> */
    private const DIRECTORY = [
        ['name' => 'Ana Silva', 'email' => 'ana@empresa.com', 'role' => 'dev'],
        ['name' => 'Bruno Costa', 'email' => 'bruno@empresa.com', 'role' => 'admin'],
        ['name' => 'Carla Mendes', 'email' => 'carla@empresa.com', 'role' => 'dev'],
        ['name' => 'Diego Rocha', 'email' => 'diego@empresa.com', 'role' => 'viewer'],
        ['name' => 'Elena Souza', 'email' => 'elena@empresa.com', 'role' => 'admin'],
        ['name' => 'Felipe Carvalho', 'email' => 'felipe@empresa.com', 'role' => 'dev'],
    ];

    public function updatingSearch(string $value): void
    {
        $this->lastHookEvent = 'updatingSearch -> "' . trim($value) . '"';
    }

    public function updatedSearch(string $value): void
    {
        $this->search = trim($value);
        $this->lastHookEvent = 'Busca aplicada: "' . ($this->search !== '' ? $this->search : 'todos') . '"';
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->role = 'all';
        $this->lastHookEvent = 'Filtros limpos';
    }

    public function render(): string
    {
        $term = mb_strtolower($this->search);

        $results = array_values(array_filter(
            self::DIRECTORY,
            function (array $user) use ($term): bool {
                if ($this->role !== 'all' && $user['role'] !== $this->role) {
                    return false;
                }

                if ($term === '') {
                    return true;
                }

                return str_contains(mb_strtolower($user['name']), $term)
                    || str_contains(mb_strtolower($user['email']), $term);
            }
        ));

        return $this->view('user-search', [
            'results' => $results,
        ]);
    }
}
