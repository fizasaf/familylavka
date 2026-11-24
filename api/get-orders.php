<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$email = isset($_GET['email']) ? filter_var($_GET['email'], FILTER_VALIDATE_EMAIL) : null;

if (!$user_id && !$email) {
    echo json_encode(['success' => false, 'message' => 'Не указан пользователь']);
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
    echo json_encode(['success' => false, 'message' => 'Ошибка подключения к БД']);
    exit;
}

try {
    if ($user_id) {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$user_id]);
    } else {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE email = ? ORDER BY created_at DESC');
        $stmt->execute([$email]);
    }

    $orders = $stmt->fetchAll();
    $result = [];

    foreach ($orders as $order) {
        // Парсим товары
        $items = [];
        if (isset($order['items_json']) && $order['items_json']) {
            $decoded = json_decode($order['items_json'], true);
            if (is_array($decoded)) $items = $decoded;
        } elseif (isset($order['items']) && $order['items']) {
            $decoded = json_decode($order['items'], true);
            if (is_array($decoded)) $items = $decoded;
        }

        // Определяем id — предпочитаем id_str, иначе числовой id
        $orderId = $order['id_str'] ?? (string)($order['id'] ?? '');

        // Статус — приводим к нижнему регистру
        $status = strtolower(trim($order['status'] ?? 'new'));

        // Сумма
        $total = 0;
        if (isset($order['total'])) $total = (float)$order['total'];
        elseif (isset($order['total_amount'])) $total = (float)$order['total_amount'];

        $result[] = [
            'id' => (string)$orderId,
            'status' => $status,
            'total' => $total,
            'total_amount' => $total,
            'items' => $items,
            'created_at' => $order['created_at'] ?? null,
            // Дублируем для отладки
            '_raw_status' => $order['status'] ?? null,
            '_raw_id' => $order['id'] ?? null
        ];
    }

    echo json_encode(['success' => true, 'orders' => $result], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка запроса к БД: ' . $e->getMessage()]);
    exit;
}
?>
