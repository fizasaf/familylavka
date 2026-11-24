<?php
// 1. Настройка сессии
session_set_cookie_params([
    'lifetime' => 3600, 'path' => '/', 'domain' => '',
    'secure' => false, 'httponly' => true, 'samesite' => 'Lax'
]);
session_start();

// Заголовки для JSON
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// 2. Проверка прав админа
if (!($_SESSION['admin_logged_in'] ?? false)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Доступ запрещён']);
    exit;
}

// 3. ПРЯМОЕ ПОДКЛЮЧЕНИЕ ЧЕРЕЗ MYSQLI (Игнорируем PDO и все скрытые скрипты)
$host = 'localhost';
$db   = 'familylavka';
$user = 'root';
$pass = ''; // Если есть пароль, впиши его сюда

$mysqli = new mysqli($host, $user, $pass, $db);

if ($mysqli->connect_error) {
    error_log('MySQLi Connection Error: ' . $mysqli->connect_error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ошибка подключения к БД']);
    exit;
}
$mysqli->set_charset('utf8mb4');

// 4. Выполняем ТОТ САМЫЙ рабочий запрос, который ты проверял в phpMyAdmin
$sql = "
    SELECT r.*, p.name AS product_name
    FROM reviews r
    LEFT JOIN products p ON r.product_id = p.id
    ORDER BY r.created_at DESC
";

$result = $mysqli->query($sql);

if (!$result) {
    error_log('Query Error: ' . $mysqli->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ошибка выполнения запроса']);
    $mysqli->close();
    exit;
}

$rawReviews = $result->fetch_all(MYSQLI_ASSOC);
$mysqli->close();

// 5. Преобразуем данные под фронтенд (маппинг полей)
$reviews = array_map(function($r) {
    // Берем is_approved из БД, кладем в ключ is_visible для JS
    $isVisibleValue = (int)($r['is_approved'] ?? 0);

    // Берем comment из БД, кладем в ключ text для JS
    $textValue = $r['comment'] ?? '';

    return [
        'id'          => (int)($r['id'] ?? 0),
        'product_id'  => (int)($r['product_id'] ?? 0),
        'product_name'=> $r['product_name'] ?? 'Товар удалён',
        'user_name'   => $r['user_name'] ?? null,
        'rating'      => (int)($r['rating'] ?? 0),
        'text'        => $textValue,
        'is_visible'  => $isVisibleValue,
        'created_at'  => $r['created_at'] ?? ''
    ];
}, $rawReviews);

// 6. Вывод результата
echo json_encode(['success' => true, 'reviews' => $reviews], JSON_UNESCAPED_UNICODE);
?>