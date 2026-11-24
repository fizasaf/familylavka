<?php
header('Content-Type: application/json; charset=utf-8');

// Перед вызовом mail()
$logFile = __DIR__ . '/orders.log';
$logEntry = date('Y-m-d H:i:s') . ' | ' . json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL;
file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

// Сюда придёт письмо (замените на свой e-mail)
$to = 'hello@familylavka.ru';
$subject = 'Новый заказ на FamilyLavka';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Только POST-запросы']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Неверный формат JSON']);
    exit;
}

// Получаем поля
$deliveryType = htmlspecialchars($data['deliveryType'] ?? '');
$address = htmlspecialchars($data['address'] ?? '');
$phone = htmlspecialchars($data['phone'] ?? '');
$name = htmlspecialchars($data['name'] ?? 'Гость');
$cart = $data['cart'] ?? [];

// Простая валидация
if (empty($deliveryType) || empty($address) || empty($cart)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Заполните все поля и добавьте товары в корзину']);
    exit;
}

// Считаем итог и формируем HTML-таблицу товаров
$total = 0;
$itemsHtml = '';
foreach ($cart as $item) {
    $qty = (int)($item['qty'] ?? 1);
    $price = (float)($item['price'] ?? 0);
    $sub = $qty * $price;
    $total += $sub;
    $itemsHtml .= "
        <tr>
            <td style='border:1px solid #ccc; padding:8px;'>{$item['name']}</td>
            <td style='border:1px solid #ccc; padding:8px; text-align:center;'>{$qty}</td>
            <td style='border:1px solid #ccc; padding:8px; text-align:right;'>{$price} ₽</td>
            <td style='border:1px solid #ccc; padding:8px; text-align:right; font-weight:bold;'>{$sub} ₽</td>
        </tr>
    ";
}

// Тело письма (HTML)
$message = "
<h2>Новый заказ на FamilyLavka</h2>
<p><strong>Клиент:</strong> {$name}</p>
<p><strong>Телефон:</strong> {$phone}</p>
<p><strong>Доставка:</strong> {$deliveryType}</p>
<p><strong>Адрес:</strong> {$address}</p>

<table style='width:100%; border-collapse:collapse; font-family:Arial, sans-serif;'>
    <thead>
        <tr style='background:#f0f0f0;'>
            <th style='border:1px solid #ccc; padding:8px;'>Товар</th>
            <th style='border:1px solid #ccc; padding:8px; width:60px; text-align:center;'>Кол-во</th>
            <th style='border:1px solid #ccc; padding:8px; text-align:right;'>Цена</th>
            <th style='border:1px solid #ccc; padding:8px; text-align:right;'>Сумма</th>
        </tr>
    </thead>
    <tbody>
        {$itemsHtml}
    </tbody>
    <tfoot>
        <tr>
            <td colspan='3' style='border:1px solid #ccc; padding:8px; font-size:1.1em; font-weight:bold; text-align:right;'>Итого:</td>
            <td style='border:1px solid #ccc; padding:8px; font-size:1.1em; font-weight:bold; text-align:right;'>{$total} ₽</td>
        </tr>
    </tfoot>
</table>
";

$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
$headers .= "From: no-reply@familylavka.local" . "\r\n";

// Отправка
$sent = mail($to, $subject, $message, $headers);

if ($sent) {
    echo json_encode([
        'success' => true,
        'message' => 'Заказ успешно оформлен! Мы получили его на почту.'
    ]);
} else {
    // Если mail() не сработал — возвращаем ошибку, но не блокируем демонстрацию
    echo json_encode([
        'success' => false,
        'message' => 'Заказ сформирован, но письмо не отправлено. Проверьте настройки почты на сервере.',
        'debug_data' => [
            'to' => $to,
            'subject' => $subject
        ]
    ]);
}
?>
