<?php

declare(strict_types=1);

namespace PhpWire;

use BackedEnum;
use eftec\bladeone\BladeOne;
use PhpWire\Attributes\Locked;
use PhpWire\Attributes\Validate;
use PhpWire\Exceptions\ValidationException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use RuntimeException;

/**
 * Classe base do Componente Reativo.
 * Toda classe que estende `Component` define seu estado nas propriedades públicas
 * e aponta para seu arquivo de visualização no método `render()` usando `$this->view('nome-da-view')`.
 */
abstract class Component
{
    /**
     * Erros de validação indexados pelo nome da propriedade.
     *
     * @var array<string, string>
     */
    protected array $errors = [];

    /**
     * Cada componente define qual view será renderizada.
     * Exemplo:
     *   public function render(): string
     *   {
     *       return $this->view('counter');
     *   }
     */
    abstract public function render(): string;

    /**
     * Carrega e compila o arquivo de visualização separado (`views/{nome}.wire.php`, `.blade.php` ou `.php`)
     * utilizando BladeOne com cache em disco.
     *
     * @param string $viewName Nome da view (ex: 'counter' ou 'pasta.counter')
     * @param array<string, mixed> $extraData Variáveis extras opcionais para enviar à view
     */
    protected function view(string $viewName, array $extraData = []): string
    {
        $resolved = $this->resolveViewFile($viewName);
        $cacheDir = PhpWire::getCachePath();

        if (!is_dir($cacheDir) && !mkdir($cacheDir, 0777, true) && !is_dir($cacheDir)) {
            throw new RuntimeException("Não foi possível criar o diretório de cache de views [{$cacheDir}].");
        }

        $blade = new BladeOne($resolved['dir'], $cacheDir, BladeOne::MODE_AUTO);
        $blade->setFileExtension($resolved['extension']);
        $blade->directive('wire', static fn(?string $expr = null): string => "<?= \\PhpWire\\PhpWire::mount({$expr}) ?>");
        $blade->directive('wireScripts', static fn(): string => '<?= \\PhpWire\\PhpWire::scripts() ?>');

        $vars = array_merge(
            $this->getPublicState(),
            [
                'errors' => $this->getErrors(),
                'component' => $this,
            ],
            $extraData
        );

        return trim($blade->run($resolved['logicalName'], $vars));
    }

    /**
     * Extrai todas as propriedades públicas do componente (ex: ['count' => 0])
     * convertendo enums para seus valores escalares para serialização no snapshot.
     *
     * @return array<string, mixed>
     */
    public function getPublicState(): array
    {
        $state = [];
        $ref = new ReflectionClass($this);

        foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            if ($prop->isStatic() || !$prop->isInitialized($this)) {
                continue;
            }

            $value = $prop->getValue($this);
            if ($value instanceof BackedEnum) {
                $value = $value->value;
            }

            $state[$prop->getName()] = $value;
        }

