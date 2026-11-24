<?php
header('Content-Type: application/json; charset=utf-8');

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
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB error']);
    exit;
}

try {
    $cols = $pdo->query("SHOW COLUMNS FROM products")->fetchAll(PDO::FETCH_COLUMN);
    $selectCols = ['id', 'name', 'price', 'image', 'description', 'category'];
    $validCols = array_intersect($selectCols, $cols);
    if (empty($validCols)) $validCols = ['*'];

    $sql = "SELECT " . implode(', ', $validCols) . " FROM products ORDER BY id DESC";
    $rows = $pdo->query($sql)->fetchAll();

    $products = [];
    foreach ($rows as $r) {
        $products[] = [
            'id'          => (int)($r['id'] ?? 0),
            'name'        => (string)($r['name'] ?? 'Товар'),
            'price'       => (float)($r['price'] ?? 0),
            'image'       => (string)($r['image'] ?? ''),
            'description' => (string)($r['description'] ?? 'Описание отсутствует.'),
            'category'    => (string)($r['category'] ?? 'other')
        ];
    }

    echo json_encode($products, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Query error']);
    exit;
}
?>
