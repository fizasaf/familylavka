    <?php
    // admin.php
    header('Content-Type: text/html; charset=utf-8');

    // Подключение к БД (как в api/save-order.php)
    $host = 'localhost';
    $db   = 'familylavka';
    $user = 'root';
    $pass = '';
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (\PDOException $e) {
        die("Ошибка подключения к БД: " . $e->getMessage());
    }

    // Получаем все заказы
    $stmt = $pdo->query("SELECT * FROM orders ORDER BY created_at DESC");
    $orders = $stmt->fetchAll();
    ?>

    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <title>Админ-панель FamilyLavka</title>
        <style>
            body { font-family: sans-serif; padding: 20px; background: #f4f4f9; }
            h1 { color: #333; }
            .order-card {
                background: white;
                border: 1px solid #ddd;
                padding: 15px;
                margin-bottom: 15px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            .order-header { display: flex; justify-content: space-between; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
            .total { font-weight: bold; color: #27ae60; font-size: 1.2em; }
            .items-list { list-style: none; padding: 0; }
            .items-list li { padding: 4px 0; border-bottom: 1px dashed #eee; }
            .status-new { color: #d35400; font-weight: bold; }
        </style>
    </head>
    <body>

    <h1>Заказы (Админ)</h1>

    <?php if (count($orders) === 0): ?>
        <p>Заказов пока нет.</p>
    <?php else: ?>
        <?php foreach ($orders as $order): ?>
            <div class="order-card">
                <div class="order-header">
                    <span>#<?= $order['id'] ?> | Статус: <span class="status-<?= strtolower($order['status']) ?>"><?= $order['status'] ?></span></span>
                    <!-- ВОТ ЗДЕСЬ ПРАВИЛЬНО БЕРЕМ СУММУ -->
                    <span class="total">Сумма: <?= number_format($order['total_amount'], 2, ',', ' ') ?> ₽</span>
                </div>
                <p><strong>Телефон:</strong> <?= htmlspecialchars($order['phone']) ?></p>
                <p><strong>Адрес:</strong> <?= htmlspecialchars($order['address'] ?? 'Не указан') ?></p>
                <p><strong>Доставка:</strong> <?= htmlspecialchars($order['delivery_type']) ?></p>

                <h4>Состав заказа:</h4>
                <ul class="items-list">
                    <?php
                    // ВОТ ЗДЕСЬ МЫ РАСШИФРОВЫВАЕМ JSON С ТОВАРАМИ
                    $items = json_decode($order['items_json'], true);

                    if (is_array($items) && count($items) > 0) {
                        foreach ($items as $item) {
                            // Выводим имя и количество. Если в JSON нет поля name, пробуем item.name
                            $name = $item['name'] ?? ($item['product_name'] ?? 'Товар без названия');
                            $qty = $item['qty'] ?? 1;
                            $price = $item['price'] ?? 0;
                            echo "<li>{$name} × {$qty} шт. (по {$price} ₽)</li>";
                        }
                    } else {
                        echo "<li>Список товаров пуст или поврежден</li>";
                    }
                    ?>
                </ul>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    </body>
    </html>
