<?php
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=familylavka;charset=utf8mb4',
        'root',
        '',
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );

    $query = isset($_GET['q']) ? trim($_GET['q']) : '';
    $type = isset($_GET['type']) ? trim($_GET['type']) : '';

    if ($query !== '' || $type !== '') {
        $whereParts = [];
        $params = [];

        if ($query !== '') {
            $whereParts[] = 'address LIKE :q';
            $params[':q'] = '%' . $query . '%';
        }

        if ($type !== '') {
            $whereParts[] = 'delivery_type = :type';
            $params[':type'] = $type;
        }

        $sql = 'SELECT id, address, lat, lon, delivery_type FROM delivery_points';
        if (!empty($whereParts)) {
            $sql .= ' WHERE ' . implode(' AND ', $whereParts);
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();
    } else {
        $stmt = $pdo->query('SELECT id, address, lat, lon, delivery_type FROM delivery_points');
        $data = $stmt->fetchAll();
    }

    echo json_encode($data, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Ошибка базы данных',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
