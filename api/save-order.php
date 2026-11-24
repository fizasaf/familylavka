<?php
// api/save-order.php

// 1. Сразу ставим JSON и кодировку
header('Content-Type: application/json; charset=utf-8');

// 2. Разрешаем CORS для localhost (для тестов)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Только POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Метод не разрешён']);
    exit;
}

// Подключение к БД (подставь свои данные)
$host = 'localhost';
$db   = 'familylavka';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Если не соединилось — отдаём понятный JSON, а не HTML
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка соединения с БД',
        'debug'   => 'Проверьте имя БД, логин/пароль'
    ]);
    exit;
}

// Читаем JSON из тела запроса
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Неверный JSON в запросе']);
    exit;
}

// Проверяем обязательные поля
if (!isset($input['delivery'], $input['phone'], $input['address'], $input['total'], $input['items'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Отсутствуют обязательные поля']);
    exit;
}

// items сохраняем как JSON-строку
$itemsJson = json_encode($input['items'], JSON_UNESCAPED_UNICODE);

// Вставка в БД
try {
    $stmt = $pdo->prepare("INSERT INTO orders (delivery_type, user_id, phone, address, total_amount, items_json, email) VALUES (?, ?, ?, ?, ?)");
    $res = $stmt->execute([
        $user_id,
        $email
        $input['delivery'],
        $input['phone'],
        $input['address'],
        $input['total'],

        $itemsJson
    ]);

    if ($res) {
        echo json_encode([
            'success'  => true,
            'message'  => 'Заказ сохранён',
            'order_id' => $pdo->lastInsertId()
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Не удалось сохранить заказ']);
    }
} catch (\PDOException $e) {
    http_response_code(500);
    // Для тестов можно оставить debug, но в продакшене лучше скрыть детали
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка БД при сохранении',
        'debug'   => $e->getMessage() // <-- можно убрать в будущем
    ]);
}
?>
