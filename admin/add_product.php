<?php
session_start();
require_once __DIR__ . '/config.php';

// Проверка, что админ авторизован
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $category = trim($_POST['category'] ?? '');
    $image_url = trim($_POST['image_url'] ?? '');
    $stock = intval($_POST['stock'] ?? 0);

    if ($name === '' || $price <= 0) {
        $error = 'Название и цена обязательны для заполнения';
    } else {
        $stmt = $pdo->prepare("INSERT INTO products (name, description, price, category, image_url, stock, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        if ($stmt->execute([$name, $description, $price, $category, $image_url, $stock])) {
            $message = 'Товар "' . htmlspecialchars($name) . '" успешно добавлен!';
        } else {
            $error = 'Ошибка при добавлении товара в базу';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Добавить товар — FamilyLavka Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f4f6f8; padding: 20px; }
        .header { background: #2c3e50; color: white; padding: 15px 25px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .header a { color: white; text-decoration: none; font-size: 14px; }
        .header a:hover { text-decoration: underline; }
        .container { max-width: 700px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #34495e; }
        input, textarea, select { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        input:focus, textarea:focus { outline: none; border-color: #3498db; }
        button { background: #3498db; color: white; border: none; padding: 12px 25px; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #2980b9; }
        .message { padding: 12px; border-radius: 5px; margin-bottom: 15px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .nav-links { margin-top: 20px; }
        .nav-links a { color: #3498db; text-decoration: none; margin-right: 15px; }
        .nav-links a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="header">
        <span>FamilyLavka — Админ-панель</span>
        <a href="admin_orders.php">Заказы</a> |
        <a href="add_product.php">Добавить товар</a> |
        <a href="logout.php">Выйти</a>
    </div>

    <div class="container">
        <h1>Добавить новый товар</h1>

        <?php if ($message): ?>
            <div class="message success"><?= $message ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <label for="name">Название товара *</label>
            <input type="text" id="name" name="name" required placeholder="Например: Артишоки маринованные">

            <label for="description">Описание</label>
            <textarea id="description" name="description" rows="4" placeholder="Краткое описание товара"></textarea>

            <label for="price">Цена (руб.) *</label>
            <input type="number" id="price" name="price" step="0.01" min="0" required placeholder="299.00">

            <label for="category">Категория</label>
            <input type="text" id="category" name="category" placeholder="Например: Овощи, Соусы, Снеки">

            <label for="image_url">Ссылка на изображение</label>
            <input type="text" id="image_url" name="image_url" placeholder="/images/product.jpg или полный URL">

            <label for="stock">Количество на складе</label>
            <input type="number" id="stock" name="stock" min="0" value="0">

            <button type="submit">Добавить товар</button>
        </form>

        <div class="nav-links">
            <a href="admin_orders.php">Перейти к заказам</a>
            <a href="add_product.php">Добавить ещё товар</a>
        </div>
    </div>
</body>
</html>