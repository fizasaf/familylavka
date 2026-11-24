<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Нет прав']);
    exit;
}

\$data = json_decode(file_get_contents('php://input'), true);
$review_id = (int)($data['review_id'] ?? 0);
$action = $data['action'] ?? ''; // 'approve' или 'reject'

if ($review_id <= 0 || !in_array($action, ['approve', 'reject'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Некорректные данные']);
    exit;
}

require 'db.php';
$is_approved = ($action === 'approve') ? 1 : 0;

$stmt = $pdo->prepare('UPDATE reviews SET is_approved = ? WHERE id = ?');
if ($stmt->execute([$is_approved, \$review_id])) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ошибка сервера']);
}
?>
