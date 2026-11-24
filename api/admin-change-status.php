<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Не авторизован']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['id']) || !isset($input['status'])) {
    echo json_encode(['success' => false, 'message' => 'Неверные параметры']);
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
    $type = $input['type'] ?? 'order';
    $id = $input['id'];
    $status = $input['status'];

    if ($type === 'return') {
        $stmt = $pdo->prepare("UPDATE returns SET status = ? WHERE id = ?");
        $stmt->execute([$status, (int)$id]);

        if ($status === 'approved' || $status === 'completed' || $status === 'rejected') {
            $stmt = $pdo->prepare("SELECT order_id FROM returns WHERE id = ?");
            $stmt->execute([(int)$id]);
            $returnRow = $stmt->fetch();

            if ($returnRow) {
                $orderId = $returnRow['order_id'];
                $orderStatus = 'closed';
                if ($status === 'completed') $orderStatus = 'returned';
                elseif ($status === 'approved') $orderStatus = 'return-approved';

                if (is_numeric($orderId)) {
                    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id_str = ? OR id = ?");
                    $stmt->execute([$orderStatus, $orderId, $orderId]);
                } else {
                    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id_str = ?");
                    $stmt->execute([$orderStatus, $orderId]);
                }
            }
        }
    } elseif ($type === 'support') {
        $stmt = $pdo->prepare("UPDATE support_tickets SET status = ? WHERE id = ?");
        $stmt->execute([$status, (int)$id]);
    } elseif ($type === 'review') {
        // Для отзывов: status = 'visible' → is_approved = 1, 'hidden' → is_approved = 0
        $isApproved = ($status === 'visible' || $status === 'approved') ? 1 : 0;
        $stmt = $pdo->prepare("UPDATE reviews SET is_approved = ? WHERE id = ?");
        $stmt->execute([$isApproved, (int)$id]);
    } else {
        if (is_numeric($id)) {
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->execute([$status, (int)$id]);
        } else {
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id_str = ?");
            $stmt->execute([$status, $id]);
        }
    }

    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()]);
    exit;
}
?>
