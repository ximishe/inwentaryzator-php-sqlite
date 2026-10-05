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
            
            // Seed default users if they do not exist
            $default_users = [
                ['username' => 'admin', 'email' => 'admin@inventaryzator.local', 'password' => 'CHANGE_ME_STRONG_PASSWORD', 'role' => 'admin', 'first_name' => 'Admin', 'last_name' => 'System'],
                ['username' => 'rwilczek', 'email' => 'rwilczek@inventaryzator.local', 'password' => 'Admin705', 'role' => 'admin', 'first_name' => 'Rafał', 'last_name' => 'Wilczek'],
                ['username' => 'rszmitke', 'email' => 'rszmitke@inventaryzator.local', 'password' => 'Admin705', 'role' => 'admin', 'first_name' => 'R.', 'last_name' => 'Szmitke'],
                ['username' => 'Igor', 'email' => 'igor@inventaryzator.local', 'password' => 'Igor123', 'role' => 'user', 'first_name' => 'Igor', 'last_name' => 'Kowalski'],
                ['username' => 'Artem', 'email' => 'artem@inventaryzator.local', 'password' => 'Artem123', 'role' => 'user', 'first_name' => 'Artem', 'last_name' => 'Nowak']
            ];

            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
            $stmt_insert = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, active, first_name, last_name) VALUES (:username, :email, :password_hash, :role, 1, :first_name, :last_name)");

            foreach ($default_users as $du) {
                $stmt_check->execute([':username' => $du['username']]);
                if ((int)$stmt_check->fetchColumn() === 0) {
                    $stmt_insert->execute([
                        ':username' => $du['username'],
                        ':email' => $du['email'],
                        ':password_hash' => password_hash($du['password'], PASSWORD_DEFAULT),
                        ':role' => $du['role'],
                        ':first_name' => $du['first_name'],
                        ':last_name' => $du['last_name']
                    ]);
                }
            }
        }
    } catch (PDOException $e) {
        die('Błąd połączenia z bazą danych: ' . htmlspecialchars($e->getMessage()));
    }

    return $pdo;
}
