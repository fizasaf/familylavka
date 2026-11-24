<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$ticketId = isset($input['ticket_id']) ? (int)$input['ticket_id'] : 0;
$message = isset($input['message']) ? trim($input['message']) : '';

if ($ticketId < 1 || empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Не передан ticket_id или message']);
    exit;
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=familylavka;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("CREATE TABLE IF NOT EXISTS support_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_id INT NOT NULL,
        sender VARCHAR(10) NOT NULL DEFAULT 'client',
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    try {
        $pdo->exec("ALTER TABLE support_messages ADD COLUMN sender VARCHAR(10) NOT NULL DEFAULT 'client' AFTER ticket_id");
    } catch (PDOException $e) {}

    // Проверяем тикет и его статус
    $check = $pdo->prepare('SELECT id, status FROM support_tickets WHERE id = :id');
    $check->execute([':id' => $ticketId]);

    if ($check->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Тикет не найден']);
        exit;
    }

    $ticket = $check->fetch(PDO::FETCH_ASSOC);

    // Если чат закрыт — отклоняем
    if ($ticket['status'] === 'closed' || $ticket['status'] === 'cancelled') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Чат закрыт. Создайте новое обращение.']);
        exit;
    }

    $stmt = $pdo->prepare('INSERT INTO support_messages (ticket_id, sender, message) VALUES (:tid, :sender, :msg)');
    $stmt->execute([
        ':tid'    => $ticketId,
        ':sender' => 'client',
        ':msg'    => $message
    ]);

    $update = $pdo->prepare("UPDATE support_tickets SET status = 'in_progress' WHERE id = :id");
    $update->execute([':id' => $ticketId]);

    echo json_encode(['success' => true, 'message' => 'Сообщение отправлено']);

} catch (PDOException $e) {
    error_log('Client reply error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ошибка БД: ' . $e->getMessage()]);
}
?>
