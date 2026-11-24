<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);

$productId = (int)($input['product_id'] ?? 0);
$userId = (int)($input['user_id'] ?? 0);
$userName = trim($input['user_name'] ?? '');
$rating = (int)($input['rating'] ?? 0);
$text = trim($input['text'] ?? '');

if (!$productId || !$userName || $rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Заполните имя и оценку (1–5 звёзд)']);
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=familylavka;charset=utf8mb4',
        'root', '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка БД']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO reviews (product_id, user_id, user_name, rating, text, is_visible) VALUES (?, ?, ?, ?, ?, 1)");
    $stmt->execute([$productId, $userId ?: null, $userName, $rating, $text ?: null]);

    echo json_encode(['success' => true, 'message' => 'Отзыв добавлен']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()]);
}
?>
