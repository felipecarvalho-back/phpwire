<div class="card">
    <div class="card-header">
        <div>
            <h2>3. Busca em Tempo Real</h2>
            <p class="subtitle"><code>wire:model.live</code> (Idiomorph mantém o foco), <code>&lt;select wire:model&gt;</code> e hooks</p>
        </div>
        <span wire:loading class="badge-loading">Filtrando...</span>
    </div>

    <div class="search-controls">
        <input type="text" wire:model.live="search" value="{{ $search }}" placeholder="Buscar por nome ou e-mail..." class="input">
        <select wire:model="role" class="input select-sm">
            <option value="all" {{ $role === 'all' ? 'selected' : '' }}>Todos</option>
            <option value="dev" {{ $role === 'dev' ? 'selected' : '' }}>Dev</option>
            <option value="admin" {{ $role === 'admin' ? 'selected' : '' }}>Admin</option>
            <option value="viewer" {{ $role === 'viewer' ? 'selected' : '' }}>Viewer</option>
        </select>
    </div>

    @if ($lastHookEvent !== '')
        <p class="hook-badge">Hook: <code>{{ $lastHookEvent }}</code></p>
    @endif

    <ul class="user-list">
        @forelse ($results as $user)
            <li class="user-item">
                <div>
                    <strong>{{ $user['name'] }}</strong>
                    <div class="muted">{{ $user['email'] }}</div>
                </div>
                <span class="pill">{{ $user['role'] }}</span>
            </li>
        @empty
            <li class="empty-state">Nenhum usuário encontrado.</li>
        @endforelse
    </ul>

    @if ($search !== '' || $role !== 'all')
        <div class="card-footer">
            <button wire:click="clearFilters" class="btn btn-ghost btn-sm">Limpar filtros</button>
        </div>
    @endif
</div>
