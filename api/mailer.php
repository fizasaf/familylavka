<?php
// api/mailer.php — универсальный отправитель через SMTP
function sendViaSMTP($to, $subject, $body, $smtpConfig) {
    $host = $smtpConfig['host'];
    $port = $smtpConfig['port'];
    $username = $smtpConfig['username'];
    $password = $smtpConfig['password'];

    $headers = "From: {$smtpConfig['from_name']} <{$smtpConfig['from_email']}>\r\n";
    $headers .= "Reply-To: {$smtpConfig['reply_to']}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/plain; charset=UTF-8\r\n";

    // Используем stream_socket_client для SMTP (упрощённо, без сторонних библиотек)
    // Но для надёжности лучше поставить PHPMailer. Ниже — самый простой рабочий пример с mail() + лог,
    // а дальше расскажу, как подключить PHPMailer правильно.

    // Для быстрой проверки на OpenServer оставь mail(), но с правильными адресами.
    return mail($to, $subject, $body, $headers);
}
?>
