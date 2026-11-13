<?php

declare(strict_types=1);

namespace PhpWire;

use PhpWire\Exceptions\ValidationException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;
use Throwable;

/**
 * Motor principal do PhpWire.
 * Funciona tanto em PHP Procedural (sem rotas extras) quanto em Frameworks MVC (CodeIgniter, Laravel, Slim).
 */
class PhpWire
{
    /** @var array<string, class-string<Component>> */
    private static array $components = [];

    private static string $viewPath = '';

    private static string $cachePath = '';

    /**
     * Namespace padrão onde os componentes ficam (ex: 'App\\Components\\').
     */
    private static string $componentNamespace = 'App\\Components\\';

    /**
     * Pasta física onde ficam as classes dos componentes (ex: __DIR__ . '/app/Components').
     * Permite carregar componentes automaticamente mesmo em projetos PHP procedural sem PSR-4 para App\.
     */
    private static string $componentsPath = '';

    /**
     * Se null, o JS envia o POST para a própria página atual (window.location.href),
     * que funciona em 100% dos projetos (XAMPP, subpastas, PHP procedural) sem configurar rotas!
     * Em frameworks como CodeIgniter, pode ser configurado para '/phpwire/update'.
     */
    private static ?string $endpoint = null;

    /**
     * Chave secreta usada para assinar o snapshot (HMAC-SHA256) e impedir adulteração no navegador.
     */
    private static ?string $secretKey = null;

    /**
     * Inicializa o PhpWire de forma simples (ideal para usar num bootstrap, BaseController do CodeIgniter ou topo do index.php).
     *
     * @param array{
     *     views?: string,
     *     cache_path?: string,
     *     components_path?: string,
     *     namespace?: string,
     *     endpoint?: string|null,
     *     secret?: string,
     *     components?: array<string, class-string<Component>>,
     *     auto_listen?: bool
     * } $config
     */
    public static function init(array $config = []): void
    {
        if (isset($config['views'])) {
            self::setViewPath($config['views']);
        }

        if (isset($config['cache_path'])) {
            self::setCachePath($config['cache_path']);
        }

        if (isset($config['components_path'])) {
            self::$componentsPath = rtrim($config['components_path'], '/\\');
        }

        if (isset($config['namespace'])) {
            self::$componentNamespace = rtrim($config['namespace'], '\\') . '\\';
        }

        if (array_key_exists('endpoint', $config)) {
            self::$endpoint = $config['endpoint'];
        }

        if (isset($config['secret']) && $config['secret'] !== '') {
            self::setSecretKey($config['secret']);
        }

        if (isset($config['components'])) {
            foreach ($config['components'] as $name => $class) {
                self::component($name, $class);
            }
        }

        // Por padrão, já intercepta cliques AJAX automaticamente se vier o header X-PhpWire!
        if (($config['auto_listen'] ?? true) === true) {
            if (self::listenAndServe()) {
                exit;
            }
        }
    }

    public static function setViewPath(string $path): void
    {
        self::$viewPath = rtrim($path, '/\\');
    }

    public static function getViewPath(): string
    {
        return self::$viewPath;
    }

    public static function setCachePath(string $path): void
    {
        self::$cachePath = rtrim($path, '/\\');
    }

    public static function getCachePath(): string
    {
        if (self::$cachePath !== '') {
            return self::$cachePath;
        }

        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpwire_views';
    }

    public static function setSecretKey(string $secret): void
    {
        self::$secretKey = $secret;
    }

    public static function getSecretKey(): string
    {
        if (self::$secretKey !== null && self::$secretKey !== '') {
            return self::$secretKey;
        }

        $envSecret = $_ENV['PHPWIRE_SECRET'] ?? $_ENV['APP_KEY'] ?? getenv('PHPWIRE_SECRET') ?: getenv('APP_KEY');
        if (is_string($envSecret) && $envSecret !== '') {
            return $envSecret;
        }

        return hash('sha256', __FILE__ . '|' . php_uname('n'));
    }

