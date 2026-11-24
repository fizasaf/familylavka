<?php
// --- НАСТРОЙКИ ЛОГИРОВАНИЯ ---
$logDir = __DIR__ . '/../../logs';
$logFile = $logDir . '/order_errors.log';

if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}
if (!file_exists($logFile)) {
    file_put_contents($logFile, '');
    chmod($logFile, 0777);
}

function logError($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Отладка (только для localhost!)
ini_set('display_errors', 1);
ini_set('error_reporting', E_ALL);

header('Content-Type: application/json; charset=utf-8');

session_start();

logError("--- НАЧАЛО ОБРАБОТКИ ЗАКАЗА ---");
logError("Session ID: " . session_id());

// 1. Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    logError("ОШИБКА: Сессия не найдена! Нет user_id.");
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Пользователь не авторизован. Войдите в профиль.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
logError("Найден user_id в сессии: " . $userId);

// 2. Получение JSON из тела запроса
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    logError("ОШИБКА: Не удалось декодировать JSON. Raw: " . file_get_contents('php://input'));
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Неверный формат JSON.']);
    exit;
}

// Проверка обязательных полей
if (empty($input['delivery']) || empty($input['phone']) || empty($input['address'])) {
    logError("ОШИБКА: Не заполнены обязательные поля.");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Заполните все поля: доставка, телефон, адрес.']);
    exit;
}

// 3. Подключение к БД
try {
    $pdo = new PDO('mysql:host=localhost;dbname=familylavka;charset=utf8', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    logError("Успешное подключение к БД familylavka");
} catch (PDOException $e) {
    logError("ОШИБКА ПОДКЛЮЧЕНИЯ К БД: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ошибка соединения с базой данных.']);
    exit;
}

// 4. Получаем данные пользователя
try {
    $userStmt = $pdo->prepare('SELECT id, email, login FROM users WHERE id = ?');
    $userStmt->execute([$userId]);
    $userData = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$userData) {
        logError("ОШИБКА: Пользователь с ID {$userId} не найден в таблице users!");
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Пользователь не найден в базе данных.']);
        exit;
    }

    $userEmail = $userData['email'];
    $userLogin = $userData['login'] ?? 'Клиент';
    logError("Пользователь найден: Email={$userEmail}");
} catch (Exception $e) {
    logError("ОШИБКА ПРИ ВЫБОРКЕ ПОЛЬЗОВАТЕЛЯ: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ошибка проверки пользователя.']);
    exit;
}

// 5. Расчёт суммы
$items = $input['items'] ?? [];
$totalAmount = 0;

foreach ($items as $item) {
    $price = isset($item['price']) ? (float)$item['price'] : 0;
    $qty = isset($item['qty']) ? (int)$item['qty'] : 0;
    $totalAmount += ($price * $qty);
}

if ($totalAmount <= 0) {
    logError("ОШИБКА: Сумма заказа равна 0 или корзина пуста.");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Корзина пуста или сумма равна 0.']);
    exit;
}

$itemsJson = json_encode($items, JSON_UNESCAPED_UNICODE);

// 6. Создание заказа
try {
    $pdo->beginTransaction();
    logError("Начинаем транзакцию и вставку заказа...");

    $email = $_POST['email'] ?? ''; // Берем email из формы

    $stmt = $pdo->prepare('INSERT INTO orders (user_id, email, delivery_type, phone, address, total_amount, status, items_json, created_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');

    $stmt->execute([
        $userId,
        $userEmail,
        $input['delivery'],
        $input['phone'],
        $input['address'],
        $totalAmount,
        'pending',
        $itemsJson
    ]);

    $orderId = $pdo->lastInsertId();
    $pdo->commit();
    logError("УСПЕХ: Заказ создан! ID заказа: {$orderId}");

    // Отправка писем ПОСЛЕ успешного сохранения
    sendOrderEmails($orderId, $userLogin, $userEmail, $input, $items, $totalAmount);

    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'message' => 'Заказ успешно оформлен!',
        'debug' => [
            'user_id_saved' => $userId,
            'email_saved' => $userEmail
        ]
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    $errorMsg = $e->getMessage();
    logError("ОШИБКА СОХРАНЕНИЯ ЗАКАЗА: " . $errorMsg);

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Произошла ошибка при сохранении заказа.',
        'details' => $errorMsg
    ]);
}

/**
 * Функция отправки писем (должна быть ниже всех вызовов, но ДО конца файла)
 */
function sendOrderEmails($orderId, $login, $email, $input, $items, $total) {
    logError("Попытка отправки писем для заказа #{$orderId}...");

    $subjectClient = 'Подтверждение заказа №' . $orderId . ' в FamilyLavka';
    $subjectAdmin = 'НОВЫЙ ЗАКАЗ №' . $orderId . ' (FamilyLavka)';

    $itemsHtml = '';
    foreach ($items as $item) {
        $name = htmlspecialchars($item['name'] ?? 'Товар');
        $qty = (int)($item['qty'] ?? 1);
        $price = (float)($item['price'] ?? 0);
        $subtotal = $price * $qty;
        $itemsHtml .= "<li><strong>{$name}</strong> x{$qty} — {$subtotal} ₽</li>";
    }

    $bodyClient = "
        <h2>Здравствуйте, {$login}!</h2>
        <p>Ваш заказ №{$orderId} успешно принят в обработку.</p>
        <p><strong>Доставка:</strong> {$input['delivery']}</p>
        <p><strong>Адрес:</strong> {$input['address']}</p>
        <p><strong>Телефон:</strong> {$input['phone']}</p>
        <h3>Состав заказа:</h3>
        <ul>{$itemsHtml}</ul>
        <h3>Итого: {$total} ₽</h3>
    ";

    $bodyAdmin = "
        <h2>⚠️ НОВЫЙ ЗАКАЗ №{$orderId} ⚠️</h2>
        <p><strong>Клиент:</strong> {$login} ({$email})</p>
        <p><strong>Доставка:</strong> {$input['delivery']}</p>
        <p><strong>Адрес:</strong> {$input['address']}</p>
        <p><strong>Телефон:</strong> {$input['phone']}</p>
        <h3>Товары:</h3>
        <ul>{$itemsHtml}</ul>
        <h3>Сумма: {$total} ₽</h3>
    ";

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: FamilyLavka <no-reply@familylavka.ru>\r\n";

    $sentClient = mail($email, $subjectClient, $bodyClient, $headers);
    $sentAdmin = mail('hello@familylavka.ru', $subjectAdmin, $bodyAdmin, $headers);

    if ($sentClient && $sentAdmin) {
        logError("Письма успешно отправлены.");
    } else {
        logError("ВНИМАНИЕ: Не удалось отправить одно или оба письма. Это не влияет на заказ.");
    }
}
?>
