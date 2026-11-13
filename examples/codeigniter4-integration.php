<?php

/**
 * Referência de Integração no CodeIgniter 4 (não executado diretamente neste pacote).
 *
 * 1. Em `app/Controllers/BaseController.php` (dentro de `initController`):
 */
wire_init([
    'views' => APPPATH . 'Views/components',
    'components_path' => APPPATH . 'Components',
    'namespace' => 'App\\Components\\',
    'endpoint' => '/phpwire/update',
    'secret' => env('encryption.key', 'troque-por-uma-chave-secreta-forte'),
    'auto_listen' => false,
]);

/**
 * 2. Em `app/Config/Routes.php`:
 *
 * $routes->post('phpwire/update', static function () {
 *     $payload = service('request')->getJSON(true) ?? [];
 *     return service('response')->setJSON(\PhpWire\PhpWire::handlePayload($payload));
 * });
 *
 * 3. Em qualquer View do CodeIgniter (`app/Views/dashboard.php`):
 *
 * <?= wire('counter') ?>
 * <?= wire('todo-list') ?>
 * <?= wire_scripts() ?>
 */
