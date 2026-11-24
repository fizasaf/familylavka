<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$code = trim(strtoupper((string)($input['code'] ?? '')));
$cartTotal = (float)($input['cart_total'] ?? 0);
$userId = isset($input['user_id']) ? (int)$input['user_id'] : null;
$email = isset($input['email']) ? trim((string)$input['email']) : null;

if (!$code) {
    echo json_encode(['success' => false, 'message' => 'Введите промокод']);
    exit;
}
if ($cartTotal <= 0) {
    echo json_encode(['success' => false, 'message' => 'Корзина пуста']);
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
    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$code]);
    $coupon = $stmt->fetch();

    if (!$coupon) {
        echo json_encode(['success' => false, 'message' => 'Промокод не найден или неактивен']);
        exit;
    }

    // Проверка срока действия
    $now = date('Y-m-d H:i:s');
    if ($coupon['valid_from'] && $now < $coupon['valid_from']) {
        echo json_encode(['success' => false, 'message' => 'Промокод ещё не действует']);
        exit;
    }
    if ($coupon['valid_until'] && $now > $coupon['valid_until']) {
        echo json_encode(['success' => false, 'message' => 'Срок действия промокода истёк']);
        exit;
    }

    // Проверка лимита использований
    if ($coupon['usage_limit'] !== null && $coupon['times_used'] >= $coupon['usage_limit']) {
        echo json_encode(['success' => false, 'message' => 'Лимит использований промокода исчерпан']);
        exit;
    }

    // Проверка: использовал ли уже этот человек промокод
    if ($userId || $email) {
        $checkParams = [];
        $checkConditions = [];
        if ($userId) {
            $checkConditions[] = 'user_id = ?';
            $checkParams[] = $userId;
        }
        if ($email) {
            $checkConditions[] = 'email = ?';
            $checkParams[] = $email;
        }
        $checkSql = "SELECT id FROM coupon_usage WHERE coupon_id = ? AND (" . implode(' OR ', $checkConditions) . ") LIMIT 1";
        $checkStmt = $pdo->prepare($checkSql);
        $checkParams = array_merge([$coupon['id']], $checkParams);
        $checkStmt->execute($checkParams);

        if ($checkStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Вы уже использовали этот промокод']);
            exit;
        }
    }

    // Расчёт скидки
    if ($coupon['discount_type'] === 'percent') {
        $discount = $cartTotal * ((float)$coupon['discount_value'] / 100);
    } else {
        $discount = (float)$coupon['discount_value'];
    }

    if ($discount > $cartTotal) $discount = $cartTotal;
    $newTotal = $cartTotal - $discount;

    // Увеличиваем times_used
    $pdo->prepare("UPDATE coupons SET times_used = times_used + 1 WHERE id = ?")
        ->execute([$coupon['id']]);

    // Записываем использование
    $pdo->prepare("INSERT INTO coupon_usage (coupon_id, user_id, email) VALUES (?, ?, ?)")
        ->execute([$coupon['id'], $userId, $email]);

    echo json_encode([
        'success' => true,
        'code'           => $code,
        'discount_type'  => $coupon['discount_type'],
        'discount_value' => (float)$coupon['discount_value'],
        'discount'       => round($discount, 2),
        'new_total'      => round($newTotal, 2),
        'message'        => 'Промокод применён! Скидка: ' . round($discount, 0) . ' ₽'
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()]);
}
?>
