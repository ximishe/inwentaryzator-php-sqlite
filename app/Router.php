<?php
namespace App;

class Router {
    private $routes = [];

    public function get($path, $callback) {
        $this->routes['GET'][$path] = $callback;
    }

    public function post($path, $callback) {
        $this->routes['POST'][$path] = $callback;
    }

    public function dispatch($uri, $method) {
        $path = parse_url($uri, PHP_URL_PATH);
        // Remove base path
        $path = str_replace('/INVENTARYZATOR', '', $path);
        
        if ($path === '' || $path === '/') {
            $path = '/index.php';
        }

        if (isset($this->routes[$method][$path])) {
            $callback = $this->routes[$method][$path];
            if (is_array($callback)) {
                $controller = new $callback[0]();
                $action = $callback[1];
                return $controller->$action();
            }
            return call_user_func($callback);
        }

        // Procedural Fallback
        // Special case: if path is /index.php, we route to dashboard.php
        if ($path === '/index.php') {
            $file = __DIR__ . '/../dashboard.php';
        } else {
            $file = __DIR__ . '/../' . ltrim($path, '/');
        }

        // Prevent infinite loop if they try to access front controller directly
        if (basename($file) === 'front_controller.php') {
            http_response_code(404);
            echo "404 Not Found";
            return;
        }

        if (file_exists($file) && is_file($file)) {
            require_once $file;
            return;
        }

        http_response_code(404);
        echo "404 Not Found";
    }
}
