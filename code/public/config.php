<?php
define('DB_HOST', 'db');
define('DB_NAME', 'visiteur_medical');
define('DB_USER', 'visiteur');
define('DB_PASS', 'visiteur123');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

session_start();

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: index.php?page=login');
        exit;
    }
}

function requireRole(string ...$roles): void {
    requireLogin();
    if (!in_array($_SESSION['user_role'] ?? '', $roles)) {
        header('Location: index.php?page=403');
        exit;
    }
}

function isChef(): bool {
    return ($_SESSION['user_role'] ?? '') === 'chef';
}

function isDelegue(): bool {
    return ($_SESSION['user_role'] ?? '') === 'delegue';
}

function isVisiteur(): bool {
    return ($_SESSION['user_role'] ?? '') === 'visiteur';
}

function getCurrentUser(): ?array {
    global $pdo;
    if (!isLoggedIn()) return null;
    $stmt = $pdo->prepare("SELECT u.*, r.nom as region_nom FROM utilisateurs u LEFT JOIN regions r ON u.region_id = r.id WHERE u.id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}
?>
