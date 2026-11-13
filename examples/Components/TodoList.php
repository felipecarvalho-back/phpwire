<?php

declare(strict_types=1);

namespace App\Components;

use PhpWire\Attributes\Validate;
use PhpWire\Component;

/**
 * Exemplo 2: Lista de Tarefas (TodoList).
 * Demonstra wire:submit, wire:model, argumentos em wire:click, filtros e @forelse no BladeOne.
 */
class TodoList extends Component
{
    #[Validate('required|min:3|max:60', message: 'Digite uma tarefa entre 3 e 60 caracteres.')]
    public string $newTodo = '';

    public string $filter = 'all';

    /**
     * @var array<int, array{title: string, done: bool}>
     */
    public array $todos = [
        ['title' => 'Instalar PhpWire via Composer', 'done' => true],
        ['title' => 'Testar DOM Morphing com Idiomorph', 'done' => false],
        ['title' => 'Publicar biblioteca no Packagist', 'done' => false],
    ];

    public function addTodo(): void
    {
        $this->newTodo = trim($this->newTodo);
        $this->validate();

        $this->todos[] = [
            'title' => $this->newTodo,
            'done' => false,
        ];

        $this->newTodo = '';
    }

    public function toggle(int $index): void
    {
        if (!isset($this->todos[$index])) {
            return;
        }

        $this->todos[$index]['done'] = !$this->todos[$index]['done'];
    }

    public function remove(int $index): void
    {
        if (!isset($this->todos[$index])) {
            return;
        }

        unset($this->todos[$index]);
        $this->todos = array_values($this->todos);
    }

    public function setFilter(string $filter): void
    {
        if (in_array($filter, ['all', 'pending', 'done'], true)) {
            $this->filter = $filter;
        }
    }

    public function clearCompleted(): void
    {
        $this->todos = array_values(array_filter(
            $this->todos,
            static fn(array $todo): bool => !$todo['done']
        ));
    }

    public function render(): string
    {
        $visibleTodos = [];
        foreach ($this->todos as $index => $todo) {
            if ($this->filter === 'pending' && $todo['done']) {
                continue;
            }
            if ($this->filter === 'done' && !$todo['done']) {
                continue;
            }
            $visibleTodos[$index] = $todo;
        }

        $pendingCount = count(array_filter($this->todos, static fn(array $t): bool => !$t['done']));
        $doneCount = count($this->todos) - $pendingCount;

        return $this->view('todo-list', [
            'visibleTodos' => $visibleTodos,
            'pendingCount' => $pendingCount,
            'doneCount' => $doneCount,
        ]);
    }
}
