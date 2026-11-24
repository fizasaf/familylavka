<?php
require_once 'config.php';
require_once 'notify.php';

$testOrderId = 1;
$testEmail = 'familylavka.test@yandex.ru';
$testPhone = '+79990000000';
$testAddress = 'г. Москва, ул. Тестовая, д. 1';
$testName = 'Тестовый Клиент';

echo "<h3>Запуск теста уведомлений...</h3>";

$res = notifyCustomerOrderReady($testOrderId, $testEmail, $testPhone, $testAddress, $testName);

echo "<pre>";
print_r($res);
echo "</pre>";

if ($res['email']) {
    echo "<h3 style='color:green'>Письмо отправлено!</h3>";
    echo "<p>Проверь папку Входящие и Спам на почте: {$testEmail}</p>";
} else {
    echo "<h3 style='color:red'>Ошибка отправки письма.</h3>";
    echo "<p>Смотри файл error.log в корне сайта или в логах OpenServer.</p>";
}
?>