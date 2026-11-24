<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Не авторизован']);
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=familylavka;charset=utf8mb4',
        'root', '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка БД']);
    exit;
}

try {
    // Сначала проверим, какие колонки есть
    $cols = $pdo->query("SHOW COLUMNS FROM products")->fetchAll(PDO::FETCH_COLUMN);
    $selectCols = ['id', 'name', 'price', 'image', 'description', 'category'];
    $validCols = array_intersect($selectCols, $cols);
    if (empty($validCols)) $validCols = ['*'];

    $sql = "SELECT " . implode(', ', $validCols) . " FROM products ORDER BY id DESC";
    $rows = $pdo->query($sql)->fetchAll();

    $products = [];
    foreach ($rows as $r) {
        $products[] = [
            'id'          => (int)$r['id'],
            'name'        => $r['name'] ?? '',
            'price'       => (float)($r['price'] ?? 0),
            'image'       => $r['image'] ?? '',
            'description' => $r['description'] ?? '',
            'category'    => $r['category'] ?? 'other'
        ];
    }

    echo json_encode(['success' => true, 'products' => $products], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()]);
}
?>
