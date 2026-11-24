<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

if ($product_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Не указан ID товара',
        'total_reviews' => 0,
        'avg_rating' => 0
    ]);
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=familylavka;charset=utf8mb4',
        'root', '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка БД',
        'total_reviews' => 0,
        'avg_rating' => 0
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, user_name, rating, comment, created_at
        FROM reviews
        WHERE product_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$product_id]);
    $rawReviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalReviews = count($rawReviews);
    $avgRating = 0;

    if ($totalReviews > 0) {
        $sum = 0;
        foreach ($rawReviews as $r) {
            $sum += (int)($r['rating'] ?? 0);
        }
        $avgRating = round($sum / $totalReviews, 1);
    }

    $reviews = array_map(function($r) {
        return [
            'id'          => (int)($r['id'] ?? 0),
            'product_id'  => $product_id,
            'user_name'   => (string)($r['user_name'] ?? 'Гость'),
            'rating'      => (int)($r['rating'] ?? 0),
            'comment'     => (string)($r['comment'] ?? ''),
            'created_at'  => (string)($r['created_at'] ?? ''),
        ];
    }, $rawReviews);

    echo json_encode([
        'success'       => true,
        'reviews'       => $reviews,
        'total_reviews' => $totalReviews,
        'avg_rating'    => $avgRating
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка запроса',
        'total_reviews' => 0,
        'avg_rating' => 0
    ]);
    exit;
}
?>
