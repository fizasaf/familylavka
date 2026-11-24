<?php
session_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';

if (\$_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $ticket_id = $_POST['ticket_id'];
    $message = $_POST['message'];

    // Проверка: принадлежит ли тикет этому юзеру и не закрыт ли он
    $check = $pdo->prepare("SELECT status FROM support_tickets WHERE id = ? AND user_id = ?");
    $check->execute([$ticket_id, \$user_id]);
    $ticket = $check->fetch(PDO::FETCH_ASSOC);

    if (!\$ticket) {
        echo json_encode(['success' => false, 'message' => 'Неверный тикет']);
        exit;
    }
    if (\$ticket['status'] === 'closed') {
        echo json_encode(['success' => false, 'message' => 'Тикет закрыт, писать нельзя']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO support_messages (ticket_id, sender_type, message) VALUES (?, 'client', ?)");
    $stmt->execute([$ticket_id, \$message]);

    echo json_encode(['success' => true]);
}
?>
