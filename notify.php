<?php
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendEmailNotification($toEmail, $orderId, $pickupAddress, $customerName = '') {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.yandex.ru';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'maksimkublenko@yandex.ru';
        $mail->Password   = 'uvnudjqngovpaybu';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom('maksimkublenko@yandex.ru', 'FamilyLavka');
        $mail->addAddress($toEmail);

        $mail->isHTML(true);
        $mail->Subject = "Заказ #{$orderId} готов к выдаче — FamilyLavka";

        $greeting = $customerName ? "Здравствуйте, {$customerName}!" : 'Здравствуйте!';

        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #2c3e50;'>{$greeting}</h2>
                <p>Ваш заказ <b>№{$orderId}</b> готов к выдаче!</p>
                <div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0;'>
                    <p style='margin: 5px 0;'><b>Адрес пункта выдачи:</b><br>{$pickupAddress}</p>
                </div>
                <p>При себе достаточно назвать номер заказа: <b>#{$orderId}</b></p>
                <p style='color: #7f8c8d; font-size: 0.9rem;'>С уважением, команда FamilyLavka</p>
            </div>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("[FamilyLavka] Email Error for order #{$orderId}: " . $mail->ErrorInfo);
        return false;
    }
}

function sendSmsNotification($phone, $orderId, $pickupAddress) {
    return false;
}

function notifyCustomerOrderReady($orderId, $customerEmail, $customerPhone, $pickupAddress, $customerName = '') {
    $results = [];

    if (!empty($customerEmail)) {
        $results['email'] = sendEmailNotification($customerEmail, $orderId, $pickupAddress, $customerName);
    } else {
        $results['email'] = false;
    }

    if (!empty($customerPhone)) {
        $results['sms'] = sendSmsNotification($customerPhone, $orderId, $pickupAddress);
    } else {
        $results['sms'] = false;
    }

    return $results;
}
?>