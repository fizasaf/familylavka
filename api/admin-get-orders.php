<?php
session_set_cookie_params([
    'lifetime' => 3600,
    'path'     => '/',
    'domain'   => '',
    'secure'   => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();
header('Content-Type: application/json; charset=utf-8');
// Антикеш
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');

if (!($_SESSION['admin_logged_in'] ?? false)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Доступ запрещён']);
    exit;
}

// --- GET: список заказов ---
try {
    $pdo = new PDO('mysql:host=localhost;dbname=familylavka;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ДОБАВЛЕНО: явно выбираем id_str (красивый номер) и id (внутренний ID)
    $sql = "
        SELECT o.id, o.id_str, o.*, u.email AS user_email, u.id AS user_id
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        ORDER BY o.created_at DESC
    ";
    $stmt = $pdo->query($sql);
    $rawOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $validStatuses = ['new', 'in_progress', 'in_transit', 'delivered', 'closed', 'cancelled', 'processing', 'shipped', 'completed', 'paid'];

    $orders = array_map(function ($o) use ($validStatuses) {
        // Приоритет total_amount, если нет — оставляем total
        if (isset($o['total_amount'])) {
            $o['total'] = $o['total_amount'];
        }

        if (empty($o['status'])) {
            $o['status'] = 'new';
        }

        if (!empty($o['user_id'])) {
            $customerInfo = [
                'type'    => 'registered',
                'id'      => (int)$o['user_id'],
                'email'   => $o['user_email'] ?? '',
                'label'   => 'Пользователь #' . $o['user_id'] . ' (' . ($o['user_email'] ?: 'без email') . ')'
            ];
        } else {
            $customerInfo = [
                'type'    => 'guest',
                'id'      => null,
                'email'   => $o['user_email'] ?? '',
                'label'   => 'Гость (' . ($o['user_email'] ?: 'email не указан') . ')'
            ];
        }
        $o['customer_info'] = $customerInfo;

        // actions пустой — кнопки рисует JS в admin.html
        $o['actions'] = [];

        return $o;
    }, $rawOrders);

    echo json_encode([
        'success' => true,
        'orders'  => $orders
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('DB Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ошибка БД']);
} catch (Exception $e) {
    error_log('General Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Внутренняя ошибка']);
}
?>
