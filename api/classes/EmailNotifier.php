<?php
require_once __DIR__ . '/../config/mail.php';

class EmailNotifier
{
    /**
     * Отправка email-уведомления через встроенную функцию mail().
     *
     * Вызов никогда не должен ломать основной запрос API — все ошибки
     * гасятся и пишутся в лог. Если MAIL_ENABLED = false (по умолчанию
     * на локальном окружении без настроенного SMTP), отправка просто
     * пропускается, а уведомление всё равно сохраняется в таблице
     * notifications и видно внутри платформы.
     */
    public static function send($to, $subject, $message)
    {
        if (empty($to) || !MAIL_ENABLED) {
            return false;
        }

        $headers = 'From: ' . MAIL_FROM . "\r\n" .
                   'Content-Type: text/plain; charset=utf-8' . "\r\n";

        try {
            return @mail($to, $subject, $message, $headers);
        } catch (\Throwable $e) {
            error_log('EmailNotifier: ' . $e->getMessage());
            return false;
        }
    }
}