    /**
     * Registra um componente manualmente pelo apelido (opcional se usar a convenção de namespace).
     *
     * @param class-string<Component> $class
     */
    public static function component(string $name, string $class): void
    {
        self::$components[$name] = $class;
    }

    /**
     * PASSO 1 (Renderização Inicial):
     * Aceita tanto o apelido ('counter') quanto a própria classe (Counter::class).
     */
    public static function mount(string $nameOrClass): string
    {
        $class = self::resolveClass($nameOrClass);
        /** @var Component $instance */
        $instance = new $class();

        $html = $instance->render();
        $snapshot = self::buildSnapshot($class, $instance);

        return self::injectSnapshotInRootTag($html, $snapshot);
    }

    /**
     * PASSO 2 (Resposta ao clique AJAX):
     * Pode ser chamado automaticamente pelo `PhpWire::init()` (modo Procedural)
     * ou dentro de um Controller/Rota (modo CodeIgniter / Framework).
     */
    public static function listenAndServe(): bool
    {
        $isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
        $hasHeader = isset($_SERVER['HTTP_X_PHPWIRE']);

        if (!$isPost || !$hasHeader) {
            return false;
        }

        $rawInput = (string) file_get_contents('php://input');
        $decoded = json_decode($rawInput, true);
        $payload = is_array($decoded) ? $decoded : [];

        try {
            $response = self::handlePayload($payload);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return true;
    }

    /**
     * Processa os dados enviados pelo JS e retorna o array ['html' => ..., 'snapshot' => ...].
     * Útil para frameworks (ex: CodeIgniter `return $this->response->setJSON(PhpWire::handlePayload($data))`).
     *
     * @param array<string, mixed> $payload
     * @return array{html: string, snapshot: array{class: string, state: array<string, mixed>, checksum: string}}
     */
    public static function handlePayload(array $payload): array
    {
        $snapshot = isset($payload['snapshot']) && is_array($payload['snapshot']) ? $payload['snapshot'] : [];
        self::verifySnapshot($snapshot);

        $class = self::resolveClass((string) ($snapshot['class'] ?? ''));
        $state = isset($snapshot['state']) && is_array($snapshot['state']) ? $snapshot['state'] : [];
        $updates = isset($payload['updates']) && is_array($payload['updates']) ? $payload['updates'] : [];
        $actionExpr = trim((string) ($payload['action'] ?? ''));

        // 1. Recria o componente e restaura o estado anterior validado pelo checksum HMAC
        /** @var Component $instance */
        $instance = new $class();
        $instance->hydrateState($state);

        try {
            // 2. Aplica atualizações de wire:model (respeitando #[Locked] e hooks updating/updated)
            if (!empty($updates)) {
                $instance->applyUpdates($updates);
            }

            // 3. Executa a ação clicada/submetida (ex: increment ou increment(5))
            if ($actionExpr !== '') {
                [$method, $args] = self::parseAction($actionExpr);
                self::invokeAction($instance, $method, $args);
            }
        } catch (ValidationException) {
            // Erros já registrados em $instance->errors; prossegue para re-renderizar exibindo as mensagens.
        }

        // 4. Renderiza o HTML novo e gera o novo snapshot assinado
        $newSnapshot = self::buildSnapshot($class, $instance);
        $newHtml = self::injectSnapshotInRootTag($instance->render(), $newSnapshot);

        return [
            'html' => $newHtml,
            'snapshot' => $newSnapshot,
        ];
    }

    /**
     * Gera um snapshot assinado com HMAC-SHA256.
     *
     * @param class-string<Component> $class
     * @return array{class: string, state: array<string, mixed>, checksum: string}
     */
    public static function buildSnapshot(string $class, Component $instance): array
    {
        $state = $instance->getPublicState();

        return [
            'class' => $class,
            'state' => $state,
            'checksum' => self::generateChecksum($class, $state),
        ];
    }

    /**
     * Valida a integridade criptográfica do snapshot recebido do navegador.
     *
     * @param array<string, mixed> $snapshot
     */
    public static function verifySnapshot(array $snapshot): void
    {
        $class = $snapshot['class'] ?? null;
        $state = $snapshot['state'] ?? null;
        $checksum = $snapshot['checksum'] ?? null;

        if (!is_string($class) || $class === '' || !is_array($state) || !is_string($checksum) || $checksum === '') {
            throw new RuntimeException('Snapshot PhpWire inválido ou incompleto.');
        }

        $expected = self::generateChecksum($class, $state);
        if (!hash_equals($expected, $checksum)) {
            throw new RuntimeException('Snapshot PhpWire inválido ou adulterado.');
        }
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function generateChecksum(string $class, array $state): string
    {
        $payload = json_encode(
            ['class' => $class, 'state' => $state],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return hash_hmac('sha256', (string) $payload, self::getSecretKey());
    }

    /**
     * Faz o parse de expressões como `increment` ou `increment(5, 'texto')`.
     *
     * @return array{0: string, 1: array<int, mixed>}
     */
    private static function parseAction(string $actionExpr): array
    {
        if (!preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)(?:\((.*)\))?$/s', $actionExpr, $matches)) {
            throw new RuntimeException("Expressão de ação inválida [{$actionExpr}].");
        }

        $method = $matches[1];
        $argsRaw = isset($matches[2]) ? trim($matches[2]) : '';

        if ($argsRaw === '') {
            return [$method, []];
        }

        return [$method, self::parseArguments($argsRaw)];
    }

    /**
     * @return array<int, mixed>
     */
    private static function parseArguments(string $argsRaw): array
    {
        $args = [];
        $length = strlen($argsRaw);
        $current = '';
        $inQuote = null;
        $escaped = false;

        for ($i = 0; $i < $length; $i++) {
            $ch = $argsRaw[$i];

            if ($escaped) {
                $current .= $ch;
                $escaped = false;
                continue;
            }

            if ($ch === '\\' && $inQuote !== null) {
                $current .= $ch;
                $escaped = true;
                continue;
            }

            if ($ch === "'" || $ch === '"') {
                if ($inQuote === null) {
                    $inQuote = $ch;
                } elseif ($inQuote === $ch) {
                    $inQuote = null;
                }
                $current .= $ch;
                continue;
            }

            if ($ch === ',' && $inQuote === null) {
                $args[] = self::decodeScalarLiteral(trim($current));
                $current = '';
                continue;
            }

            $current .= $ch;
        }

        if (trim($current) !== '') {
            $args[] = self::decodeScalarLiteral(trim($current));
        }

        return $args;
    }

    private static function decodeScalarLiteral(string $token): mixed
    {
        if ($token === 'null' || $token === 'NULL') {
            return null;
        }

        if ($token === 'true' || $token === 'TRUE') {
            return true;
        }

        if ($token === 'false' || $token === 'FALSE') {
            return false;
        }

        if (
            (str_starts_with($token, "'") && str_ends_with($token, "'")) ||
            (str_starts_with($token, '"') && str_ends_with($token, '"'))
        ) {
            return stripcslashes(substr($token, 1, -1));
        }

        if (preg_match('/^-?\d+$/', $token)) {
            return (int) $token;
        }

        if (is_numeric($token)) {
            return (float) $token;
        }

        return $token;
    }

    /**
     * Invoca uma ação pública declarada no componente (bloqueando métodos mágicos e métodos internos de Component).
     *
     * @param array<int, mixed> $args
     */
    private static function invokeAction(Component $instance, string $method, array $args): void
    {
        if (str_starts_with($method, '__')) {
            throw new RuntimeException("Método mágico [{$method}] não pode ser chamado como ação.");
        }

        if (!method_exists($instance, $method)) {
            throw new RuntimeException("Ação [{$method}] não encontrada no componente.");
        }

        $refMethod = new ReflectionMethod($instance, $method);
        if (!$refMethod->isPublic() || $refMethod->isStatic() || $refMethod->isAbstract()) {
            throw new RuntimeException("Ação [{$method}] não é acessível.");
        }

        if ($method === 'render' || $refMethod->getDeclaringClass()->getName() === Component::class) {
            throw new RuntimeException("Método interno [{$method}] não pode ser chamado como ação.");
        }

        $typedArgs = self::coerceMethodArguments($refMethod->getParameters(), $args);
        $refMethod->invokeArgs($instance, $typedArgs);
    }

    /**
     * @param array<int, ReflectionParameter> $parameters
     * @param array<int, mixed> $args
     * @return array<int, mixed>
     */
    private static function coerceMethodArguments(array $parameters, array $args): array
    {
        $coerced = [];

        foreach ($args as $index => $arg) {
            if (!isset($parameters[$index])) {
                $coerced[] = $arg;
                continue;
            }

            $type = $parameters[$index]->getType();
            if (!$type instanceof ReflectionNamedType || ($arg === null && $type->allowsNull())) {
                $coerced[] = $arg;
                continue;
            }

            if ($type->isBuiltin()) {
                $coerced[] = match ($type->getName()) {
                    'int' => is_numeric($arg) ? (int) $arg : 0,
                    'float' => is_numeric($arg) ? (float) $arg : 0.0,
                    'bool' => filter_var($arg, FILTER_VALIDATE_BOOLEAN),
                    'string' => is_scalar($arg) ? (string) $arg : '',
                    default => $arg,
                };
            } else {
                $coerced[] = $arg;
            }
        }

        return $coerced;
    }

    /**
     * Descobre a classe PHP a partir do nome ('counter' -> 'App\Components\Counter') ou FQCN.
     *
     * @return class-string<Component>
     */
    private static function resolveClass(string $nameOrClass): string
    {
        $resolved = null;

        if (isset(self::$components[$nameOrClass])) {
            $resolved = self::$components[$nameOrClass];
        } elseif (class_exists($nameOrClass)) {
            $resolved = $nameOrClass;
        } else {
            // Converte 'counter' (ou 'App\Components\Counter') para o nome da classe 'Counter'
            $shortName = str_contains($nameOrClass, '\\')
                ? substr($nameOrClass, strrpos($nameOrClass, '\\') + 1)
                : str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $nameOrClass)));

            $candidate = self::$componentNamespace . $shortName;

            if (class_exists($candidate)) {
                $resolved = $candidate;
            } else {
                // Fallback automático: procura o arquivo Counter.php nas pastas de componentes (ideal para PHP procedural!)
                $searchDirs = [];
                if (self::$componentsPath !== '') {
                    $searchDirs[] = self::$componentsPath;
                }
                if (self::$viewPath !== '') {
                    $projectRoot = dirname(self::$viewPath);
                    $searchDirs[] = $projectRoot . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Components';
                    $searchDirs[] = $projectRoot . DIRECTORY_SEPARATOR . 'components';
                }

                foreach ($searchDirs as $dir) {
                    $file = $dir . DIRECTORY_SEPARATOR . $shortName . '.php';
                    if (is_file($file)) {
                        require_once $file;
                        if (class_exists($candidate)) {
                            $resolved = $candidate;
                            break;
                        }
                        if (class_exists($shortName)) {
                            $resolved = $shortName;
                            break;
                        }
                    }
                }
            }
        }

