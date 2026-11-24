<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} else {
    $pdo = null;
}

session_start();
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// 1. Сначала пробуем взять action из JSON-тела (для POST-запросов)
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

$action = $input['action'] ?? '';
$product_id = isset($input['product_id']) ? (int)$input['product_id'] : 0;
$quantity = max(1, (int)($input['quantity'] ?? 1));

// 2. Если action пустой — пробуем из GET-параметров (для cart.php?action=get)
if ($action === '') {
    $action = $_GET['action'] ?? '';
    // Для GET-запросов product_id тоже можно брать из URL, если надо
    if ($product_id === 0) {
        $product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
    }
}

try {
    if ($action === 'add') {
        if ($product_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Неверный ID товара']);
            exit;
        }
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id] += $quantity;
        } else {
            $_SESSION['cart'][$product_id] = $quantity;
        }
        echo json_encode(['success' => true, 'message' => 'Товар добавлен в корзину']);
        exit;
    }

    if ($action === 'remove') {
        if ($product_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Неверный ID товара для удаления']);
            exit;
        }
        unset($_SESSION['cart'][$product_id]);
        echo json_encode(['success' => true, 'message' => 'Товар удалён из корзины']);
        exit;
    }

    if ($action === 'clear') {
        $_SESSION['cart'] = [];
        echo json_encode(['success' => true, 'message' => 'Корзина очищена']);
        exit;
    }

    if ($action === 'get') {
        $cartItems = [];
        $total = 0.0;

        foreach ($_SESSION['cart'] as $id => $qty) {
            if (isset($pdo)) {
                $stmt = $pdo->prepare('SELECT id, name, price FROM products WHERE id = ?');
                $stmt->execute([(int)$id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $priceNum = (float)$row['price'];
                    $itemTotal = $priceNum * $qty;
                    $total += $itemTotal;
                    $cartItems[] = [
                        'id' => (int)$row['id'],
                        'name' => $row['name'],
                        'price' => $priceNum,
                        'quantity' => (int)$qty,
                        'total' => $itemTotal
                    ];
                    continue;
                }
            }
            // Заглушка, если товара нет в БД
            $priceNum = 100.00;
            $itemTotal = $priceNum * $qty;
            $total += $itemTotal;
            $cartItems[] = [
                'id' => (int)$id,
                'name' => "Товар (ID: {$id})",
                'price' => $priceNum,
                'quantity' => (int)$qty,
                'total' => $itemTotal
            ];
        }

        echo json_encode([
            'success' => true,
            'items' => $cartItems,
            'total' => (float)$total
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Неизвестное действие']);
} catch (Exception $e) {
    error_log('Cart error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ошибка сервера', 'debug' => $e->getMessage()]);
}
