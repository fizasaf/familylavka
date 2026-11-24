<?php
header('Content-Type: text/html; charset=utf-8');

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

// Получаем заказы
$stmt = $pdo->query("SELECT * FROM orders ORDER BY id DESC");
$orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>DEBUG: Админка заказов</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background: #f5f5f5; }
        .order { border: 1px solid #ccc; padding: 15px; margin-bottom: 15px; background: white; }
        .row { margin: 6px 0; }
        .label { font-weight: bold; color: #555; }
        .amount { color: #27ae60; font-weight: bold; font-size: 1.1em; }
    </style>
</head>
<body>
<h1>DEBUG: Заказы (проверка вывода)</h1>

<?php if (empty($orders)): ?>
    <p>Заказов нет.</p>
<?php else: ?>
    <?php foreach ($orders as $o): ?>
        <div class="order">
            <div class="row"><span class="label">ID:</span> <?= $o['id'] ?></div>
            <div class="row"><span class="label">Статус:</span> <?= htmlspecialchars($o['status']) ?></div>
            <div class="row"><span class="label">Телефон:</span> <?= htmlspecialchars($o['phone']) ?></div>
            <div class="row"><span class="label">Адрес:</span> <?= htmlspecialchars($o['address'] ?? '—') ?></div>

            <!-- ВОТ ЗДЕСЬ ГЛАВНОЕ: ПРАВИЛЬНЫЙ ВЫВОД СУММЫ -->
            <div class="row">
                <span class="label">Сумма (total_amount):</span>
                <?php
                $amount = $o['total_amount'] ?? 0;
                if ($amount === null || $amount == '') {
                    echo '<span class="amount" style="color:#e74c3c;">NULL / пусто</span>';
                } else {
                    echo '<span class="amount">' . number_format((float)$amount, 2, ',', ' ') . ' ₽</span>';
                }
                ?>
            </div>

            <div class="row"><span class="label">items_json (сырое):</span></div>
            <pre style="margin: 8px 0 0 120px; border: 1px dashed #ccc; padding: 8px; font-size:0.9em;">
<?= htmlspecialchars(json_encode(json_decode($o['items_json'], true), JSON_PRETTY_PRINT) ?? 'пусто') ?>
            </pre>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
