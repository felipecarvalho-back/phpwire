<div class="card">
    <div class="card-header">
        <div>
            <h2>1. Contador Reativo</h2>
            <p class="subtitle"><code>wire:click</code>, parâmetros <code>increment(5)</code>, <code>wire:model.live</code> e <code>#[Validate]</code></p>
        </div>
        <span wire:loading class="badge-loading">Atualizando...</span>
    </div>

    <div class="counter-value">{{ $count }}</div>

    <div class="field-inline">
        <label for="counter-step">Passo (1 a 100):</label>
        <input id="counter-step" type="number" wire:model.live="step" value="{{ $step }}" class="input input-sm" min="1" max="100">
    </div>
    @if (!empty($errors['step']))
        <p class="error-text">{{ $errors['step'] }}</p>
    @endif

    <div class="actions">
        <button wire:click="decrement" class="btn btn-secondary">- {{ $step }}</button>
        <button wire:click="increment" class="btn btn-primary">+ {{ $step }}</button>
        <button wire:click="increment(5)" class="btn btn-accent">+5</button>
        @if ($count !== 0)
            <button wire:click="resetCounter" class="btn btn-ghost">Zerar</button>
        @endif
    </div>
</div>
