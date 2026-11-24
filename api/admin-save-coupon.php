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

$id            = (int)($input['id'] ?? 0);
$code          = trim(strtoupper((string)($input['code'] ?? '')));
$discountType  = ($input['discount_type'] ?? 'percent') === 'fixed' ? 'fixed' : 'percent';
$discountValue = (float)($input['discount_value'] ?? 0);
$validFrom     = trim((string)($input['valid_from'] ?? ''));
$validUntil    = trim((string)($input['valid_until'] ?? ''));
$usageLimit    = isset($input['usage_limit']) && $input['usage_limit'] !== '' ? (int)$input['usage_limit'] : null;
$isActive      = isset($input['is_active']) ? (int)$input['is_active'] : 1;

if (!$code || $discountValue <= 0) {
    echo json_encode(['success' => false, 'message' => 'Код и размер скидки обязательны']);
    exit;
}
if ($discountType === 'percent' && $discountValue > 100) {
    echo json_encode(['success' => false, 'message' => 'Процент скидки не может быть больше 100']);
    exit;
}

$validFromSql  = $validFrom ? $validFrom : null;
$validUntilSql = $validUntil ? $validUntil : null;

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
        $stmt = $pdo->prepare("
            UPDATE coupons SET code = ?, discount_type = ?, discount_value = ?,
            valid_from = ?, valid_until = ?, usage_limit = ?, is_active = ?
            WHERE id = ?
        ");
        $stmt->execute([$code, $discountType, $discountValue, $validFromSql, $validUntilSql, $usageLimit, $isActive, $id]);
        echo json_encode(['success' => true, 'action' => 'updated'], JSON_UNESCAPED_UNICODE);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO coupons (code, discount_type, discount_value, valid_from, valid_until, usage_limit, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$code, $discountType, $discountValue, $validFromSql, $validUntilSql, $usageLimit, $isActive]);
        echo json_encode(['success' => true, 'action' => 'created', 'id' => (int)$pdo->lastInsertId()], JSON_UNESCAPED_UNICODE);
    }
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        echo json_encode(['success' => false, 'message' => 'Промокод с таким кодом уже существует']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()]);
    }
}
?>
