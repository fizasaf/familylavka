<?php
session_set_cookie_params([
    'lifetime' => 3600, 'path' => '/', 'domain' => '',
    'secure' => false, 'httponly' => true, 'samesite' => 'Lax'
]);
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!($_SESSION['admin_logged_in'] ?? false)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Доступ запрещён']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$reviewId = (int)($input['id'] ?? 0);

if (!$reviewId) {
    echo json_encode(['success' => false, 'message' => 'Не указан ID отзыва']);
    exit;
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=familylavka;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("UPDATE reviews SET is_visible = 1 - is_visible WHERE id = ?");
    $stmt->execute([$reviewId]);

    $stmt = $pdo->prepare("SELECT is_visible FROM reviews WHERE id = ?");
    $stmt->execute([$reviewId]);
    $row = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'is_visible' => (int)$row['is_visible'],
        'message' => $row['is_visible'] ? 'Отзыв виден' : 'Отзыв скрыт'
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()]);
}
?>
