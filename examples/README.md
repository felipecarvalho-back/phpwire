# Exemplos do PhpWire

Esta pasta contém 4 componentes interativos demonstrando todos os recursos da biblioteca em uma única página:

| Exemplo | Arquivo do Componente | View BladeOne | Recursos Demonstrados |
| --- | --- | --- | --- |
| **Contador Reativo** | `Components/Counter.php` | `views/counter.wire.php` | `wire:click`, argumentos `increment(5)`, `wire:model.live`, `#[Validate]`, `@if` |
| **Lista de Tarefas** | `Components/TodoList.php` | `views/todo-list.wire.php` | `wire:submit`, `wire:model`, `toggle(i)` / `remove(i)`, `@forelse`, dados extras na view |
| **Busca em Tempo Real** | `Components/UserSearch.php` | `views/user-search.wire.php` | `wire:model.live` (Idiomorph preservando foco), `<select wire:model>`, hooks `updatingSearch` / `updatedSearch`, `wire:loading` |
| **Formulário de Perfil** | `Components/ProfileForm.php` | `views/profile-form.wire.php` | `#[Locked]`, hidratação de `BackedEnum` (`PlanEnum`), múltiplas regras `#[Validate]`, `$errors` |

## Como executar localmente

Na raiz do projeto, execute:

```bash
composer serve
```

Ou diretamente com o servidor embutido do PHP:

```bash
php -S localhost:8080 -t examples
```

Depois acesse `http://localhost:8080`.

## Integração com Frameworks

Veja [codeigniter4-integration.php](codeigniter4-integration.php) para um modelo pronto de integração com CodeIgniter 4.
