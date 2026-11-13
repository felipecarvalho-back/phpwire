<?php

declare(strict_types=1);

// Funções globais (sem namespace) para ficar super prático tanto em PHP procedural quanto em Views do CodeIgniter!

use PhpWire\PhpWire;

if (!function_exists('wire_init')) {
    /**
     * Inicializa o PhpWire (para PHP procedural ou BaseController / Bootstrap de frameworks).
     *
     * @param array<string, mixed> $config
     */
    function wire_init(array $config = []): void
    {
        PhpWire::init($config);
    }
}

if (!function_exists('wire')) {
    /**
     * Renderiza um componente PhpWire na view (ex: <?= wire('counter') ?>).
     */
    function wire(string $nameOrClass): string
    {
        return PhpWire::mount($nameOrClass);
    }
}

if (!function_exists('wire_scripts')) {
    /**
     * Imprime o script JS do PhpWire antes do </body>.
     */
    function wire_scripts(): string
    {
        return PhpWire::scripts();
    }
}
