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
            
            // Seed default device types
            $device_types = ['Laptop', 'Komputer stacjonarny', 'Monitor', 'Drukarka', 'Smartfon', 'Tablet', 'Router/Switch', 'Inny'];
            $stmt_d = $pdo->prepare("INSERT INTO device_types (name) VALUES (:name)");
            foreach ($device_types as $d) {
                $stmt_d->execute([':name' => $d]);
            }
        }

        // Ensure all default manufacturers are present in uppercase and sorted
        $raw_manufacturers = ["Asus", "HP", "Epson", "Grandstream", "U Are U", "DigiPresion", "TP-Link", "Microsoft", "Lenovo", "Dell", "Canon", "ActiveJet", "Philips", "Ever", "Kyocera", "Panasonic", "APC", "Acer", "PowerWalker", "Samsung", "Logitech", "Lestar", "RICOH", "Eaton", "VariFone", "NTT", "AOC", "Qoltec", "Wallner", "BenQ", "Energenie", "Dahua", "Toshiba", "Zebra", "Rexel", "Adobe", "MorelePC", "Iiyama", "MSI", "Fujitsu", "Zyxel", "PCF", "Shure", "Behringer", "ILC", "ITC", "AverMedia", "BOSE", "XSplit", "Audio-Technica", "Focusrite", "Feelworld", "Spacetronik", "DBS", "Konica Minolta", "Geemarc", "Stormshield", "Sophos", "Edimax", "Cisco", "NEC", "EDIOM", "ALTI", "POL-EKO", "Inwentaryzator EP", "Nefeni", "Infosystem", "G DATA", "QNAP", "Allen & Heath", "Hyundai", "Oki", "LG", "Fujifilm", "Opty", "EccoPC", "Infor", "esesja.tv", "Inny"];
        $manufacturers = array_unique(array_map('mb_strtoupper', $raw_manufacturers));
        sort($manufacturers, SORT_STRING);

        $stmt_m_check = $pdo->prepare("SELECT COUNT(*) FROM manufacturers WHERE name = :name");
        $stmt_m_insert = $pdo->prepare("INSERT INTO manufacturers (name) VALUES (:name)");
        foreach ($manufacturers as $m) {
            $stmt_m_check->execute([':name' => $m]);
            if ((int)$stmt_m_check->fetchColumn() === 0) {
                $stmt_m_insert->execute([':name' => $m]);
            }
        }

        // Seed default users if they do not exist
        $default_users = [
            ['username' => 'rwilczek', 'email' => 'rwilczek@inventaryzator.local', 'password' => 'Admin705', 'role' => 'admin'],
            ['username' => 'rszmitke', 'email' => 'rszmitke@inventaryzator.local', 'password' => 'Admin705', 'role' => 'admin'],
            ['username' => 'Igor', 'email' => 'igor@inventaryzator.local', 'password' => 'Igor123', 'role' => 'user'],
            ['username' => 'Artem', 'email' => 'artem@inventaryzator.local', 'password' => 'Artem123', 'role' => 'user']
        ];

        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
        $stmt_insert = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, active) VALUES (:username, :email, :password_hash, :role, 1)");

        foreach ($default_users as $du) {
            $stmt_check->execute([':username' => $du['username']]);
            if ((int)$stmt_check->fetchColumn() === 0) {
                $stmt_insert->execute([
                    ':username' => $du['username'],
                    ':email' => $du['email'],
                    ':password_hash' => password_hash($du['password'], PASSWORD_DEFAULT),
                    ':role' => $du['role']
                ]);
            }
        }
    } catch (PDOException $e) {
        die('Błąd połączenia z bazą danych: ' . htmlspecialchars($e->getMessage()));
    }

    return $pdo;
}
