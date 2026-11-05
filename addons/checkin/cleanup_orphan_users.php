<?php
// Preview by default. --apply removes only relations whose member no longer exists.
if (PHP_SAPI !== 'cli') exit(1);
$env = parse_ini_file(dirname(__DIR__, 2) . '/.env', true);
$db = $env['database'];
$prefix = $db['prefix'];
if (!preg_match('/^[a-zA-Z0-9_]*$/', $prefix)) throw new RuntimeException('Invalid table prefix');
$pdo = new PDO('mysql:host=' . (getenv('DATABASE_HOSTNAME') ?: $db['hostname']) . ';port=' . (getenv('DATABASE_HOSTPORT') ?: $db['hostport']) . ';dbname=' . (getenv('DATABASE_DATABASE') ?: $db['database']) . ';charset=utf8mb4', getenv('DATABASE_USERNAME') ?: $db['username'], getenv('DATABASE_PASSWORD') ?: $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$count = $pdo->query("SELECT COUNT(*) FROM `{$prefix}checkin_user` c LEFT JOIN `{$prefix}user` u ON u.id=c.user_id WHERE u.id IS NULL")->fetchColumn();
echo "Orphan checkin relations: {$count}.\n";
if (!in_array('--apply', $argv, true)) { echo "Dry run. Pass --apply to remove orphan relations.\n"; exit; }
$removed = $pdo->exec("DELETE c FROM `{$prefix}checkin_user` c LEFT JOIN `{$prefix}user` u ON u.id=c.user_id WHERE u.id IS NULL");
echo "Removed orphan relations: {$removed}.\n";
