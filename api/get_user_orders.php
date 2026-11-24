<?php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("SELECT id, status, delivery_address, total, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($orders);