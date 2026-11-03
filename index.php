<?php
// Autoloader for MVC (simple version)
spl_autoload_register(function ($class) {
    // App\Controllers\TypeController -> app/Controllers/TypeController.php
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

require_once __DIR__ . '/inc/auth.php';
start_session();

$router = new \App\Router();

// MVC Routes
$router->get('/index.php', [\App\Controllers\DashboardController::class, 'index']);
$router->post('/index.php', [\App\Controllers\DashboardController::class, 'index']);
$router->get('/dashboard.php', [\App\Controllers\DashboardController::class, 'index']);
$router->post('/dashboard.php', [\App\Controllers\DashboardController::class, 'index']);
$router->get('/types.php', [\App\Controllers\TypeController::class, 'index']);
$router->post('/types.php', [\App\Controllers\TypeController::class, 'index']);
$router->get('/users.php', [\App\Controllers\UserController::class, 'index']);
$router->post('/users.php', [\App\Controllers\UserController::class, 'index']);
$router->get('/settings_fields.php', [\App\Controllers\SettingsController::class, 'fields']);
$router->post('/settings_fields.php', [\App\Controllers\SettingsController::class, 'fields']);
$router->get('/edit.php', [\App\Controllers\DeviceController::class, 'edit']);
$router->post('/edit.php', [\App\Controllers\DeviceController::class, 'edit']);
$router->get('/change_password.php', [\App\Controllers\UserController::class, 'changePassword']);
$router->post('/change_password.php', [\App\Controllers\UserController::class, 'changePassword']);

// Dispatch
$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];
$router->dispatch($uri, $method);

