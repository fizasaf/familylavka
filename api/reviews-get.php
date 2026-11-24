<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

$productId = (int)($_GET['product_id'] ?? 0);

if (!$productId) {
    echo json_encode(['success' => false, 'message' => 'Не указан товар', 'reviews' => []]);
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=familylavka;charset=utf8mb4',
        'root', '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка БД', 'reviews' => []]);
    exit;
}

try {
    // Отзывы
    $stmt = $pdo->prepare("
        SELECT r.*, u.email AS user_email
        FROM reviews r
        LEFT JOIN users u ON r.user_id = u.id
        WHERE r.product_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$productId]);
    $reviews = $stmt->fetchAll();

    // Средняя оценка
    $stmt = $pdo->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS count FROM reviews WHERE product_id = ?");
    $stmt->execute([$productId]);
    $stats = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'reviews' => $reviews,
        'avg_rating' => round((float)$stats['avg_rating'], 1),
        'reviews_count' => (int)$stats['count']
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage(), 'reviews' => []]);
}
?>
