<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Не авторизован', 'favorites' => []]);
    exit;
}

$userId = (int)$_SESSION['user_id'];

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=familylavka;charset=utf8mb4',
        'root', '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка БД', 'favorites' => []]);
    exit;
}

try {
    // Возвращаем массив ID товаров в избранном + полные данные товаров
    $stmt = $pdo->prepare("
        SELECT p.*, f.created_at AS fav_date
        FROM favorites f
        JOIN products p ON f.product_id = p.id
        WHERE f.user_id = ?
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$userId]);
    $products = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'favorites' => array_column($products, 'id'),
        'products' => $products
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage(), 'favorites' => []]);
}
?>
