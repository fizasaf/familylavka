<?php
error_reporting(0);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!($_SESSION['admin_logged_in'] ?? false)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Доступ запрещён']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$ticketId = $input['ticket_id'] ?? null;
$response = $input['response'] ?? null;

if (!$ticketId || !$response) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Неверные данные']);
    exit;
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=familylavka;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Создаём таблицу сообщений, если нет
    $pdo->exec("CREATE TABLE IF NOT EXISTS support_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_id INT NOT NULL,
        sender VARCHAR(10) NOT NULL DEFAULT 'client',
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Если таблица уже существовала без колонки sender — добавляем
    try {
        $pdo->exec("ALTER TABLE support_messages ADD COLUMN sender VARCHAR(10) NOT NULL DEFAULT 'client' AFTER ticket_id");
    } catch (PDOException $e) {
        // Колонка уже есть — игнорируем
    }


    // Проверяем тикет
    $check = $pdo->prepare('SELECT id FROM support_tickets WHERE id = :id');
    $check->execute([':id' => $ticketId]);

    if ($check->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Тикет не найден']);
        exit;
    }

    // Вставляем ответ админа в переписку
    $stmt = $pdo->prepare('INSERT INTO support_messages (ticket_id, sender, message) VALUES (:tid, :sender, :msg)');
    $stmt->execute([
        ':tid'    => $ticketId,
        ':sender' => 'admin',
        ':msg'    => $response
    ]);

    // Обновляем admin_response и статус
    $update = $pdo->prepare("UPDATE support_tickets SET admin_response = :resp, status = 'in_progress' WHERE id = :id");
    $update->execute([
        ':resp' => $response,
        ':id'   => $ticketId
    ]);

    echo json_encode(['success' => true, 'message' => 'Ответ отправлен']);

} catch (PDOException $e) {
    error_log('Reply Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ошибка БД: ' . $e->getMessage()]);
}
?>
