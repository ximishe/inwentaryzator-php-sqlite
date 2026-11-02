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

        // Ensure app_fields table exists and has default fields
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS app_fields (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                field_key TEXT NOT NULL,
                label TEXT NOT NULL,
                field_type TEXT NOT NULL DEFAULT 'text',
                source_type TEXT NOT NULL DEFAULT 'native',
                type_id INTEGER DEFAULT NULL,
                show_in_main INTEGER NOT NULL DEFAULT 1,
                show_in_details INTEGER NOT NULL DEFAULT 1,
                show_in_form INTEGER NOT NULL DEFAULT 1,
                log_history INTEGER NOT NULL DEFAULT 1,
                is_required INTEGER NOT NULL DEFAULT 0,
                order_main INTEGER NOT NULL DEFAULT 0,
                order_details INTEGER NOT NULL DEFAULT 0,
                order_form INTEGER NOT NULL DEFAULT 0,
                FOREIGN KEY (type_id) REFERENCES device_type(id) ON DELETE CASCADE
            );
        ");

        $pdo->exec("UPDATE app_fields SET is_required = 0 WHERE field_key = 'asset_number';");

        $fields_count = (int)$pdo->query("SELECT COUNT(*) FROM app_fields")->fetchColumn();
        if ($fields_count === 0) {
            $default_fields = [
                ['field_key' => 'asset_number', 'label' => 'Numer inwentarzowy', 'field_type' => 'text', 'source_type' => 'native', 'is_required' => 0, 'show_in_main' => 1, 'show_in_details' => 1, 'show_in_form' => 1, 'log_history' => 1, 'order_main' => 10, 'order_details' => 10, 'order_form' => 10],
                ['field_key' => 'manufacturer', 'label' => 'Producent', 'field_type' => 'text', 'source_type' => 'native', 'is_required' => 0, 'show_in_main' => 1, 'show_in_details' => 1, 'show_in_form' => 1, 'log_history' => 1, 'order_main' => 20, 'order_details' => 20, 'order_form' => 20],
                ['field_key' => 'model', 'label' => 'Model', 'field_type' => 'text', 'source_type' => 'native', 'is_required' => 0, 'show_in_main' => 1, 'show_in_details' => 1, 'show_in_form' => 1, 'log_history' => 1, 'order_main' => 30, 'order_details' => 30, 'order_form' => 30],
                ['field_key' => 'serial_number', 'label' => 'Numer seryjny (S/N)', 'field_type' => 'text', 'source_type' => 'native', 'is_required' => 0, 'show_in_main' => 1, 'show_in_details' => 1, 'show_in_form' => 1, 'log_history' => 1, 'order_main' => 40, 'order_details' => 40, 'order_form' => 40],
                ['field_key' => 'type_id', 'label' => 'Typ urządzenia', 'field_type' => 'select', 'source_type' => 'native', 'is_required' => 0, 'show_in_main' => 1, 'show_in_details' => 1, 'show_in_form' => 1, 'log_history' => 1, 'order_main' => 50, 'order_details' => 50, 'order_form' => 50],
                ['field_key' => 'user_id', 'label' => 'Pracownik', 'field_type' => 'select', 'source_type' => 'native', 'is_required' => 0, 'show_in_main' => 1, 'show_in_details' => 1, 'show_in_form' => 1, 'log_history' => 1, 'order_main' => 60, 'order_details' => 60, 'order_form' => 60],
                ['field_key' => 'room', 'label' => 'Pokój', 'field_type' => 'text', 'source_type' => 'native', 'is_required' => 0, 'show_in_main' => 1, 'show_in_details' => 1, 'show_in_form' => 1, 'log_history' => 1, 'order_main' => 70, 'order_details' => 70, 'order_form' => 70],
                ['field_key' => 'status', 'label' => 'Status', 'field_type' => 'select', 'source_type' => 'native', 'is_required' => 0, 'show_in_main' => 1, 'show_in_details' => 1, 'show_in_form' => 1, 'log_history' => 1, 'order_main' => 80, 'order_details' => 80, 'order_form' => 80],
                ['field_key' => 'purchase_date', 'label' => 'Data zakupu', 'field_type' => 'date', 'source_type' => 'native', 'is_required' => 0, 'show_in_main' => 0, 'show_in_details' => 1, 'show_in_form' => 1, 'log_history' => 1, 'order_main' => 90, 'order_details' => 90, 'order_form' => 90],
                ['field_key' => 'purchase_price', 'label' => 'Cena zakupu', 'field_type' => 'number', 'source_type' => 'native', 'is_required' => 0, 'show_in_main' => 0, 'show_in_details' => 1, 'show_in_form' => 1, 'log_history' => 1, 'order_main' => 100, 'order_details' => 100, 'order_form' => 100],
                ['field_key' => 'invoice', 'label' => 'Nr faktury', 'field_type' => 'text', 'source_type' => 'native', 'is_required' => 0, 'show_in_main' => 0, 'show_in_details' => 1, 'show_in_form' => 1, 'log_history' => 1, 'order_main' => 110, 'order_details' => 110, 'order_form' => 110],
                ['field_key' => 'warranty_until', 'label' => 'Gwarancja do', 'field_type' => 'date', 'source_type' => 'native', 'is_required' => 0, 'show_in_main' => 0, 'show_in_details' => 1, 'show_in_form' => 1, 'log_history' => 1, 'order_main' => 120, 'order_details' => 120, 'order_form' => 120],
                ['field_key' => 'description', 'label' => 'Opis / Uwagi', 'field_type' => 'textarea', 'source_type' => 'native', 'is_required' => 0, 'show_in_main' => 0, 'show_in_details' => 1, 'show_in_form' => 1, 'log_history' => 1, 'order_main' => 130, 'order_details' => 130, 'order_form' => 130],
            ];

            $stmt_field = $pdo->prepare("
                INSERT INTO app_fields (field_key, label, field_type, source_type, is_required, show_in_main, show_in_details, show_in_form, log_history, order_main, order_details, order_form)
                VALUES (:field_key, :label, :field_type, :source_type, :is_required, :show_in_main, :show_in_details, :show_in_form, :log_history, :order_main, :order_details, :order_form)
            ");
            foreach ($default_fields as $df) {
                $stmt_field->execute([
                    ':field_key' => $df['field_key'],
                    ':label' => $df['label'],
                    ':field_type' => $df['field_type'],
                    ':source_type' => $df['source_type'],
                    ':is_required' => $df['is_required'],
                    ':show_in_main' => $df['show_in_main'],
                    ':show_in_details' => $df['show_in_details'],
                    ':show_in_form' => $df['show_in_form'],
                    ':log_history' => $df['log_history'],
                    ':order_main' => $df['order_main'],
                    ':order_details' => $df['order_details'],
                    ':order_form' => $df['order_form']
                ]);
            }
        }
    } catch (PDOException $e) {
        die('Błąd połączenia z bazą danych: ' . htmlspecialchars($e->getMessage()));
    }

    return $pdo;
}
