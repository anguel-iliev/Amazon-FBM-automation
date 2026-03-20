<?php
/**
 * Mailer — изпраща имейли чрез SMTP (Gmail)
 * Без зависимости — чист PHP с fsockopen/TLS.
 * Работи на всеки Apache хостинг.
 */
class Mailer {

    /**
     * Изпраща имейл за потвърждение след покана
     */
    public static function sendInvite(string $toEmail, string $token): bool {
        $link    = APP_URL . '/register/' . $token;
        $subject = 'Покана за AMZ Retail платформата';
        $body    = static::inviteTemplate($toEmail, $link);
        return static::send($toEmail, $subject, $body);
    }

    /**
     * Изпраща имейл за нулиране на парола
     */
    public static function sendPasswordReset(string $toEmail, string $token): bool {
        $link    = APP_URL . '/reset-password/' . $token;
        $subject = 'Нулиране на парола — AMZ Retail';
        $body    = static::resetTemplate($toEmail, $link);
        return static::send($toEmail, $subject, $body);
    }

    /**
     * Основна функция за изпращане
     */
    public static function send(string $to, string $subject, string $htmlBody): bool {
        $host = SMTP_HOST;
        $port = SMTP_PORT;
        $user = SMTP_USER;
        $pass = SMTP_PASS;
        $from = SMTP_FROM ?: $user;
        $name = SMTP_FROM_NAME;

        if (empty($user) || empty($pass)) {
            Logger::error("Mailer: SMTP_USER or SMTP_PASS not configured");
            return false;
        }

        try {
            // Connect
            $socket = fsockopen($host, $port, $errno, $errstr, 10);
            if (!$socket) {
                Logger::error("Mailer: Cannot connect to {$host}:{$port} — {$errstr}");
                return false;
            }

            static::read($socket); // 220 greeting

            // EHLO
            static::write($socket, "EHLO amz-retail.tnsoft.eu");
            static::read($socket);

            // STARTTLS
            static::write($socket, "STARTTLS");
            static::read($socket);

            // Enable TLS
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);

            // EHLO again after TLS
            static::write($socket, "EHLO amz-retail.tnsoft.eu");
            static::read($socket);

            // AUTH LOGIN
            static::write($socket, "AUTH LOGIN");
            static::read($socket);
            static::write($socket, base64_encode($user));
            static::read($socket);
            static::write($socket, base64_encode($pass));
            $authResp = static::read($socket);

            if (strpos($authResp, '235') === false) {
                Logger::error("Mailer: AUTH failed — {$authResp}");
                fclose($socket);
                return false;
            }

            // MAIL FROM
            static::write($socket, "MAIL FROM:<{$from}>");
            static::read($socket);

            // RCPT TO
            static::write($socket, "RCPT TO:<{$to}>");
            static::read($socket);

            // DATA
            static::write($socket, "DATA");
            static::read($socket);

            // Build message
            $boundary = md5(uniqid());
            $date     = date('r');
            $msgId    = '<' . time() . '.' . bin2hex(random_bytes(4)) . '@amz-retail.tnsoft.eu>';

            $message  = "Date: {$date}\r\n";
            $message .= "From: =?UTF-8?B?" . base64_encode($name) . "?= <{$from}>\r\n";
            $message .= "To: {$to}\r\n";
            $message .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
            $message .= "Message-ID: {$msgId}\r\n";
            $message .= "MIME-Version: 1.0\r\n";
            $message .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
            $message .= "\r\n";
            $message .= "--{$boundary}\r\n";
            $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
            $message .= strip_tags($htmlBody) . "\r\n";
            $message .= "--{$boundary}\r\n";
            $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
            $message .= $htmlBody . "\r\n";
            $message .= "--{$boundary}--\r\n";
            $message .= "\r\n.";

            static::write($socket, $message);
            static::read($socket);

            static::write($socket, "QUIT");
            fclose($socket);

            Logger::info("Mailer: Sent '{$subject}' to {$to}");
            return true;

        } catch (Throwable $e) {
            Logger::error("Mailer exception: " . $e->getMessage());
            return false;
        }
    }

    private static function write($socket, string $cmd): void {
        fwrite($socket, $cmd . "\r\n");
    }

    private static function read($socket): string {
        $resp = '';
        while ($line = fgets($socket, 512)) {
            $resp .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        return $resp;
    }

    // ── Email templates ───────────────────────────────────────
    private static function inviteTemplate(string $email, string $link): string {
        return <<<HTML
<!DOCTYPE html>
<html lang="bg">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#0D0F14;font-family:'Helvetica Neue',Arial,sans-serif">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#0D0F14;min-height:100vh">
    <tr><td align="center" style="padding:60px 20px">
      <table width="520" cellpadding="0" cellspacing="0" style="background:#1A1E2A;border:1px solid rgba(255,255,255,0.08);border-radius:8px;overflow:hidden">
        <tr>
          <td style="background:#C9A84C;padding:4px 0;font-size:1px">&nbsp;</td>
        </tr>
        <tr>
          <td style="padding:40px 48px">
            <p style="font-size:22px;font-weight:700;color:#E8E6E1;margin:0 0 6px">AMZ<span style="color:#C9A84C">Retail</span></p>
            <p style="font-size:11px;letter-spacing:0.15em;color:rgba(232,230,225,0.4);text-transform:uppercase;margin:0 0 32px">TN Soft Platform</p>
            <h1 style="font-size:20px;font-weight:700;color:#E8E6E1;margin:0 0 12px">Поканени сте!</h1>
            <p style="font-size:14px;color:rgba(232,230,225,0.65);line-height:1.7;margin:0 0 28px">
              Получихте покана за достъп до AMZ Retail платформата.<br>
              Натиснете бутона за да зададете паролата си и активирате акаунта.
            </p>
            <p style="margin:0 0 32px">
              <a href="{$link}" style="display:inline-block;background:#C9A84C;color:#0D0F14;text-decoration:none;padding:14px 32px;border-radius:4px;font-weight:700;font-size:14px;letter-spacing:0.05em">
                Активирай акаунт →
              </a>
            </p>
            <p style="font-size:12px;color:rgba(232,230,225,0.35);margin:0 0 8px">Или копирай линка:</p>
            <p style="font-size:12px;color:#C9A84C;word-break:break-all;margin:0 0 32px;background:rgba(201,168,76,0.08);padding:10px 14px;border-radius:4px;border:1px solid rgba(201,168,76,0.2)">{$link}</p>
            <p style="font-size:12px;color:rgba(232,230,225,0.3);margin:0">Линкът е валиден 24 часа.</p>
          </td>
        </tr>
        <tr>
          <td style="padding:20px 48px;border-top:1px solid rgba(255,255,255,0.06)">
            <p style="font-size:11px;color:rgba(232,230,225,0.25);margin:0">&copy; TN Soft &middot; AMZ Retail Platform</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }

    private static function resetTemplate(string $email, string $link): string {
        return <<<HTML
<!DOCTYPE html>
<html lang="bg">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#0D0F14;font-family:'Helvetica Neue',Arial,sans-serif">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#0D0F14;min-height:100vh">
    <tr><td align="center" style="padding:60px 20px">
      <table width="520" cellpadding="0" cellspacing="0" style="background:#1A1E2A;border:1px solid rgba(255,255,255,0.08);border-radius:8px;overflow:hidden">
        <tr><td style="background:#C9A84C;padding:4px 0;font-size:1px">&nbsp;</td></tr>
        <tr>
          <td style="padding:40px 48px">
            <p style="font-size:22px;font-weight:700;color:#E8E6E1;margin:0 0 32px">AMZ<span style="color:#C9A84C">Retail</span></p>
            <h1 style="font-size:20px;font-weight:700;color:#E8E6E1;margin:0 0 12px">Нулиране на парола</h1>
            <p style="font-size:14px;color:rgba(232,230,225,0.65);line-height:1.7;margin:0 0 28px">
              Получихме заявка за нулиране на паролата за <strong style="color:#E8E6E1">{$email}</strong>.<br>
              Ако не сте направили тази заявка, игнорирайте имейла.
            </p>
            <p style="margin:0 0 32px">
              <a href="{$link}" style="display:inline-block;background:#C9A84C;color:#0D0F14;text-decoration:none;padding:14px 32px;border-radius:4px;font-weight:700;font-size:14px">
                Задай нова парола →
              </a>
            </p>
            <p style="font-size:12px;color:rgba(232,230,225,0.3);margin:0">Линкът е валиден 24 часа.</p>
          </td>
        </tr>
        <tr>
          <td style="padding:20px 48px;border-top:1px solid rgba(255,255,255,0.06)">
            <p style="font-size:11px;color:rgba(232,230,225,0.25);margin:0">&copy; TN Soft &middot; AMZ Retail Platform</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }
}
