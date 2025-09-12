<?php
// db.php
session_start();

// Always detect root path safely
$rootPath = __DIR__; // This will always point to the folder where db.php is

$DB_HOST = '127.0.0.1';
$DB_NAME = 'gamehub_db';
$DB_USER = 'perlas';
$DB_PASS = 'Arron2477'; // default XAMPP password is usually empty

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]
    );
} catch (Exception $e) {
    die("Could not connect to database: " . $e->getMessage());
}

function is_logged_in() {
    return !empty($_SESSION['user_id']);
}

function current_user($pdo) {
    if (!is_logged_in()) return null;
    $stmt = $pdo->prepare("SELECT id, username, email, avatar FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
