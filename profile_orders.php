<?php
session_start();
require_once 'config.php';

if (!isset(\$_SESSION['user_id'])) {
    die('Вы не авторизованы');
}

$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getStatusBadge(\$status) {
    \$map = [
        'new' => ['text' => 'Принят', 'color' => '#6c757d'],
        'in_progress' => ['text' => 'В работе', 'color' => '#ffc107'],
        'in_delivery' => ['text' => 'Передан в доставку', 'color' => '#17a2b8'],
        'ready_for_pickup' => ['text' => 'Готов к выдаче', 'color' => '#28a745'],
        'cancelled' => ['text' => 'Отменён', 'color' => '#dc3545']
    ];
    return $map[$status] ?? ['text' => \$status, 'color' => '#6c757d'];
}
?>
<h2>История заказов</h2>
<?php if (empty(\$orders)): ?>
    <p>У вас пока нет заказов.</p>
<?php else: ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
        <?php foreach ($orders as $order): ?>
            <div style="border: 1px solid #ddd; padding: 15px; border-radius: 8px;">
                <h3>Заказ #<?= \$order['id'] ?></h3>
                <p><strong>Дата:</strong> <?= \$order['created_at'] ?></p>
                <p><strong>Сумма:</strong> <?= number_format(\$order['total_amount'], 0, '.', ' ') ?> ₽</p>
                <p><strong>Телефон:</strong> <?= htmlspecialchars(\$order['phone']) ?></p>
                <p><strong>Тип доставки:</strong> <?= htmlspecialchars(\$order['delivery_type']) ?></p>
                <div style="margin: 15px 0; padding: 10px; background: #f8f9fa; border-radius: 4px;">
                    <strong>Статус:</strong><br>
                    <span class="badge" style="padding: 4px 8px; border-radius: 4px; color: white; background: <?= getStatusBadge(\$order['status'])['color'] ?>">
                        <?= getStatusBadge(\$order['status'])['text'] ?>
                    </span>
                </div>
                <?php if (\$order['status'] === 'ready_for_pickup'): ?>
                    <p style="color: green; font-weight: bold;">
                        📍 Адрес пункта выдачи: <?= htmlspecialchars(\$order['address']) ?>
                    </p>
                <?php endif; ?>
                <details style="margin-top:10px;">
                    <summary style="cursor:pointer; color:#007bff;">Показать состав заказа</summary>
                    <pre style="font-size:0.85em; color:#555; background:#f5f5f5; padding:8px; border-radius:4px;"><?= htmlspecialchars(\$order['items_json']) ?></pre>
                </details>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
