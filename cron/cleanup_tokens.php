<?php
require_once __DIR__ . '/../api/config/env.php';
require_once __DIR__ . '/../api/config/db.php';

$db = DB::getInstance();

$stmt = $db->prepare('DELETE FROM auth_tokens WHERE expires_at < NOW()');
$stmt->execute();
$deleted = $stmt->rowCount();

$stmt2 = $db->prepare('DELETE FROM fahrer_reg_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)');
$stmt2->execute();

echo date('Y-m-d H:i:s') . " — удалено просроченных токенов: $deleted\n";
