<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

// Если корзины нет, создадим пустую, чтобы видеть ID сессии
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

echo json_encode([
    'session_id' => session_id(),
    'cart_count' => count($_SESSION['cart']),
    'cart' => $_SESSION['cart'],
    'timestamp' => time()
]);
