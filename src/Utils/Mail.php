<?php

namespace Utils;

// System
use PDO;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mail
{
    public static function queue(PDO $db, string $to, string $subject, string $body): bool
    {
        try {
            $stmt = $db->prepare("
                INSERT INTO logs_email (recipient, subject, body)
                VALUES (:recipient, :subject, :body)
            ");
            $stmt->bindValue(':recipient', $to);
            $stmt->bindValue(':subject', $subject);
            $stmt->bindValue(':body', $body);
            return $stmt->execute();
        } catch (\Throwable $e) {
            Helper::getLogger()->error("Mail Queue error: " . $e->getMessage());
            return false;
        }
    }


    public static function sendMail(string $to, string $subject, string $body)
    {
        if($_ENV['APP_DEBUG']=='true'){
            return true;
        }

        // ✅ Respond immediately and close connection
        // if (php_sapi_name() !== 'cli') {
        //     ignore_user_abort(true);
        //     if (ob_get_length()) ob_end_flush();
        //     flush();
        //     if (function_exists('fastcgi_finish_request')) {
        //         fastcgi_finish_request();
        //     }
        // }

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $_ENV['SMTP_HOST'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['SMTP_USER'];
            $mail->Password   = $_ENV['SMTP_APP_PASSWORD'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $_ENV['SMTP_PORT'];
            $mail->isHTML(true);
            $mail->CharSet = "UTF-8";
            // $mail->SMTPDebug = 2;

            $mail->setFrom($_ENV['SMTP_USER'], 'V SAFE - Smart Home Solutions');
            $mail->addAddress($to);

            $mail->Subject = $subject;
            $mail->Body    = $body;

            $mail->send();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public static function renderTemplate($templatePath, $variables = [])
    {
        extract($variables);
        ob_start();
        include $templatePath;
        return ob_get_clean();
    }
}
