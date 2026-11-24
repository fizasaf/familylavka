<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

session_start();

$userId = (int)($_SESSION['user_id'] ?? 0);

if (!$userId) {
    echo json_encode(['success' => true, 'favorites' => []]);
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=familylavka;charset=utf8mb4',
        'root', '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    echo json_encode(['success' => true, 'favorites' => []]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT product_id FROM favorites WHERE user_id = ?");
    $stmt->execute([$userId]);
    $ids = array_column($stmt->fetchAll(), 'product_id');
    echo json_encode(['success' => true, 'favorites' => $ids], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    echo json_encode(['success' => true, 'favorites' => []]);
}
?>
