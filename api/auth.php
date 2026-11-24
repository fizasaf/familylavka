<?php
// Отключаем вывод ошибок прямо в HTML (чтобы не ломать JSON), но логируем их
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Функция для безопасного ответа
function sendResponse($success, $message, $data = null) {
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// Подключение к БД (один раз)
try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=familylavka;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    error_log('DB Connection Error: ' . $e->getMessage());
    sendResponse(false, 'Ошибка соединения с базой данных. Проверьте настройки подключения.');
}

// =========================================================
// 🔥 ГЛАВНОЕ ДОБАВЛЕНИЕ: ПРОВЕРКА АВТОРИЗАЦИИ (для profile.html)
// =========================================================
if (isset($_GET['check']) && $_GET['check'] == 1) {
    if (!empty($_SESSION['user_id']) && !empty($_SESSION['logged_in'])) {
        sendResponse(true, 'Пользователь авторизован', [
            'loggedIn' => true,
            'user' => [
                'id'    => (int)$_SESSION['user_id'],
                'login' => $_SESSION['user_login'] ?? '',
                'email' => $_SESSION['user_email'] ?? '', // теперь есть и email
                'role'  => $_SESSION['user_role'] ?? 'user'
            ]
        ]);
    } else {
        sendResponse(false, 'Пользователь не авторизован', [
            'loggedIn' => false
        ]);
    }
}

// Читаем и проверяем входные данные (только для POST запросов с JSON)
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($input)) {
    sendResponse(false, 'Некорректный формат данных. Ожидается JSON.');
}

$action = $input['action'] ?? '';

// --- РЕГИСТРАЦИЯ ---
if ($action === 'register') {
    $login = trim($input['login'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';

    if (!$login || !$email || !$password) {
        sendResponse(false, 'Все поля обязательны: логин, email и пароль.');
    }

    if (strlen($password) < 8) {
        sendResponse(false, 'Пароль должен быть не менее 8 символов.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, 'Некорректный email.');
    }

    try {
        // Проверяем уникальность email и login заранее (опционально, но полезно для понятных ошибок)
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email OR login = :login');
        $stmt->execute([':email' => $email, ':login' => $login]);
        if ($stmt->fetch()) {
            sendResponse(false, 'Email или логин уже заняты.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare('INSERT INTO users (login, email, password_hash, role) VALUES (:login, :email, :password_hash, :role)');
        $stmt->execute([
            ':login'        => $login,
            ':email'        => $email,
            ':password_hash' => $hash,
            ':role'         => 'user'
        ]);

        $userId = (int)$pdo->lastInsertId();

        sendResponse(true, 'Аккаунт успешно создан. Теперь войдите.', [
            'id'      => $userId,
            'login'   => $login,
            'email'   => $email
        ]);

    } catch (PDOException $e) {
        $code = $e->getCode();
        // 23000 — нарушение уникальности (UNIQUE constraint)
        if ($code === '23000') {
            sendResponse(false, 'Пользователь с таким email или логином уже существует.');
        }
        error_log('Register DB Error: ' . $e->getMessage());
        sendResponse(false, 'Ошибка базы данных. Проверьте консоль сервера.');
    }
}

// --- ВХОД (LOGIN) — теперь поддерживает вход по логину ИЛИ по email ---
elseif ($action === 'login') {
    $loginOrEmail = trim($input['login_or_email'] ?? '');
    $password = $input['password'] ?? '';

    if (!$loginOrEmail || !$password) {
        sendResponse(false, 'Логин/email и пароль обязательны.');
    }

    try {
        // Ищем по логину ИЛИ email
        $stmt = $pdo->prepare('SELECT * FROM users WHERE login = :v OR email = :v LIMIT 1');
        $stmt->execute([':v' => $loginOrEmail]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            // Чтобы не подсказывать злоумышленнику, какой именно параметр неверен,
            // можно оставить общее сообщение. Если хочешь, можно сделать разные.
            sendResponse(false, 'Неверный логин/email или пароль.');
        }

        if (!password_verify($password, $user['password_hash'])) {
            sendResponse(false, 'Неверный логин/email или пароль.');
        }

        // Успешный вход
        $_SESSION['user_id']     = (int)$user['id'];
        $_SESSION['user_login']  = $user['login'];
        $_SESSION['user_email']  = $user['email']; // сохраняем email в сессии
        $_SESSION['user_role']   = $user['role'];
        $_SESSION['logged_in']   = true;

        sendResponse(true, 'Успешный вход', [
            'id'      => (int)$user['id'],
            'login'   => $user['login'],
            'email'   => $user['email'],
            'role'    => $user['role']
        ]);

    } catch (PDOException $e) {
        error_log('Login DB Error: ' . $e->getMessage());
        sendResponse(false, 'Ошибка соединения с базой данных.');
    }
}

// --- ВЫХОД (LOGOUT) ---
elseif ($action === 'logout') {
    session_destroy();
    sendResponse(true, 'Вы успешно вышли из системы.');
}

else {
    sendResponse(false, 'Укажите действие: login, register или logout.');
}
?>
