<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

session_start();

$input = json_decode(file_get_contents('php://input'), true);

$productId = (int)($input['product_id'] ?? 0);
$rating = (int)($input['rating'] ?? 0);
$comment = trim($input['comment'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);
$userName = trim($input['user_name'] ?? '');

if (!$productId || !$rating || $rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Неверные данные']);
    exit;
}
if (mb_strlen($comment) < 3) {
    echo json_encode(['success' => false, 'message' => 'Слишком короткий отзыв']);
    exit;
}

// Если не авторизован — используем переданное имя
if (!$userId && !$userName) {
    echo json_encode(['success' => false, 'message' => 'Укажите имя']);
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

// Если авторизован — берём email как имя
if ($userId) {
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    $userName = $user['email'] ?? 'Пользователь';
}

try {
    // Проверка: не оставлял ли уже отзыв (если авторизован)
    if ($userId) {
        $stmt = $pdo->prepare("SELECT id FROM reviews WHERE product_id = ? AND user_id = ?");
        $stmt->execute([$productId, $userId]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Вы уже оставили отзыв']);
            exit;
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO reviews (product_id, user_id, user_name, rating, comment)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$productId, $userId ?: null, $userName, $rating, $comment]);

    echo json_encode(['success' => true, 'message' => 'Отзыв добавлен'], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()]);
}
?>
