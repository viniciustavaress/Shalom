<?php
function getDB() {
    static $db = null;
    if ($db !== null) return $db;

    $dbPath = __DIR__ . '/bazar.db';
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $db->exec("PRAGMA journal_mode=WAL");

    $db->exec("CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        category TEXT NOT NULL,
        size TEXT NOT NULL,
        price REAL NOT NULL,
        image TEXT DEFAULT '',
        description TEXT DEFAULT '',
        active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT
    )");

    // Default settings
    $existing = $db->query("SELECT COUNT(*) FROM settings")->fetchColumn();
    if ($existing == 0) {
        $stmt = $db->prepare("INSERT INTO settings (key, value) VALUES (?, ?)");
        $stmt->execute(['whatsapp_number', '5511999999999']);
        $stmt->execute(['admin_password', password_hash('shalom2024', PASSWORD_DEFAULT)]);
        $stmt->execute(['bazar_address', 'Rua da Missão, 123 - Centro']);
    }

    // Seed products if empty
    $count = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
    if ($count == 0) {
        $seeds = [
            ['Vestido Floral Primavera', 'Feminino', 'M', 25.00, '', 'Lindo vestido floral, estado ótimo'],
            ['Blusa de Tricô Rosê', 'Feminino', 'G', 15.00, '', 'Blusa quentinha, pouco usada'],
            ['Calça Jeans Skinny', 'Feminino', 'P', 20.00, '', 'Jeans escuro, sem defeitos'],
            ['Camiseta Social Branca', 'Masculino', 'M', 18.00, '', 'Perfeita para o trabalho'],
            ['Bermuda Cargo Bege', 'Masculino', 'G', 22.00, '', 'Bermuda com vários bolsos'],
            ['Camisa Xadrez Flanela', 'Masculino', 'GG', 28.00, '', 'Ideal para o inverno'],
            ['Conjunto Body + Calça', 'Infantil', '2 anos', 30.00, '', 'Conjunto completo, estado novo'],
            ['Vestido de Festa Rosa', 'Infantil', '4 anos', 35.00, '', 'Usado uma vez, impecável'],
            ['Moletom Azul Marinho', 'Infantil', '6 anos', 20.00, '', 'Quentinho e confortável'],
        ];
        $stmt = $db->prepare("INSERT INTO products (name, category, size, price, image, description) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($seeds as $s) $stmt->execute($s);
    }

    return $db;
}

function getSetting($key) {
    $db = getDB();
    $stmt = $db->prepare("SELECT value FROM settings WHERE key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['value'] : null;
}

function setSetting($key, $value) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value");
    $stmt->execute([$key, $value]);
}
