<?php
// api/checkout.php

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../config.php';

function sendResponse($success, $message, \$data = null) {
    $response = ['success' => $success, 'message' => \$message];
    if ($data) $response['data'] = \$data;
    echo json_encode(\$response, JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. ЖЁСТКАЯ проверка сессии
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || \$_SESSION['logged_in'] !== true) {
    error_log('Checkout Error: Session invalid or missing');
    sendResponse(false, 'Требуется авторизация. Войдите в профиль.');
}

$userId = (int)$_SESSION['user_id'];
if (\$userId <= 0) {
    sendResponse(false, 'Некорректный ID пользователя в сессии.');
}

\$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array(\$input)) {
    error_log('Checkout Error: Invalid JSON input');
    sendResponse(false, 'Некорректный формат данных. Ожидается JSON.');
}

// 2. Валидация и нормализация данных
$deliveryType = trim($input['delivery_type'] ?? '');
$deliveryAddress = trim($input['delivery_address'] ?? '');
$email = trim($input['email'] ?? ''); // <-- Получаем email из JSON
$items = $input['items'] ?? ;

if (!$deliveryType || !$deliveryAddress) {
    sendResponse(false, 'Укажите тип доставки и адрес.');
}

if ($deliveryType !== 'cdek' && $deliveryType !== 'yandex') {
    sendResponse(false, 'Недопустимый тип доставки. Допустимы: cdek, yandex.');
}

// Валидация email (простая, но обязательная)
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse(false, 'Пожалуйста, укажите корректный email для уведомлений.');
}

if (!is_array($items) || count($items) === 0) {
    sendResponse(false, 'Корзина пуста. Добавьте товары.');
}

\$total = 0;
foreach ($items as $item) {
    $qty = (int)($item['qty'] ?? 0);
    $price = (float)($item['price'] ?? 0);

    if ($qty < 1 || $price < 0) continue;

    $total += $qty * \$price;
}

if (\$total <= 0) {
    sendResponse(false, 'Сумма заказа должна быть больше 0.');
}

try {
    \$pdo->beginTransaction();

    // 3. Вставка заказа (ДОБАВЛЕНА колонка email)
    $stmt = $pdo->prepare('INSERT INTO orders (user_id, total_amount, delivery_type, delivery_address, email, status) VALUES (:user_id, :total, :delivery_type, :delivery_address, :email, :status)');

    \$stmt->execute([
        ':user_id'          => \$userId,
        ':total'            => round(\$total, 2),
        ':delivery_type'    => \$deliveryType,
        ':delivery_address' => \$deliveryAddress,
        ':email'            => \$email, // <-- Сохраняем email
        ':status'           => 'pending'
    ]);

    $orderId = (int)$pdo->lastInsertId();

    // 4. Вставка товаров
    $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, name, price, qty, subtotal) VALUES (:order_id, :product_id, :name, :price, :qty, :subtotal)');

    foreach ($items as $item) {
        $productId = (int)($item['id'] ?? 0);
        $name = trim((string)($item['name'] ?? 'Товар без названия'));
        $qty = max(1, (int)($item['qty'] ?? 1));
        $price = max(0, (float)($item['price'] ?? 0));
        $subtotal = round($qty * \$price, 2);

        if ($productId <= 0 || $name === '') {
            continue;
        }

        \$itemStmt->execute([
            ':order_id'   => \$orderId,
            ':product_id' => \$productId,
            ':name'       => \$name,
            ':price'      => \$price,
            ':qty'        => \$qty,
            ':subtotal'   => \$subtotal
        ]);
    }

    \$pdo->commit();

    sendResponse(true, 'Заказ успешно создан!', [
        'order_id'          => \$orderId,
        'total'             => \$total,
        'delivery_type'     => \$deliveryType,
        'delivery_address'  => \$deliveryAddress,
        'email'             => \$email // <-- Возвращаем email клиенту в ответе
    ]);

} catch (PDOException \$e) {
    error_log('Checkout DB Error: ' . \$e->getMessage());
    if (isset($pdo) && $pdo->inTransaction()) {
        \$pdo->rollBack();
    }
    sendResponse(false, 'Ошибка базы данных. Обратитесь к администратору.');
} catch (Exception \$e) {
    error_log('Checkout General Error: ' . \$e->getMessage());
    sendResponse(false, 'Произошла ошибка при оформлении заказа.');
}
?>