        if ($resolved === null || !is_subclass_of($resolved, Component::class)) {
            throw new RuntimeException("Componente PhpWire [{$nameOrClass}] não encontrado ou inválido.");
        }

        return $resolved;
    }

    /**
     * @param array{class: string, state: array<string, mixed>, checksum: string} $snapshot
     */
    private static function injectSnapshotInRootTag(string $html, array $snapshot): string
    {
        $json = htmlspecialchars((string) json_encode($snapshot, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

        return (string) preg_replace_callback(
            '/^(\s*<[a-zA-Z0-9\-]+)/',
            static fn(array $matches): string => $matches[1] . ' wire:snapshot="' . $json . '"',
            $html,
            1
        );
    }

    /**
     * Script JS modernizado com Idiomorph (DOM morphing), wire:click (com parâmetros),
     * wire:submit, wire:model / wire:model.live (com debounce) e wire:loading.
     */
    public static function scripts(): string
    {
        $endpointJs = self::$endpoint !== null
            ? json_encode(self::$endpoint, JSON_UNESCAPED_SLASHES)
            : 'window.location.href';

        return <<<HTML
<script src="https://unpkg.com/idiomorph@0.3.0/dist/idiomorph.min.js"></script>
<style>[wire\\:loading]{display:none;}</style>
<script>
(() => {
    const endpoint = {$endpointJs};
    const pendingTimers = new WeakMap();

    function collectUpdates(root) {
        const updates = {};
        root.querySelectorAll('[wire\\\\:model], [wire\\\\:model\\\\.live]').forEach((el) => {
            const prop = el.getAttribute('wire:model') || el.getAttribute('wire:model.live');
            if (!prop) return;
            if (el.type === 'checkbox') {
                updates[prop] = el.checked;
            } else if (el.type === 'radio') {
                if (el.checked) updates[prop] = el.value;
            } else {
                updates[prop] = el.value;
            }
        });
        return updates;
    }

    function toggleLoading(root, triggerEl, isLoading) {
        root.querySelectorAll('[wire\\\\:loading]').forEach((el) => {
            el.style.display = isLoading ? 'inline-block' : 'none';
        });
        if (triggerEl && 'disabled' in triggerEl) {
            triggerEl.disabled = isLoading;
        }
    }

    function applyMorph(root, html) {
        if (window.Idiomorph && typeof window.Idiomorph.morph === 'function') {
            window.Idiomorph.morph(root, html);
            return;
        }
        const tpl = document.createElement('template');
        tpl.innerHTML = html.trim();
        const nextRoot = tpl.content.firstElementChild;
        if (nextRoot) {
            root.replaceWith(nextRoot);
        } else {
            root.outerHTML = html;
        }
    }

    async function sendRequest(root, { action = '', triggerEl = null } = {}) {
        const snapshotRaw = root.getAttribute('wire:snapshot');
        if (!snapshotRaw) return;

        const snapshot = JSON.parse(snapshotRaw);
        const updates = collectUpdates(root);

        toggleLoading(root, triggerEl, true);
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-PhpWire': 'true'
                },
                body: JSON.stringify({ snapshot, updates, action })
            });

            if (!response.ok) {
                const err = await response.json().catch(() => ({}));
                console.error('PhpWire error:', err.error || response.statusText);
                return;
            }

            const data = await response.json();
            if (data.html) {
                applyMorph(root, data.html);
            }
        } finally {
            if (document.body.contains(root)) {
                toggleLoading(root, triggerEl, false);
            }
        }
    }

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[wire\\\\:click]');
        if (!button) return;

        const root = button.closest('[wire\\\\:snapshot]');
        if (!root) return;

        event.preventDefault();
        const action = button.getAttribute('wire:click') || '';
        sendRequest(root, { action, triggerEl: button });
    });

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('[wire\\\\:submit]');
        if (!form) return;

        const root = form.closest('[wire\\\\:snapshot]');
        if (!root) return;

        event.preventDefault();
        const action = form.getAttribute('wire:submit') || '';
        const submitBtn = form.querySelector('[type="submit"]');
        sendRequest(root, { action, triggerEl: submitBtn });
    });

    document.addEventListener('input', (event) => {
        const el = event.target.closest('[wire\\\\:model\\\\.live]');
        if (!el) return;

        const root = el.closest('[wire\\\\:snapshot]');
        if (!root) return;

        clearTimeout(pendingTimers.get(root));
        const timer = setTimeout(() => {
            sendRequest(root);
        }, 250);
        pendingTimers.set(root, timer);
    });

    document.addEventListener('change', (event) => {
        const el = event.target.closest('[wire\\\\:model]');
        if (!el || el.hasAttribute('wire:model.live')) return;

        const root = el.closest('[wire\\\\:snapshot]');
        if (!root) return;

        sendRequest(root);
    });
})();
</script>
HTML;
    }
}
