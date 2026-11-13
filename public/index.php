<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Inicializa em 1 linha!
// (Configura a pasta de views e já escuta os cliques AJAX do wire:click automaticamente)
wire_init([
    'views' => __DIR__ . '/../views',
]);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>PhpWire — Exemplo Mínimo (Increment / Decrement)</title>
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: #0f172a;
            color: #f8fafc;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .counter-card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 2rem 2.5rem;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
        }
        .label {
            margin: 0;
            color: #94a3b8;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .number {
            font-size: 4.5rem;
            font-weight: 800;
            margin: 1rem 0 1.5rem;
            color: #818cf8;
        }
        .buttons {
            display: flex;
            gap: 0.75rem;
        }
        .btn {
            padding: 0.75rem 1.25rem;
            font-size: 1rem;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.1s, opacity 0.15s;
        }
        .btn:active {
            transform: scale(0.96);
        }
        .btn-minus {
            background: #334155;
            color: #f8fafc;
        }
        .btn-plus {
            background: #6366f1;
            color: #ffffff;
        }
        .step-control {
            margin-bottom: 1.25rem;
            font-size: 0.9rem;
            color: #cbd5e1;
        }
        .step-input {
            width: 4.5rem;
            margin-left: 0.4rem;
            padding: 0.35rem 0.5rem;
            border-radius: 6px;
            border: 1px solid #475569;
            background: #0f172a;
            color: #f8fafc;
            text-align: center;
        }
        .loading-badge {
            margin-left: 0.4rem;
            font-size: 0.75rem;
            color: #a5b4fc;
        }
        .error-text {
            color: #fca5a5;
            font-size: 0.8rem;
            margin: 0.4rem 0 0;
        }
    </style>
</head>
<body>

    <!-- Monta o componente na tela (descobre App\Components\Counter automaticamente!) -->
    <?= wire('counter') ?>

    <!-- Conecta o wire:click ao PHP -->
    <?= wire_scripts() ?>

</body>
</html>
