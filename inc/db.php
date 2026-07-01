<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $db_dir = dirname(DB_PATH);
    if (!is_dir($db_dir)) {
        mkdir($db_dir, 0755, true);
    }

    $db_exists = file_exists(DB_PATH);

    try {
        $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        
        // Enforce foreign key constraints in SQLite
        $pdo->exec('PRAGMA foreign_keys = ON;');

        // Initialize schema if database is new
        if (!$db_exists) {
            $schema_file = __DIR__ . '/../sql/schema.sql';
            if (file_exists($schema_file)) {
                $sql = file_get_contents($schema_file);
                $pdo->exec($sql);
            }
            
            // Seed default manufacturers
            $manufacturers = ['Dell', 'HP', 'Lenovo', 'Apple', 'Samsung', 'Brother', 'Asus', 'Acer', 'Inny'];
            $stmt_m = $pdo->prepare("INSERT INTO manufacturers (name) VALUES (:name)");
            foreach ($manufacturers as $m) {
                $stmt_m->execute([':name' => $m]);
            }

            // Seed default device types
            $device_types = ['Laptop', 'Komputer stacjonarny', 'Monitor', 'Drukarka', 'Smartfon', 'Tablet', 'Router/Switch', 'Inny'];
            $stmt_d = $pdo->prepare("INSERT INTO device_types (name) VALUES (:name)");
            foreach ($device_types as $d) {
                $stmt_d->execute([':name' => $d]);
            }
        }
    } catch (PDOException $e) {
        die('Błąd połączenia z bazą danych: ' . htmlspecialchars($e->getMessage()));
    }

    return $pdo;
}
