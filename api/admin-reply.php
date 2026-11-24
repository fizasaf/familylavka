<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if (!(\$_SESSION['admin_logged_in'] ?? false)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Доступ запрещён']);
    exit;
}

\$input = json_decode(file_get_contents('php://input'), true);
$ticket_id = $input['ticket_id'] ?? null;
$message = htmlspecialchars($input['message'] ?? '');

if (!$ticket_id || !$message) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Не переданы данные']);
    exit;
}

try {
    \$pdo = new PDO('mysql:host=localhost;dbname=familylavka;charset=utf8mb4', 'root', '');
    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Опционально: можно автоматически ставить статус 'in_progress', если он еще 'new'
    // $check = $pdo->query("SELECT status FROM support_tickets WHERE id = \$ticket_id")->fetch();
    // if (\$check['status'] === 'new') { ... UPDATE status ... }

    // Пишем сообщение от админа
    $stmt = $pdo->prepare('INSERT INTO support_messages (ticket_id, sender_type, message) VALUES (?, ?, ?)');
    $res = $stmt->execute([$ticket_id, 'admin', $message]);

    if (\$res) {
        echo json_encode(['success' => true, 'message' => 'Ответ отправлен']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Не удалось сохранить сообщение']);
    }
} catch (Exception \$e) {
    error_log('Admin Reply Error: ' . \$e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ошибка сервера']);
}
?>
