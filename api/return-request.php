<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['order_id'])) {
    echo json_encode(['success' => false, 'error' => 'Не указан номер заказа']);
    exit;
}
if (empty($input['items'])) {
    echo json_encode(['success' => false, 'error' => 'Не выбраны товары для возврата']);
    exit;
}
if (empty($input['reason'])) {
    echo json_encode(['success' => false, 'error' => 'Не указана причина возврата']);
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
    $orderId = $input['order_id'];

    // Ищем заказ — разделяем логику для строковых и числовых id
    if (is_numeric($orderId)) {
        $stmt = $pdo->prepare('SELECT id, id_str, status FROM orders WHERE id_str = ? OR id = ?');
        $stmt->execute([$orderId, $orderId]);
    } else {
        $stmt = $pdo->prepare('SELECT id, id_str, status FROM orders WHERE id_str = ?');
        $stmt->execute([$orderId]);
    }
    $order = $stmt->fetch();

    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'Заказ не найден']);
        exit;
    }

    if ($order['status'] === 'return-pending' || $order['status'] === 'return-approved' || $order['status'] === 'returned') {
        echo json_encode(['success' => false, 'error' => 'Возврат уже оформлен']);
        exit;
    }

    // Создаём таблицу returns если нет
    $pdo->exec("CREATE TABLE IF NOT EXISTS returns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id VARCHAR(50) NOT NULL,
        user_id INT NULL,
        email VARCHAR(255) NULL,
        items_json TEXT,
        reason VARCHAR(50) NOT NULL,
        comment TEXT,
        refund_method VARCHAR(20) NOT NULL,
        refund_details TEXT,
        status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
        created_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Записываем возврат
    $stmt = $pdo->prepare("
        INSERT INTO returns (order_id, user_id, email, items_json, reason, comment, refund_method, refund_details, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([
        $orderId,
        $input['user_id'] ?? null,
        $input['email'] ?? null,
        json_encode($input['items'], JSON_UNESCAPED_UNICODE),
        $input['reason'],
        $input['comment'] ?? '',
        $input['refund_method'] ?? 'card',
        $input['refund_details'] ?? ''
    ]);

    // Обновляем статус заказа — разделяем логику
    if (is_numeric($orderId)) {
        $stmt = $pdo->prepare("UPDATE orders SET status = 'return-pending' WHERE id_str = ? OR id = ?");
        $stmt->execute([$orderId, $orderId]);
    } else {
        $stmt = $pdo->prepare("UPDATE orders SET status = 'return-pending' WHERE id_str = ?");
        $stmt->execute([$orderId]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Заявка на возврат создана',
        'return_id' => $pdo->lastInsertId()
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Ошибка: ' . $e->getMessage()]);
    exit;
}
?>
