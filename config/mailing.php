<?php
$phpMailerPath = __DIR__ . '/../vendor/phpmailer/phpmailer/src';
foreach (['Exception.php', 'PHPMailer.php', 'SMTP.php'] as $phpMailerFile) {
    $filePath = $phpMailerPath . '/' . $phpMailerFile;
    if (!is_file($filePath)) {
        throw new RuntimeException('PHPMailer files are missing from vendor/phpmailer/phpmailer/src.');
    }
    require_once $filePath;
}

use PHPMailer\PHPMailer\PHPMailer;

define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', (int) (getenv('SMTP_PORT') ?: 587));
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: '');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '');
define('SMTP_TIMEOUT', (int) (getenv('SMTP_TIMEOUT') ?: 8));

define('MAIL_FROM_EMAIL', getenv('MAIL_FROM_EMAIL') ?: SMTP_USERNAME);
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'Instagram Clone No Reply');
define('NO_REPLY_EMAIL', getenv('NO_REPLY_EMAIL') ?: MAIL_FROM_EMAIL);
define('NO_REPLY_NAME', getenv('NO_REPLY_NAME') ?: 'No Reply');
define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: SMTP_USERNAME);
define('ADMIN_NAME', getenv('ADMIN_NAME') ?: 'Admin');

function sendMail(string $toEmail, string $toName, string $subject, string $body, ?string $replyToEmail = null, ?string $replyToName = null): bool {
    if (SMTP_USERNAME === '' || SMTP_PASSWORD === '' || ADMIN_EMAIL === '') {
        error_log('SMTP is not configured. Set SMTP_USERNAME, SMTP_PASSWORD and ADMIN_EMAIL environment variables.');
        return false;
    }

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->Timeout = SMTP_TIMEOUT;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->addReplyTo($replyToEmail ?: NO_REPLY_EMAIL, $replyToName ?: NO_REPLY_NAME);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body)));

        return $mail->send();
    } catch (Throwable $e) {
        error_log('SMTP mail error: ' . $e->getMessage());
        return false;
    }
}
?>
