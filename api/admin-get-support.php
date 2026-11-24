<?php
error_reporting(0);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (!($_SESSION['admin_logged_in'] ?? false)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Доступ запрещён']);
    exit;
}

$ticketId = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;

try {
    $pdo = new PDO('mysql:host=localhost;dbname=familylavka;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Создаём таблицу сообщений если нет
    $pdo->exec("CREATE TABLE IF NOT EXISTS support_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_id INT NOT NULL,
        sender VARCHAR(10) NOT NULL DEFAULT 'client',
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Добавляем колонку sender если её нет
    try {
        $pdo->exec("ALTER TABLE support_messages ADD COLUMN sender VARCHAR(10) NOT NULL DEFAULT 'client' AFTER ticket_id");
    } catch (PDOException $e) {}

    // Запрос тикетов
    if ($ticketId > 0) {
        $stmt = $pdo->prepare('SELECT t.*, u.email AS user_email FROM support_tickets t LEFT JOIN users u ON t.user_id = u.id WHERE t.id = :tid ORDER BY t.created_at DESC');
        $stmt->execute([':tid' => $ticketId]);
    } else {
        $stmt = $pdo->query('SELECT t.*, u.email AS user_email FROM support_tickets t LEFT JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC');
    }
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($tickets as $t) {
        // Загружаем сообщения
        $msgStmt = $pdo->prepare('SELECT * FROM support_messages WHERE ticket_id = :tid ORDER BY created_at ASC');
        $msgStmt->execute([':tid' => $t['id']]);
        $messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);

        // Миграция старых данных
        if (count($messages) === 0) {
            $createdAt = $t['created_at'] ?? date('Y-m-d H:i:s');
            $ins = $pdo->prepare('INSERT INTO support_messages (ticket_id, sender, message, created_at) VALUES (:tid, :sender, :msg, :created)');

            if (!empty($t['message'])) {
                $ins->execute([':tid' => $t['id'], ':sender' => 'client', ':msg' => $t['message'], ':created' => $createdAt]);
            }
            if (!empty($t['admin_response'])) {
                $ins->execute([':tid' => $t['id'], ':sender' => 'admin', ':msg' => $t['admin_response'], ':created' => date('Y-m-d H:i:s')]);
            }

            $msgStmt->execute([':tid' => $t['id']]);
            $messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $result[] = [
            'id'         => $t['id'],
            'subject'    => $t['subject'] ?? 'Без темы',
            'status'     => $t['status'] ?? 'new',
            'name'       => $t['name'] ?? 'Гость',
            'email'      => $t['email'] ?? ($t['user_email'] ?? ''),
            'message'    => $t['message'] ?? '',
            'admin_response' => $t['admin_response'] ?? '',
            'created_at' => $t['created_at'],
            'messages'   => $messages
        ];
    }

    echo json_encode(['success' => true, 'tickets' => $result], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('Admin get support error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ошибка БД: ' . $e->getMessage()]);
}
?>
