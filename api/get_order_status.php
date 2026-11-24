<?php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

// Получаем ID заказа
$orderId = (int)($_GET['order_id'] ?? 0);
$userId = $_SESSION['user_id'] ?? null;

if (!$orderId) {
    echo json_encode(['error' => 'No order ID']);
    exit;
}

// Получаем заказ (проверяем что он принадлежит текущему пользователю)
$stmt = $pdo->prepare("SELECT id, status, delivery_address, total, created_at FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo json_encode(['error' => 'Order not found']);
    exit;
}

$statusLabels = [
    'new' => 'Новый',
    'processing' => 'В работе',
    'shipping' => 'Передаём в доставку',
    'ready' => 'В пункте выдачи — можно забирать!',
    'completed' => 'Завершён',
    'cancelled' => 'Отменён',
];

$statusColors = [
    'new' => '#3498db',
    'processing' => '#f39c12',
    'shipping' => '#9b59b6',
    'ready' => '#27ae60',
    'completed' => '#2c3e50',
    'cancelled' => '#e74c3c',
];

$status = $order['status'] ?? 'new';

echo json_encode([
    'order_id' => $order['id'],
    'status' => $status,
    'status_label' => $statusLabels[$status] ?? $status,
    'status_color' => $statusColors[$status] ?? '#999',
    'delivery_address' => $order['delivery_address'],
    'total' => $order['total'],
    'created_at' => $order['created_at'],
]);