<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Не авторизован']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$id          = (int)($input['id'] ?? 0);
$name        = trim((string)($input['name'] ?? ''));
$price       = (float)($input['price'] ?? 0);
$image       = trim((string)($input['image'] ?? ''));
$description = trim((string)($input['description'] ?? ''));
$category    = trim((string)($input['category'] ?? 'other'));

if (!$name || $price <= 0) {
    echo json_encode(['success' => false, 'message' => 'Название и цена обязательны']);
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
    if ($id > 0) {
        // Редактирование
        $stmt = $pdo->prepare("
            UPDATE products SET name = :name, price = :price, image = :image,
            description = :description, category = :category WHERE id = :id
        ");
        $stmt->execute([
            ':name' => $name, ':price' => $price, ':image' => $image,
            ':description' => $description, ':category' => $category, ':id' => $id
        ]);
        echo json_encode(['success' => true, 'action' => 'updated'], JSON_UNESCAPED_UNICODE);
    } else {
        // Создание
        $stmt = $pdo->prepare("
            INSERT INTO products (name, price, image, description, category)
            VALUES (:name, :price, :image, :description, :category)
        ");
        $stmt->execute([
            ':name' => $name, ':price' => $price, ':image' => $image,
            ':description' => $description, ':category' => $category
        ]);
        echo json_encode(['success' => true, 'action' => 'created', 'id' => (int)$pdo->lastInsertId()], JSON_UNESCAPED_UNICODE);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()]);
}
?>
