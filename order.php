<?php
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Только POST']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
// Тут можно сохранить заказ в БД, отправить письмо и т.д.
// Для теста просто возвращаем заглушку:
echo json_encode([
    'order_id' => rand(1000, 9999),
    'status' => 'accepted'
]);
?>
