<div class="card">
    <div class="card-header">
        <div>
            <h2>4. Formulário com Validação &amp; Enum</h2>
            <p class="subtitle"><code>#[Locked]</code>, <code>BackedEnum</code>, múltiplas regras <code>#[Validate]</code> e <code>wire:submit</code></p>
        </div>
        <span wire:loading class="badge-loading">Validando...</span>
    </div>

    <form wire:submit="save" class="stack-form">
        <div class="meta-row">
            <span>ID da Conta (<code>#[Locked]</code>):</span>
            <strong>#{{ $accountId }}</strong>
        </div>

        <label class="field">
            <span>Nome</span>
            <input type="text" wire:model="name" value="{{ $name }}" class="input">
            @if (!empty($errors['name']))
                <span class="error-text">{{ $errors['name'] }}</span>
            @endif
        </label>

        <label class="field">
            <span>E-mail</span>
            <input type="email" wire:model="email" value="{{ $email }}" class="input">
            @if (!empty($errors['email']))
                <span class="error-text">{{ $errors['email'] }}</span>
            @endif
        </label>

        <div class="two-cols">
            <label class="field">
                <span>Perfil (<code>in:dev,admin,viewer</code>)</span>
                <select wire:model="role" class="input">
                    <option value="dev" {{ $role === 'dev' ? 'selected' : '' }}>dev</option>
                    <option value="admin" {{ $role === 'admin' ? 'selected' : '' }}>admin</option>
                    <option value="viewer" {{ $role === 'viewer' ? 'selected' : '' }}>viewer</option>
                </select>
                @if (!empty($errors['role']))
                    <span class="error-text">{{ $errors['role'] }}</span>
                @endif
            </label>

            <label class="field">
                <span>Plano (<code>BackedEnum</code>)</span>
                <select wire:model="plan" class="input">
                    <option value="free" {{ $plan === 'free' ? 'selected' : '' }}>Free</option>
                    <option value="pro" {{ $plan === 'pro' ? 'selected' : '' }}>Pro</option>
                    <option value="enterprise" {{ $plan === 'enterprise' ? 'selected' : '' }}>Enterprise</option>
                </select>
            </label>
        </div>

        <button type="submit" class="btn btn-primary">Salvar Perfil</button>

        @if ($saved)
            <div class="success-banner">
                Perfil salvo! Plano ativo: <strong>{{ strtoupper($plan) }}</strong> ({{ $role }}).
            </div>
        @endif
    </form>
</div>
