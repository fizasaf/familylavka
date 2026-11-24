<?php
// Сессии тут не нужны для корзины, но оставляем на всякий случай
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- НАСТРОЙКИ БАЗЫ ДАННЫХ ---
$host = 'localhost';
$db   = 'soap_candle_shop';
$user = 'root';
$pass = '';              // <-- ВСТАВЬ СЮДА СВОЙ ПАРОЛЬ ОТ БАЗЫ
$charset = 'utf8mb4';
// ----------------------------

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка подключения к базе данных',
        'error_details' => $e->getMessage()
    ]);
    exit;
}

$rawUri = $_SERVER['REQUEST_URI'];
$path = strtolower(rtrim(parse_url($rawUri, PHP_URL_PATH), '/'));
$method = $_SERVER['REQUEST_METHOD'];

// Эндпоинт: получить список товаров
if (strpos($path, '/api/products') === 0 && $method === 'GET') {
    try {
        $stmt = $pdo->query('SELECT * FROM products');
        $products = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $products]);
    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Ошибка БД при получении товаров', 'error' => $e->getMessage()]);
    }
    exit;
}

// Эндпоинт: проверить, существует ли товар (защита от подмены цены)
if (strpos($path, '/api/check-product') === 0 && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['productId'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Требуется productId']);
        exit;
    }

    $productId = (int)$input['productId'];

    $stmt = $pdo->prepare('SELECT id, name, price FROM products WHERE id = ?');
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if ($product) {
        echo json_encode(['success' => true, 'product' => $product]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Товар не найден']);
    }
    exit;
}

http_response_code(404);
echo json_encode(['success' => false, 'message' => 'Маршрут не найден']);
exit;
