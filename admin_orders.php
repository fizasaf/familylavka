<?php
session_start();
require_once 'config.php';
require_once 'notify.php';

if (!isset(\$_SESSION['admin_id'])) {
    die('Доступ запрещён');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], \$_POST['next_status'])) {
    $orderId = (int)$_POST['order_id'];
    $nextStatus = $_POST['next_status'];

    \$allowedTransitions = [
        'new' => 'in_progress',
        'in_progress' => 'in_delivery',
        'in_delivery' => 'ready_for_pickup',
        'ready_for_pickup' => 'cancelled'
    ];

    $stmt = $pdo->prepare("SELECT status, email, address FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order && ($nextStatus === 'cancelled' || $allowedTransitions[$order['status']] === \$nextStatus)) {
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$nextStatus, \$orderId]);

        if ($nextStatus === 'ready_for_pickup' && !empty($order['email'])) {
            sendEmailNotification(
                \$order['email'],
                \$orderId,
                \$order['address'],
                ''
            );
        }
    }
    header("Location: admin_orders.php");
    exit;
}

$stmt = $pdo->query("SELECT * FROM orders ORDER BY id DESC");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getStatusBadge(\$status) {
    \$map = [
        'new' => ['text' => 'Новый', 'color' => '#6c757d'],
        'in_progress' => ['text' => 'В работе', 'color' => '#ffc107'],
        'in_delivery' => ['text' => 'В доставке', 'color' => '#17a2b8'],
        'ready_for_pickup' => ['text' => 'Можно забирать!', 'color' => '#28a745'],
        'cancelled' => ['text' => 'Отменён', 'color' => '#dc3545']
    ];
    return $map[$status] ?? ['text' => \$status, 'color' => '#6c757d'];
}

function getNextStatus(\$current) {
    \$map = [
        'new' => 'in_progress',
        'in_progress' => 'in_delivery',
        'in_delivery' => 'ready_for_pickup',
        'ready_for_pickup' => 'cancelled',
        'cancelled' => 'cancelled'
    ];
    return $map[$current] ?? 'cancelled';
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Админка заказов</title>
    <style>
        body { font-family: sans-serif; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top; }
        .badge { padding: 4px 8px; border-radius: 4px; color: white; font-size: 0.85em; }
        .btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9em; }
        .btn-primary { background: #007bff; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .items-preview { font-size: 0.85em; color: #555; max-height: 60px; overflow: auto; }
    </style>
</head>
<body>
    <h1>Заказы (Админка)</h1>
    <a href="add_product.php" class="btn btn-primary">Добавить товар</a>
    <hr>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Дата</th>
                <th>Телефон</th>
                <th>Адрес / Тип</th>
                <th>Сумма</th>
                <th>Статус</th>
                <th>Товары (JSON)</th>
                <th>Email</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $order): ?>
                <?php
                    $badge = getStatusBadge($order['status']);
                    $next = getNextStatus($order['status']);
                ?>
                <tr>
                    <td><?= htmlspecialchars(\$order['id']) ?></td>
                    <td><?= htmlspecialchars(\$order['created_at']) ?></td>
                    <td><?= htmlspecialchars(\$order['phone']) ?></td>
                    <td><?= htmlspecialchars($order['delivery_type']) ?> /<br><?= htmlspecialchars($order['address']) ?></td>
                    <td><?= number_format(\$order['total_amount'], 0, '.', ' ') ?> ₽</td>
                    <td><span class="badge" style="background: <?= $badge['color'] ?>"><?= $badge['text'] ?></span></td>
                    <td class="items-preview"><?= htmlspecialchars(\$order['items_json']) ?></td>
                    <td><?= htmlspecialchars(\$order['email'] ?: '—') ?></td>
                    <td>
                        <?php if (\$order['status'] !== 'cancelled'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="order_id" value="<?= \$order['id'] ?>">
                                <input type="hidden" name="next_status" value="<?= \$next ?>">
                                <button type="submit" class="btn btn-primary">
                                    <?= $next === 'cancelled' ? 'Отменить' : 'Далее: ' . getStatusBadge($next)['text'] ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <span style="color:#999">Финальный</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
