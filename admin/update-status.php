<?php
require_once __DIR__ . '/../config.php'; // Подключение к БД
require_once __DIR__ . '/../notify.php'; // Подключение функций уведомлений

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';

    // 1. Обновляем статус в БД
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $orderId]);

    // 2. ЛОГИКА УВЕДОМЛЕНИЙ (Вставить этот блок)
    if ($newStatus === 'ready') { // Проверь, точно ли твой статус называется 'ready'

        // Получаем данные заказа, чтобы взять почту, телефон и адрес
        $stmt = $pdo->prepare("SELECT customer_name, email, phone, delivery_address FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            $res = notifyCustomerOrderReady(
                $orderId,
                $order['email'],
                $order['phone'],
                $order['delivery_address'],
                $order['customer_name']
            );

            // Опционально: можно записать факт отправки в лог-таблицу или просто вывести в консоль админки
            // echo json_encode(['status' => 'success', 'notifications' => \$res]);
        }
    }

    // 3. Возвращаем ответ фронтенду
    echo json_encode(['status' => 'success']);
}
?>
