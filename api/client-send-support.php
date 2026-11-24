<?php
// Отключаем вывод ошибок в браузер (чтобы не ломать JSON), но включаем логирование
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Путь к файлу лога (будет создан в папке api/)
$logFile = __DIR__ . '/error_log.txt';
error_log("--- Запрос поддержки: Начало ---", 3, $logFile);

header('Content-Type: application/json; charset=utf-8');

// 1. Получение данных
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ошибка формата данных (не JSON)']);
    exit;
}

// 2. Валидация полей
$name    = trim($data['name'] ?? '');
$email   = trim($data['email'] ?? '');
$subject = trim($data['subject'] ?? '');
$message = trim($data['message'] ?? '');
$user_id = isset($data['user_id']) ? (int)$data['user_id'] : 0;

if (empty($name)) $name = 'Аноним';
if (empty($email) || empty($subject) || empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Заполните все обязательные поля (Тема, Сообщение, Email)']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Некорректный формат Email']);
    exit;
}

// 3. Настройки БД
$host = 'localhost';
$db   = 'familylavka';
$user = 'root';
$pass = ''; // <--- ВПИШИ СЮДА ПАРОЛЬ, ЕСЛИ ОН ЕСТЬ! Если нет - оставь пустым.
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Попытка подключения
    $pdo = new PDO($dsn, $user, $pass, $options);
    error_log("✅ Подключение к БД успешно", 3, $logFile);

    // Проверка наличия таблицы (на всякий случай)
    $checkTable = $pdo->query("SHOW TABLES LIKE 'support_tickets'");
    if ($checkTable->rowCount() === 0) {
        throw new Exception("Таблица support_tickets не найдена в базе данных!");
    }
    error_log("✅ Таблица support_tickets найдена", 3, $logFile);

    // Подготовка и выполнение запроса
    $stmt = $pdo->prepare("INSERT INTO support_tickets (name, email, subject, message, status, created_at, user_id)
                          VALUES (:name, :email, :subject, :message, :status, NOW(), :user_id)");

    $stmt->execute([
        ':name'     => $name,
        ':email'    => $email,
        ':subject'  => $subject,
        ':message'  => $message,
        ':status'   => 'new',
        ':user_id'  => $user_id
    ]);

    error_log("✅ Данные успешно вставлены. User ID: $user_id", 3, $logFile);
    echo json_encode(['success' => true, 'message' => 'Обращение успешно отправлено!']);

} catch (PDOException $e) {
    $errMsg = $e->getMessage();
    error_log("❌ PDO Exception: " . $errMsg, 3, $logFile);

    // Если ошибка "Access denied", это точно проблема с паролем в строке $pass
    if (strpos($errMsg, 'Access denied') !== false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Ошибка доступа к базе данных. Проверьте логин/пароль в client-send-support.php']);
        exit;
    }

    // Если ошибка "Unknown column", значит структура таблицы не совпадает
    if (strpos($errMsg, 'Unknown column') !== false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Ошибка структуры БД. Проверьте названия колонок в таблице support_tickets']);
        exit;
    }

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Внутренняя ошибка сервера. Подробности в error_log.txt']);
} catch (Exception $e) {
    error_log("❌ General Exception: " . $e->getMessage(), 3, $logFile);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()]);
}
?>
