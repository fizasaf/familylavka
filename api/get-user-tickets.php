<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$ticketId = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;

if ($userId < 1) {
    echo json_encode(['success' => false, 'message' => 'Не передан user_id']);
    exit;
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=familylavka;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Создаём таблицу сообщений, если её нет
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


    // Запрос тикетов
    if ($ticketId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM support_tickets WHERE id = :tid AND user_id = :uid ORDER BY created_at DESC');
        $stmt->execute([':tid' => $ticketId, ':uid' => $userId]);
    } else {
        $stmt = $pdo->prepare('SELECT * FROM support_tickets WHERE user_id = :uid ORDER BY created_at DESC');
        $stmt->execute([':uid' => $userId]);
    }
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($tickets as $t) {
        // Проверяем, есть ли сообщения в support_messages
        $msgStmt = $pdo->prepare('SELECT * FROM support_messages WHERE ticket_id = :tid ORDER BY created_at ASC');
        $msgStmt->execute([':tid' => $t['id']]);
        $messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);

        // Если сообщений ещё нет — мигрируем из полей тикета
        if (count($messages) === 0) {
            $createdAt = $t['created_at'] ?? date('Y-m-d H:i:s');

            $ins = $pdo->prepare('INSERT INTO support_messages (ticket_id, sender, message, created_at) VALUES (:tid, :sender, :msg, :created)');

            // Исходное сообщение клиента
            if (!empty($t['message'])) {
                $ins->execute([':tid' => $t['id'], ':sender' => 'client', ':msg' => $t['message'], ':created' => $createdAt]);
            }

            // Ответ админа, если есть
            if (!empty($t['admin_response'])) {
                $ins->execute([':tid' => $t['id'], ':sender' => 'admin', ':msg' => $t['admin_response'], ':created' => date('Y-m-d H:i:s')]);
            }

            // Перечитываем
            $msgStmt->execute([':tid' => $t['id']]);
            $messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $result[] = [
            'id'         => $t['id'],
            'subject'    => $t['subject'] ?? 'Без темы',
            'status'     => $t['status'] ?? 'new',
            'created_at' => $t['created_at'],
            'messages'   => $messages
        ];
    }

    echo json_encode(['success' => true, 'tickets' => $result], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('Get tickets error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ошибка БД: ' . $e->getMessage()]);
}
?>
