<div class="counter-card">
    <p class="label">Contador Reativo em PHP <span wire:loading class="loading-badge">Atualizando...</span></p>
    <div class="number">{{ $count }}</div>

    <div class="step-control">
        <label for="step-input">Passo (1 a 100):</label>
        <input id="step-input" type="number" wire:model.live="step" value="{{ $step }}" class="step-input" min="1" max="100">
        @if (!empty($errors['step']))
            <p class="error-text">{{ $errors['step'] }}</p>
        @endif
    </div>

    <div class="buttons">
        <button wire:click="decrement" class="btn btn-minus">- {{ $step }}</button>
        <button wire:click="increment" class="btn btn-plus">+ {{ $step }}</button>
        <button wire:click="increment(5)" class="btn btn-plus">+5</button>
        @if ($count !== 0)
            <button wire:click="resetCounter" class="btn btn-minus">Zerar</button>
        @endif
    </div>
</div>
