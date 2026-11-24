<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['order_id'])) {
    echo json_encode(['success' => false, 'error' => 'Не указан номер заказа']);
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=familylavka;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE orders SET status = 'paid' WHERE id_str = ?");
    $stmt->execute([$input['order_id']]);

    echo json_encode([
        'success' => true,
        'order_id' => $input['order_id'],
        'message' => 'Оплата подтверждена'
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Ошибка: ' . $e->getMessage()]);
    exit;
}
?>
