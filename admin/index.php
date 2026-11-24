<?php
// Простая проверка на админа (для теста можно убрать или усложнить)
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die('Доступ запрещён');
}

require_once '../db.php'; // Подключаем конфиг БД или создаём PDO здесь

$host = 'localhost'; $db = 'familylavka'; $user = 'root'; $pass = '';
$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

// Получаем заказы
$stmt = $pdo->query("SELECT * FROM orders ORDER BY created_at DESC");
$orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Админ: Заказы — FamilyLavka</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background: #f5f6fa; }
        h1 { color: #2c3e50; }
        table { width: 100%; border-collapse: collapse; background: white; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        th, td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #34495e; color: white; }
        .status-new { color: #d35400; font-weight: bold; }
        .status-processing { color: #f1c40f; }
        .status-shipped { color: #2ecc71; }
        .items-preview { font-size: 0.85rem; color: #7f8c8d; }
    </style>
</head>
<body>
    <h1>Заказы — FamilyLavka</h1>
    <a href="../index.html" style="color:#2c3e50;">← Вернуться на сайт</a>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Дата</th>
                <th>Телефон</th>
                <th>Адрес / ПВЗ</th>
                <th>Доставка</th>
                <th>Сумма</th>
                <th>Состав</th>
                <th>Статус</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $o): ?>
            <tr>
                <td><?= htmlspecialchars($o['id']) ?></td>
                <td><?= htmlspecialchars($o['created_at']) ?></td>
                <td><?= htmlspecialchars($o['phone']) ?></td>
                <td class="items-preview"><?= nl2br(htmlspecialchars($o['address'])) ?></td>
                <td><?= htmlspecialchars($o['delivery_type']) ?></td>
                <td><?= number_format($o['total_amount'], 2, ',', ' ') ?> ₽</td>
                <td class="items-preview">
                    <?php
                    $items = json_decode($o['items_json'], true);
                    if (is_array($items)) {
                        foreach ($items as $i) {
                            echo htmlspecialchars($i['name']) . ' ×' . $i['qty'] . '<br>';
                        }
                    } else {
                        echo 'Ошибка данных';
                    }
                    ?>
                </td>
                <td>
                    <select onchange="updateStatus(<?= $o['id'] ?>, this.value)">
                        <option value="new" <?= $o['status'] === 'new' ? 'selected' : '' ?>>Новый</option>
                        <option value="processing" <?= $o['status'] === 'processing' ? 'selected' : '' ?>>В обработке</option>
                        <option value="shipped" <?= $o['status'] === 'shipped' ? 'selected' : '' ?>>Отправлен</option>
                        <option value="completed" <?= $o['status'] === 'completed' ? 'selected' : '' ?>>Выполнен</option>
                        <option value="cancelled" <?= $o['status'] === 'cancelled' ? 'selected' : '' ?>>Отменён</option>
                    </select>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <script>
        async function updateStatus(id, status) {
            const res = await fetch('/admin/update-status.php', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({id, status})
            });
            const data = await res.json();
            if (!data.success) alert('Ошибка обновления статуса');
        }
    </script>
</body>
</html>
