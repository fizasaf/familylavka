<?php
// 1. Только старт сессии, без лишних настроек (для OpenServer этого достаточно)
session_start();

// 2. Обязательные заголовки: только JSON, чтобы fetch не ломался
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

// Отключаем вывод любых предупреждений, чтобы они не попали в JSON
error_reporting(0);
ini_set('display_errors', 0);

// Читаем JSON из запроса
$input = json_decode(file_get_contents('php://input'), true);

// Если JSON битый — сразу отвечаем ошибкой
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Некорректный формат данных']);
    exit;
}

$login = $input['login'] ?? '';
$password = $input['password'] ?? '';

// --- ТЕСТОВАЯ ПРОВЕРКА (для быстрого запуска) ---
// В будущем тут будет проверка через БД и password_verify()
if ($login === 'admin' && $password === 'admin123') {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_login'] = $login;

    echo json_encode([
        'success' => true,
        'admin_login' => $login,
        'message' => 'Вы успешно вошли!'
    ]);
} else {
    // Неверный логин/пароль
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Неверный логин или пароль'
    ]);
}
?>
