<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Не авторизован']);
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
    $rows = $pdo->query("SELECT * FROM coupons ORDER BY id DESC")->fetchAll();

    $coupons = [];
    foreach ($rows as $c) {
        $coupons[] = [
            'id'            => (int)$c['id'],
            'code'          => $c['code'],
            'discount_type' => $c['discount_type'],
            'discount_value'=> (float)$c['discount_value'],
            'valid_from'    => $c['valid_from'],
            'valid_until'   => $c['valid_until'],
            'usage_limit'   => $c['usage_limit'] !== null ? (int)$c['usage_limit'] : null,
            'times_used'    => (int)$c['times_used'],
            'is_active'     => (int)$c['is_active'],
            'created_at'    => $c['created_at']
        ];
    }

    echo json_encode(['success' => true, 'coupons' => $coupons], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()]);
}
?>
