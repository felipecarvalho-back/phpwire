# ⚡ PhpWire

![PHP](https://img.shields.io/badge/PHP-%3E%3D%208.1-777BB4?logo=php&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-10B981)
![PHPStan](https://img.shields.io/badge/PHPStan-Level%208-3B82F6)

Biblioteca PHP leve para criar componentes reativos no servidor (inspirada no **Laravel Livewire**), separando a classe de estado/ações do template de visualização — sem precisar escrever JavaScript. Funciona tanto em **PHP Procedural** (sem configurar rotas) quanto em **Frameworks MVC** (CodeIgniter 4, Slim, Laravel).

## 📑 Índice

- [Principais Recursos](#-principais-recursos)
- [Instalação](#-instalação)
- [Início Rápido (3 Passos)](#-início-rápido-3-passos)
- [Guia de Funcionalidades](#-guia-de-funcionalidades)
  - [Diretivas HTML (`wire:*`)](#diretivas-html-wire)
  - [Validação (`#[Validate]`)](#validação-validate)
  - [Segurança (`#[Locked]` e Checksum HMAC)](#segurança-locked-e-checksum-hmac)
  - [Hooks de Ciclo de Vida e `BackedEnum`](#hooks-de-ciclo-de-vida-e-backedenum)
- [Configuração (`wire_init`)](#-configuração-wire_init)
- [Integração com CodeIgniter 4](#-integração-com-codeigniter-4)
- [Exemplos Interativos e Testes](#-exemplos-interativos-e-testes)

---

## ✨ Principais Recursos

- **Reatividade em PHP Puro:** Propriedades públicas da classe são sincronizadas automaticamente entre o navegador e o servidor via `wire:snapshot`.
- **Templates com BladeOne (`eftec/bladeone`):** Suporte nativo a `@if`, `@forelse`, `@foreach`, `@include`, `{{ $var }}` e diretivas `@wire` / `@wireScripts`, compilados com cache em disco (sem `eval()`).
- **DOM Morphing com Idiomorph:** Atualiza apenas os nós e atributos alterados no DOM, preservando foco, posição do cursor em inputs e evitando piscar em cliques rápidos.
- **Segurança Integrada:** Snapshots assinados com **HMAC-SHA256**, bloqueio de alteração de propriedades `#[Locked]` e proteção contra chamadas a métodos mágicos (`__`) ou internos (`render`, `hydrateState`, etc.).
- **PHP 8.1+ Attributes & Tipagem:** Atributos nativos `#[Validate]` e `#[Locked]`, além de coerção automática de tipos escalares, `array` e `BackedEnum`.

---

## 📦 Instalação

```bash
composer require felipecarvalho-back/phpwire
```

---

## 🚀 Início Rápido (3 Passos)

### 1. Crie a classe do componente (`app/Components/Counter.php`)

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

    public function decrement(): void
    {
        $this->validate();
        $this->count -= $this->step;
    }

    public function render(): string
    {
        return $this->view('counter');
    }
}
```

### 2. Crie o template da view (`views/counter.wire.php`)

> O PhpWire procura automaticamente por arquivos `.wire.php`, `.blade.php` ou `.php` na pasta configurada em `views`.

```html
<div class="counter-card">
    <p>Contador Reativo <span wire:loading>Atualizando...</span></p>
    <h1>{{ $count }}</h1>

    <input type="number" wire:model.live="step" value="{{ $step }}">
    @if (!empty($errors['step']))
        <p class="error">{{ $errors['step'] }}</p>
    @endif

    <button wire:click="decrement">- {{ $step }}</button>
    <button wire:click="increment">+ {{ $step }}</button>
    <button wire:click="increment(5)">+5</button>
</div>
```

### 3. Inicialize e renderize na página (`public/index.php`)

Em PHP procedural você **não precisa criar rotas extras**: o `wire_init()` intercepta automaticamente requisições POST com o header `X-PhpWire`.

```php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

wire_init([
    'views'  => __DIR__ . '/../views',
    'secret' => $_ENV['PHPWIRE_SECRET'] ?? 'troque-por-uma-chave-secreta',
]);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<body>
    <?= wire('counter') ?>

    <?= wire_scripts() ?>
</body>
</html>
```

---

## 🧩 Guia de Funcionalidades

### Diretivas HTML (`wire:*`)

| Diretiva | Onde usar | Comportamento |
| --- | --- | --- |
| `wire:click="acao"` | Botões / links | Executa o método `acao()` no componente PHP. |
| `wire:click="acao(5, 'ok')"` | Botões / links | Executa `acao(5, 'ok')` convertendo tipos pelos parâmetros do método. |
| `wire:submit="salvar"` | `<form>` | Intercepta o `submit` (`preventDefault`) e executa `salvar()`. |
| `wire:model="campo"` | `<input>`, `<select>`, `<textarea>` | Sincroniza no evento `change` e antes de qualquer `wire:click` / `wire:submit`. |
| `wire:model.live="campo"` | `<input>`, `<textarea>` | Atualiza em tempo real no evento `input` com debounce de `250ms` preservando o foco. |
| `wire:loading` | Qualquer elemento | Fica oculto por padrão e aparece se a requisição demorar mais de `200ms` (evita piscar). |
| `wire:loading.delay.none` | Qualquer elemento | Exibe o indicador de carregamento imediatamente (`0ms`) ao disparar a requisição. |

---

### Validação (`#[Validate]`)

Adicione o atributo `#[Validate]` nas propriedades públicas e chame `$this->validate()` dentro da sua ação. Se houver erro, o PhpWire captura a `ValidationException` automaticamente e re-renderiza a view com a variável `$errors` preenchida.

```php
use PhpWire\Attributes\Validate;

#[Validate('required|min:3|max:60', message: 'Digite entre 3 e 60 caracteres.')]
public string $title = '';
```

Também é possível passar regras dinâmicas via `$this->validate(['email' => 'required|email'])`, consultar `$this->hasError('title')`, `$this->getError('title')`, adicionar erros manuais com `$this->addError('title', '...')` ou limpar com `$this->resetValidation()`.

#### Regras suportadas

| Regra | Descrição |
| --- | --- |
| `required` | Não pode ser `null`, string vazia `""` ou array vazio `[]`. |
| `integer` / `int` | Deve ser um número inteiro válido. |
| `numeric` | Deve ser um valor numérico (`is_numeric`). |
| `email` | Deve ser um endereço de e-mail válido. |
| `min:N` | Valor numérico mínimo `N` ou tamanho mínimo de `N` caracteres/itens. |
| `max:N` | Valor numérico máximo `N` ou tamanho máximo de `N` caracteres/itens. |
| `in:a,b,c` | O valor deve estar contido na lista informada. |

---

### Segurança (`#[Locked]` e Checksum HMAC)

1. **Snapshot Assinado (`HMAC-SHA256`):** Todo estado serializado em `wire:snapshot` recebe um `checksum`. Qualquer tentativa de adulterar o JSON no navegador falha na verificação (`hash_equals`) e retorna erro `403`.
2. **Propriedades Protegidas (`#[Locked]`):** Impede que campos sensíveis sejam modificados via `wire:model` / `updates` vindos do cliente:

```php
use PhpWire\Attributes\Locked;

#[Locked]
public int $accountId = 1042;
```

3. **Bloqueio de Métodos Internos:** Ações não podem invocar métodos mágicos (`__construct`, `__call`, etc.), `render()` nem métodos da classe base `PhpWire\Component`.

---

### Hooks de Ciclo de Vida e `BackedEnum`

- **Hooks automáticos ao atualizar propriedades via `wire:model`:**
  - `updating(string $property, mixed $value)` e `updated(string $property, mixed $value)`
  - `updating{Propriedade}(mixed $value)` e `updated{Propriedade}(mixed $value)` (ex: `updatedSearch(string $value)`)
- **Enums (`BackedEnum`):** Propriedades tipadas com um `BackedEnum` são serializadas no snapshot e reidratadas automaticamente via `::tryFrom()`.

---

## ⚙️ Configuração (`wire_init`)

| Chave | Tipo | Padrão | Descrição |
| --- | --- | --- | --- |
| `views` | `string` | `''` | Diretório base onde ficam os arquivos `.wire.php` / `.blade.php` / `.php`. |
| `components_path` | `string` | `''` | Pasta física dos componentes (útil em PHP procedural sem autoload PSR-4). |
| `namespace` | `string` | `'App\\Components\\'` | Namespace padrão usado ao resolver apelidos como `wire('counter')`. |
| `cache_path` | `string` | `sys_get_temp_dir()/phpwire_views` | Pasta de cache para templates compilados pelo BladeOne. |
| `endpoint` | `string\|null` | `null` | URL de destino do `fetch()`. Se `null`, usa `window.location.href`. |
| `secret` | `string` | `PHPWIRE_SECRET` / `APP_KEY` / fallback | Chave secreta usada na assinatura HMAC-SHA256 dos snapshots. |
| `components` | `array` | `[]` | Mapa manual `['apelido' => MinhaClasse::class]`. |
| `auto_listen` | `bool` | `true` | Quando `true`, responde automaticamente requisições `POST` com header `X-PhpWire`. |

---

## 🧱 Integração com CodeIgniter 4

1. **Em `app/Controllers/BaseController.php`** (dentro de `initController`):

```php
wire_init([
    'views'           => APPPATH . 'Views/components',
    'components_path' => APPPATH . 'Components',
    'namespace'       => 'App\\Components\\',
    'endpoint'        => '/phpwire/update',
    'secret'          => env('encryption.key', 'troque-por-uma-chave-secreta'),
    'auto_listen'     => false,
]);
```

2. **Em `app/Config/Routes.php`**:

```php
$routes->post('phpwire/update', static function () {
    $payload = service('request')->getJSON(true) ?? [];
    return service('response')->setJSON(\PhpWire\PhpWire::handlePayload($payload));
});
```

3. **Em qualquer View (`app/Views/welcome_message.php`)**:

```php
<?= wire('counter') ?>

<?= wire_scripts() ?>
```

---

## 🧪 Exemplos Interativos e Testes

O repositório inclui a pasta [`examples/`](examples/) com um painel funcional contendo 4 componentes completos:

| Componente | Arquivo | O que demonstra |
| --- | --- | --- |
| **Contador Reativo** | [`Counter.php`](examples/Components/Counter.php) | `wire:click`, `increment(5)`, `wire:model.live`, `#[Validate]`, `@if` |
| **Lista de Tarefas** | [`TodoList.php`](examples/Components/TodoList.php) | `wire:submit`, `wire:model`, `toggle(i)` / `remove(i)`, `@forelse` |
| **Busca em Tempo Real** | [`UserSearch.php`](examples/Components/UserSearch.php) | `wire:model.live` (Idiomorph preservando foco), `<select wire:model>`, hooks |
| **Formulário de Perfil** | [`ProfileForm.php`](examples/Components/ProfileForm.php) | `#[Locked]`, `BackedEnum` (`PlanEnum`), múltiplas regras `#[Validate]` |

### Rodar os exemplos localmente

```bash
composer serve
```

Acesse `http://localhost:8080` no navegador.

### Rodar testes e análise estática

```bash
composer test
composer analyse
```

---

## 📄 Licença

Distribuído sob a licença **MIT**. Veja [LICENSE](LICENSE) para mais detalhes.
