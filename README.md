# ⚡ PhpWire

Biblioteca PHP leve para criar componentes reativos (inspirada no **Laravel Livewire**), separando a classe de reatividade do arquivo de visualização. Funciona tanto em **PHP Procedural** quanto em **Frameworks MVC (CodeIgniter 4, Slim, Laravel)**.

---

## ✨ Recursos

- **Motor de Templates BladeOne (`eftec/bladeone`):** Suporte nativo a `@if`, `@foreach`, `@include`, `{{ $var }}` e diretivas `@wire` / `@wireScripts`, com cache em disco (sem `eval()`).
- **DOM Morphing com Idiomorph:** Atualiza apenas o que mudou no DOM, preservando foco de inputs, seleções e transições CSS.
- **Segurança com HMAC-SHA256:** Cada `wire:snapshot` inclui um `checksum` assinado no servidor para impedir adulteração de estado no navegador, além de bloqueio contra chamadas a métodos internos (`render`, `hydrateState`, etc.) e mágicos (`__`).
- **PHP 8.1+ Attributes:**
  - `#[Locked]` para impedir que propriedades sensíveis sejam alteradas via `wire:model`.
  - `#[Validate('required|integer|min:1|max:100')]` para validação declarativa com renderização automática de `$errors`.
- **Diretivas Reativas:**
  - `wire:click="increment"` e com parâmetros `wire:click="increment(5)"`
  - `wire:submit="salvar"` em formulários
  - `wire:model="campo"` e `wire:model.live="campo"` (com debounce de 250ms)
  - `wire:loading` para exibir indicadores de carregamento durante requisições AJAX
- **Hidratação Tipada:** Converte automaticamente escalares (`int`, `float`, `bool`, `string`, `array`) e `BackedEnum`.

---

## 📦 Instalação via Composer

```bash
composer require felipecarvalho-back/phpwire
```

---

## 🧩 1. Criando seu Componente (Increment / Decrement)

### Arquivo de Reatividade (`app/Components/Counter.php`)

```php
<?php

namespace App\Components;

use PhpWire\Attributes\Validate;
use PhpWire\Component;

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

    public function render(): string
    {
        return $this->view('counter');
    }
}
```

### Arquivo de Visualização (`views/counter.wire.php`)

```html
<div class="counter-card">
    <p>Contador Reativo em PHP <span wire:loading>Atualizando...</span></p>
    <h1>{{ $count }}</h1>

    <input type="number" wire:model.live="step" value="{{ $step }}">
    @if (!empty($errors['step']))
        <p>{{ $errors['step'] }}</p>
    @endif

    <button wire:click="decrement">- {{ $step }}</button>
    <button wire:click="increment">+ {{ $step }}</button>
    <button wire:click="increment(5)">+5</button>
</div>
```

---

## 🚀 2. Como Inicializar e Usar

### Opção A: Em PHP Procedural (`public/index.php`)
No PHP procedural você **não precisa configurar nenhuma rota extra**. O próprio `wire_init()` no topo do arquivo já escuta os cliques AJAX automaticamente (via header `X-PhpWire`):

```php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

// 1. Inicializa a biblioteca (aponta a pasta de views e já responde aos cliques AJAX)
wire_init([
    'views'  => __DIR__ . '/../views',
    'secret' => $_ENV['APP_KEY'] ?? 'chave-secreta-da-sua-aplicacao',
]);
?>
<!DOCTYPE html>
<html>
<body>
    <!-- 2. Renderiza o componente -->
    <?= wire('counter') ?>

    <!-- 3. Script que conecta o wire:click / wire:model ao PHP (com Idiomorph) -->
    <?= wire_scripts() ?>
</body>
</html>
```

---

### Opção B: No CodeIgniter 4 (ou outro Framework MVC)

#### 1. No `app/Controllers/BaseController.php` (dentro de `initController`):
```php
wire_init([
    'views'       => APPPATH . 'Views/components',
    'namespace'   => 'App\\Components\\',
    'endpoint'    => '/phpwire/update',
    'secret'      => env('encryption.key', 'chave-secreta'),
    'auto_listen' => false, // No CodeIgniter, usamos uma rota dedicada abaixo
]);
```

#### 2. Em `app/Config/Routes.php` (Rota única para atualizar qualquer componente):
```php
$routes->post('phpwire/update', static function () {
    $payload = service('request')->getJSON(true) ?? [];
    return service('response')->setJSON(\PhpWire\PhpWire::handlePayload($payload));
});
```

#### 3. Em qualquer View do CodeIgniter (`app/Views/welcome_message.php`):
```php
<?= wire('counter') ?>

<?= wire_scripts() ?>
```

---

## 🧪 Testes e Qualidade

```bash
composer test
composer analyse
```
