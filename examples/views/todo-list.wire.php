<div class="card">
    <div class="card-header">
        <div>
            <h2>2. Lista de Tarefas</h2>
            <p class="subtitle"><code>wire:submit</code>, <code>wire:model</code>, argumentos <code>toggle(i)</code> e <code>@@forelse</code></p>
        </div>
        <span wire:loading class="badge-loading">Salvando...</span>
    </div>

    <form wire:submit="addTodo" class="inline-form">
        <input type="text" wire:model="newTodo" value="{{ $newTodo }}" placeholder="Nova tarefa (mín. 3 caracteres)" class="input">
        <button type="submit" class="btn btn-primary">Adicionar</button>
    </form>
    @if (!empty($errors['newTodo']))
        <p class="error-text">{{ $errors['newTodo'] }}</p>
    @endif

    <div class="filter-tabs">
        <button wire:click="setFilter('all')" class="tab {{ $filter === 'all' ? 'active' : '' }}">Todas ({{ count($todos) }})</button>
        <button wire:click="setFilter('pending')" class="tab {{ $filter === 'pending' ? 'active' : '' }}">Pendentes ({{ $pendingCount }})</button>
        <button wire:click="setFilter('done')" class="tab {{ $filter === 'done' ? 'active' : '' }}">Concluídas ({{ $doneCount }})</button>
    </div>

    <ul class="todo-list">
        @forelse ($visibleTodos as $idx => $todo)
            <li class="todo-item {{ $todo['done'] ? 'is-done' : '' }}">
                <button wire:click="toggle({{ $idx }})" class="todo-toggle" title="Alternar status">
                    {{ $todo['done'] ? '✓' : '○' }}
                </button>
                <span class="todo-title">{{ $todo['title'] }}</span>
                <button wire:click="remove({{ $idx }})" class="btn-icon" title="Remover">✕</button>
            </li>
        @empty
            <li class="empty-state">Nenhuma tarefa neste filtro.</li>
        @endforelse
    </ul>

    @if ($doneCount > 0)
        <div class="card-footer">
            <button wire:click="clearCompleted" class="btn btn-ghost btn-sm">Limpar concluídas ({{ $doneCount }})</button>
        </div>
    @endif
</div>