        return $state;
    }

    /**
     * Restaura os valores das propriedades públicas vindos do snapshot assinado,
     * convertendo automaticamente para o tipo declarado na propriedade (int, float, bool, string, array, BackedEnum).
     *
     * @param array<string, mixed> $state
     */
    public function hydrateState(array $state): void
    {
        $ref = new ReflectionClass($this);

        foreach ($state as $key => $value) {
            if (!$ref->hasProperty($key)) {
                continue;
            }

            $prop = $ref->getProperty($key);
            if (!$prop->isPublic() || $prop->isStatic()) {
                continue;
            }

            $prop->setValue($this, $this->castValueForProperty($prop, $value));
        }
    }

    /**
     * Aplica atualizações de propriedades enviadas pelo cliente (`wire:model`),
     * respeitando o atributo `#[Locked]` e disparando hooks `updating*` / `updated*`.
     *
     * @param array<string, mixed> $updates
     */
    public function applyUpdates(array $updates): void
    {
        $ref = new ReflectionClass($this);

        foreach ($updates as $key => $value) {
            if (!$ref->hasProperty($key)) {
                continue;
            }

            $prop = $ref->getProperty($key);
            if (!$prop->isPublic() || $prop->isStatic()) {
                continue;
            }

            if (!empty($prop->getAttributes(Locked::class))) {
                throw new RuntimeException(
                    "A propriedade pública [\${$key}] é protegida (#[Locked]) e não pode ser alterada pelo cliente."
                );
            }

            $castedValue = $this->castValueForProperty($prop, $value);
            $studlyKey = $this->toStudly($key);

            if (method_exists($this, 'updating')) {
                $this->updating($key, $castedValue);
            }

            $updatingHook = 'updating' . $studlyKey;
            if (method_exists($this, $updatingHook)) {
                $this->{$updatingHook}($castedValue);
            }

            $prop->setValue($this, $castedValue);

            if (method_exists($this, 'updated')) {
                $this->updated($key, $castedValue);
            }

            $updatedHook = 'updated' . $studlyKey;
            if (method_exists($this, $updatedHook)) {
                $this->{$updatedHook}($castedValue);
            }
        }
    }

    /**
     * Valida as propriedades públicas do componente usando regras passadas ou atributos `#[Validate]`.
     *
     * @param array<string, string>|null $customRules
     * @return array<string, mixed> Valores validados
     * @throws ValidationException
     */
    public function validate(?array $customRules = null): array
    {
        $this->resetValidation();
        $ref = new ReflectionClass($this);
        $rulesMap = $customRules ?? [];
        $customMessages = [];

        if ($customRules === null) {
            foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
                if ($prop->isStatic()) {
                    continue;
                }

                $attrs = $prop->getAttributes(Validate::class);
                if (empty($attrs)) {
                    continue;
                }

                $propRules = [];
                foreach ($attrs as $attr) {
                    /** @var Validate $instance */
                    $instance = $attr->newInstance();
                    $propRules[] = $instance->rules;
                    if ($instance->message !== null) {
                        $customMessages[$prop->getName()] = $instance->message;
                    }
                }

                $rulesMap[$prop->getName()] = implode('|', $propRules);
            }
        }

        $validated = [];

        foreach ($rulesMap as $field => $rulesString) {
            $value = null;
            if ($ref->hasProperty($field)) {
                $prop = $ref->getProperty($field);
                if ($prop->isPublic() && $prop->isInitialized($this)) {
                    $value = $prop->getValue($this);
                    if ($value instanceof BackedEnum) {
                        $value = $value->value;
                    }
                }
            }

            $error = $this->validateField($field, $value, $rulesString, $customMessages[$field] ?? null);
            if ($error !== null) {
                $this->errors[$field] = $error;
            } else {
                $validated[$field] = $value;
            }
        }

        if (!empty($this->errors)) {
            throw new ValidationException($this->errors);
        }

        return $validated;
    }

    /**
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    public function getError(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field] = $message;
    }

    public function resetValidation(?string $field = null): void
    {
        if ($field === null) {
            $this->errors = [];
            return;
        }

        unset($this->errors[$field]);
    }

    /**
     * Converte um valor recebido para o tipo declarado na propriedade.
     */
    private function castValueForProperty(ReflectionProperty $prop, mixed $value): mixed
    {
        $type = $prop->getType();
        if (!$type instanceof ReflectionNamedType) {
            return $value;
        }

        if ($value === null && $type->allowsNull()) {
            return null;
        }

        $typeName = $type->getName();

        if ($type->isBuiltin()) {
            return match ($typeName) {
                'int' => is_numeric($value) ? (int) $value : 0,
                'float' => is_numeric($value) ? (float) $value : 0.0,
                'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                'string' => is_scalar($value) ? (string) $value : '',
                'array' => is_array($value) ? $value : (array) $value,
                default => $value,
            };
        }

        if (is_subclass_of($typeName, BackedEnum::class) && (is_int($value) || is_string($value))) {
            $enum = $typeName::tryFrom($value);
            if ($enum !== null) {
                return $enum;
            }

            if (ctype_digit((string) $value)) {
                $intEnum = $typeName::tryFrom((int) $value);
                if ($intEnum !== null) {
                    return $intEnum;
                }
            }
        }

        return $value;
    }

    private function validateField(string $field, mixed $value, string $rulesString, ?string $customMessage): ?string
    {
        $rules = array_filter(array_map('trim', explode('|', $rulesString)), static fn(string $r): bool => $r !== '');

        foreach ($rules as $rule) {
            $parts = explode(':', $rule, 2);
            $name = strtolower($parts[0]);
            $param = $parts[1] ?? '';

            $errorMessage = match ($name) {
                'required' => ($value === null || $value === '' || $value === [])
                    ? "O campo {$field} é obrigatório."
                    : null,
                'integer', 'int' => filter_var($value, FILTER_VALIDATE_INT) === false
                    ? "O campo {$field} deve ser um número inteiro."
                    : null,
                'numeric' => !is_numeric($value)
                    ? "O campo {$field} deve ser numérico."
                    : null,
                'email' => (!is_string($value) || filter_var($value, FILTER_VALIDATE_EMAIL) === false)
                    ? "O campo {$field} deve ser um e-mail válido."
                    : null,
                'min' => $this->checkMinViolation($value, (float) $param)
                    ? (is_numeric($value)
                        ? "O campo {$field} deve ser no mínimo {$param}."
                        : "O campo {$field} deve ter pelo menos {$param} caracteres.")
                    : null,
                'max' => $this->checkMaxViolation($value, (float) $param)
                    ? (is_numeric($value)
                        ? "O campo {$field} deve ser no máximo {$param}."
                        : "O campo {$field} deve ter no máximo {$param} caracteres.")
                    : null,
                'in' => !in_array((string) $value, array_map('trim', explode(',', $param)), true)
                    ? "O campo {$field} deve ser um dos seguintes valores: {$param}."
                    : null,
                default => null,
            };

            if ($errorMessage !== null) {
                return $customMessage ?? $errorMessage;
            }
        }

        return null;
    }

    private function checkMinViolation(mixed $value, float $min): bool
    {
        if (is_numeric($value)) {
            return (float) $value < $min;
        }

        if (is_string($value)) {
            return mb_strlen($value) < (int) $min;
        }

        if (is_array($value)) {
            return count($value) < (int) $min;
        }

        return true;
    }

    private function checkMaxViolation(mixed $value, float $max): bool
    {
        if (is_numeric($value)) {
            return (float) $value > $max;
        }

        if (is_string($value)) {
            return mb_strlen($value) > (int) $max;
        }

        if (is_array($value)) {
            return count($value) > (int) $max;
        }

        return false;
    }

    private function toStudly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }

    /**
     * Localiza o arquivo de view na pasta configurada em `PhpWire::init(['views' => ...])`
     * ou relativo à pasta do próprio componente se `views` não tiver sido configurado.
     *
     * @return array{dir: string, logicalName: string, extension: string, fullPath: string}
     */
    private function resolveViewFile(string $viewName): array
    {
        $extensions = ['.wire.php', '.blade.php', '.php'];

        if (is_file($viewName)) {
            $dir = dirname($viewName);
            $basename = basename($viewName);
            foreach ($extensions as $ext) {
                if (str_ends_with($basename, $ext)) {
                    return [
                        'dir' => $dir,
                        'logicalName' => substr($basename, 0, -strlen($ext)),
                        'extension' => $ext,
                        'fullPath' => $viewName,
                    ];
                }
            }

            return [
                'dir' => $dir,
                'logicalName' => pathinfo($basename, PATHINFO_FILENAME),
                'extension' => '.' . pathinfo($basename, PATHINFO_EXTENSION),
                'fullPath' => $viewName,
            ];
        }

        $relative = str_replace('.', DIRECTORY_SEPARATOR, $viewName);
        $searchDirs = [];

        if (PhpWire::getViewPath() !== '') {
            $searchDirs[] = PhpWire::getViewPath();
        }

        // Fallback automático: procura também numa pasta `views` ao lado de `app` ou do componente
        $classFile = (new ReflectionClass($this))->getFileName();
        if ($classFile !== false) {
            $compDir = dirname($classFile);
            $searchDirs[] = $compDir . DIRECTORY_SEPARATOR . 'views';
            $searchDirs[] = dirname($compDir, 2) . DIRECTORY_SEPARATOR . 'views';
            $searchDirs[] = dirname($compDir) . DIRECTORY_SEPARATOR . 'Views'; // Padrão CodeIgniter 4 (app/Views)
        }

        foreach ($searchDirs as $basePath) {
            foreach ($extensions as $ext) {
                $file = $basePath . DIRECTORY_SEPARATOR . $relative . $ext;
                if (is_file($file)) {
                    return [
                        'dir' => $basePath,
                        'logicalName' => $viewName,
                        'extension' => $ext,
                        'fullPath' => $file,
                    ];
                }
            }
        }

        throw new RuntimeException("View [{$viewName}] não encontrada.");
    }
}
