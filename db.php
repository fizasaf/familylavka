<?php
// db.php — подключение к базе данных для проекта FamilyLavka

$host = 'localhost';
$db   = 'familylavka';
$user = 'root';
$pass = ''; // На OpenServer пароль по умолчанию пустой
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // Если база не найдена (например, ошибка 1049 Unknown database), это критично
    error_log('DB Connection failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ошибка подключения к базе данных']);
    exit;
}
?>
