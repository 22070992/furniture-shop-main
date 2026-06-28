<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

require __DIR__ . '/../../../vendor/autoload.php';

$app = require __DIR__ . '/../../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$database = config('database.connections.' . config('database.default') . '.database');

$tables = collect(DB::select(
    'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME',
    [$database]
))->pluck('TABLE_NAME')->values();

$schema = [];
foreach ($tables as $table) {
    $columns = DB::select(
        'SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_KEY, EXTRA
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
         ORDER BY ORDINAL_POSITION',
        [$database, $table]
    );

    $foreignKeys = DB::select(
        'SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME, CONSTRAINT_NAME
         FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL
         ORDER BY COLUMN_NAME',
        [$database, $table]
    );

    $indexes = DB::select(
        'SELECT INDEX_NAME, COLUMN_NAME, NON_UNIQUE
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
         ORDER BY INDEX_NAME, SEQ_IN_INDEX',
        [$database, $table]
    );

    $schema[$table] = [
        'columns' => $columns,
        'foreign_keys' => $foreignKeys,
        'indexes' => $indexes,
        'count' => DB::table($table)->count(),
    ];
}

$routes = collect(Route::getRoutes())->map(function ($route) {
    return [
        'method' => implode('|', $route->methods()),
        'uri' => $route->uri(),
        'name' => $route->getName(),
        'action' => $route->getActionName(),
        'middleware' => $route->middleware(),
    ];
})->values();

$permissions = DB::table('permissions')
    ->select('name')
    ->orderBy('name')
    ->pluck('name')
    ->values();

$roles = DB::table('roles')
    ->select('id', 'name', 'guard_name')
    ->orderBy('id')
    ->get();

$demoAccounts = DB::table('users')
    ->select('user_id', 'email', 'first_name', 'last_name', 'is_staff', 'is_verified', 'is_active')
    ->whereIn('email', ['demo.user@furemir-demo.test', 'demo.admin@furemir-demo.test'])
    ->orderBy('is_staff')
    ->get();

$data = [
    'generated_at' => now()->toIso8601String(),
    'app' => [
        'name' => config('app.name'),
        'env' => config('app.env'),
        'url' => config('app.url'),
        'timezone' => config('app.timezone'),
        'database_connection' => config('database.default'),
        'database_name' => $database,
    ],
    'tables' => $tables,
    'schema' => $schema,
    'routes' => $routes,
    'roles' => $roles,
    'permissions' => $permissions,
    'demo_accounts' => $demoAccounts,
];

file_put_contents(
    __DIR__ . '/../data/project-data.json',
    json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);

echo 'Wrote docs/final-report/data/project-data.json' . PHP_EOL;
