<?php
// Настройки email-уведомлений модуля "Задачи по отделам".
//
// На локальном XAMPP почта обычно не настроена, поэтому по умолчанию
// MAIL_ENABLED = false — уведомления сохраняются только внутри платформы
// (таблица notifications), email не отправляется.
//
// Чтобы включить реальную отправку писем:
//   1) настройте sendmail_path в php.ini (раздел [mail function])
//      или подключите SMTP-библиотеку (например, PHPMailer);
//   2) поставьте MAIL_ENABLED в true;
//   3) укажите корректный адрес отправителя в MAIL_FROM.

if (!defined('MAIL_ENABLED')) {
    define('MAIL_ENABLED', false);
}
if (!defined('MAIL_FROM')) {
    define('MAIL_FROM', 'noreply@portus-logistics.local');
}
