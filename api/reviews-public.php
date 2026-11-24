<?php
// api/reviews-public.php — публичные отзывы для витрины (Quick View)
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$dbPath = __DIR__ . '/../db.php';
if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ошибка сервера: db.php не найден']);
    exit;
}

try {
    require $dbPath; // подключение к БД из твоего файла
} catch (Throwable $e) {
    error_log('reviews-public require error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ошибка подключения к БД']);
    exit;
}

// Получаем product_id из GET-параметра
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

if ($product_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Не указан ID товара',
        'reviews' => [],
        'total_reviews' => 0,
        'avg_rating' => 0
    ]);
    exit;
}

try {
    // Только одобренные отзывы (is_approved = 1)
    $stmt = $pdo->prepare("
        SELECT id, user_name, rating, comment, created_at
        FROM reviews
        WHERE product_id = ? AND is_approved = 1
        ORDER BY created_at DESC
    ");
    $stmt->execute([$product_id]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Считаем средний рейтинг
    $totalReviews = count($reviews);
    $avgRating = 0;
    if ($totalReviews > 0) {
        $sum = 0;
        foreach ($reviews as $r) {
            $sum += (int)($r['rating'] ?? 0);
        }
        $avgRating = round($sum / $totalReviews, 1);
    }

    echo json_encode([
        'success' => true,
        'reviews' => $reviews,
        'total_reviews' => $totalReviews,
        'avg_rating' => $avgRating
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('DB Query Error (reviews-public): ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ошибка получения отзывов']);
}
?>
