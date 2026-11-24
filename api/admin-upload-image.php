<?php
error_reporting(0);
ini_set('display_errors', 0);
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Не авторизован']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if (empty($_FILES)) {
    echo json_encode(['success' => false, 'message' => 'Нет файлов']);
    exit;
}

$uploadDir = __DIR__ . '/../images/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$maxSize = 5 * 1024 * 1024; // 5 МБ

$paths = [];
$errors = [];

// Обрабатываем один или несколько файлов
$files = $_FILES['images'];
$fileCount = is_array($files['name']) ? count($files['name']) : 1;

for ($i = 0; $i < $fileCount; $i++) {
    $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
    $origName = is_array($files['name']) ? $files['name'][$i] : $files['name'];
    $size = is_array($files['size']) ? $files['size'][$i] : $files['size'];
    $type = is_array($files['type']) ? $files['type'][$i] : $files['type'];

    if (!in_array($type, $allowed)) {
        $errors[] = $origName . ': недопустимый формат';
        continue;
    }
    if ($size > $maxSize) {
        $errors[] = $origName . ': больше 5 МБ';
        continue;
    }

    // Генерируем безопасное имя
    $ext = pathinfo($origName, PATHINFO_EXTENSION);
    $ext = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $ext));
    if (!$ext) $ext = 'jpg';
    $newName = uniqid('img_', true) . '.' . $ext;
    $destPath = $uploadDir . $newName;

    if (move_uploaded_file($tmpName, $destPath)) {
        $paths[] = '/images/' . $newName;
    } else {
        $errors[] = $origName . ': ошибка сохранения';
    }
}

echo json_encode([
    'success' => count($paths) > 0,
    'paths' => $paths,
    'errors' => $errors
], JSON_UNESCAPED_UNICODE);
?>
