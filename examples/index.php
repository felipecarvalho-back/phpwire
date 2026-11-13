<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

wire_init([
    'views' => __DIR__ . '/views',
    'components_path' => __DIR__ . '/Components',
]);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PhpWire — Painel de Exemplos Interativos</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: #0b1120;
            color: #e2e8f0;
            margin: 0;
            padding: 2rem 1.25rem 3rem;
        }
        .container {
            max-width: 1120px;
            margin: 0 auto;
        }
        .hero {
            text-align: center;
            margin-bottom: 2rem;
        }
        .hero h1 {
            margin: 0 0 0.4rem;
            font-size: 2rem;
            color: #f8fafc;
        }
        .hero p {
            margin: 0;
            color: #94a3b8;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 1.5rem;
            align-items: start;
        }
        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.35);
        }
        .card-header {
            position: relative;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        .card-header h2 {
            margin: 0 0 0.25rem;
            font-size: 1.15rem;
            color: #f8fafc;
        }
        .subtitle {
            margin: 0;
            font-size: 0.8rem;
            color: #94a3b8;
            line-height: 1.4;
        }
        code {
            background: #0f172a;
            color: #a5b4fc;
            padding: 0.1rem 0.35rem;
            border-radius: 4px;
            font-size: 0.78rem;
        }
        .badge-loading {
            position: absolute;
            top: 0;
            right: 0;
            background: #312e81;
            color: #c7d2fe;
            font-size: 0.72rem;
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
            white-space: nowrap;
            pointer-events: none;
        }
        .counter-value {
            font-size: 3.5rem;
            font-weight: 800;
            text-align: center;
            color: #818cf8;
            margin: 0.75rem 0 1rem;
        }
        .field-inline, .inline-form, .search-controls, .actions, .filter-tabs, .two-cols {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
        }
        .field-inline { justify-content: center; margin-bottom: 0.75rem; font-size: 0.88rem; }
        .actions { justify-content: center; margin-top: 0.75rem; }
        .inline-form, .search-controls { margin-bottom: 0.75rem; }
        .inline-form .input, .search-controls .input:first-child { flex: 1; min-width: 160px; }
        .two-cols .field { flex: 1; min-width: 140px; }
        .stack-form { display: flex; flex-direction: column; gap: 0.75rem; }
        .field { display: flex; flex-direction: column; gap: 0.3rem; font-size: 0.85rem; color: #cbd5e1; }
        .input {
            width: 100%;
            padding: 0.55rem 0.75rem;
            border-radius: 8px;
            border: 1px solid #475569;
            background: #0f172a;
            color: #f8fafc;
            font-size: 0.9rem;
        }
        .input-sm { width: 5rem; text-align: center; }
        .select-sm { width: auto; min-width: 105px; }
        .btn {
            padding: 0.55rem 0.95rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.88rem;
            cursor: pointer;
            transition: opacity 0.15s, background-color 0.15s;
        }
        .btn:disabled { opacity: 0.75; cursor: wait; }
        .btn-primary { background: #6366f1; color: #fff; }
        .btn-secondary { background: #334155; color: #f8fafc; }
        .btn-accent { background: #0ea5e9; color: #0f172a; }
        .btn-ghost { background: transparent; border: 1px solid #475569; color: #cbd5e1; }
        .btn-sm { padding: 0.35rem 0.7rem; font-size: 0.8rem; }
        .filter-tabs { margin: 0.5rem 0 0.75rem; }
        .tab {
            background: #0f172a;
            color: #94a3b8;
            border: 1px solid #334155;
            border-radius: 6px;
            padding: 0.3rem 0.65rem;
            font-size: 0.78rem;
            cursor: pointer;
        }
        .tab.active { background: #4f46e5; color: #fff; border-color: #6366f1; }
        .todo-list, .user-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
        }
        .todo-item, .user-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.6rem;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 0.55rem 0.75rem;
        }
        .todo-item.is-done .todo-title { text-decoration: line-through; color: #64748b; }
        .todo-title { flex: 1; font-size: 0.9rem; }
        .todo-toggle, .btn-icon {
            background: transparent;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            font-size: 1rem;
            padding: 0.15rem 0.35rem;
        }
        .todo-toggle:hover { color: #818cf8; }
        .btn-icon:hover { color: #f87171; }
        .muted { font-size: 0.78rem; color: #94a3b8; }
        .pill {
            background: #334155;
            color: #e2e8f0;
            font-size: 0.72rem;
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
            text-transform: uppercase;
        }
        .hook-badge { margin: 0 0 0.6rem; font-size: 0.78rem; color: #94a3b8; }
        .meta-row {
            display: flex;
            justify-content: space-between;
            background: #0f172a;
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            font-size: 0.85rem;
        }
        .empty-state {
            text-align: center;
            color: #64748b;
            font-size: 0.85rem;
            padding: 1rem 0;
        }
        .card-footer { margin-top: 0.75rem; display: flex; justify-content: flex-end; }
        .error-text { color: #fca5a5; font-size: 0.78rem; margin: 0.2rem 0 0; }
        .success-banner {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid #10b981;
            color: #6ee7b7;
            padding: 0.6rem 0.75rem;
            border-radius: 8px;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="hero">
            <h1>⚡ PhpWire — Exemplos Interativos</h1>
            <p>4 componentes reativos em PHP puro rodando com BladeOne, Idiomorph e snapshots assinados via HMAC.</p>
        </header>

        <main class="grid">
            <?= wire('counter') ?>
            <?= wire('todo-list') ?>
            <?= wire('user-search') ?>
            <?= wire('profile-form') ?>
        </main>
    </div>

    <?= wire_scripts() ?>
</body>
</html>
