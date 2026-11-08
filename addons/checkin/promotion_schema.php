<?php
// php addons/checkin/promotion_schema.php [--apply]
if (PHP_SAPI !== 'cli') exit(1);
if (!in_array('--apply', $argv, true)) { echo "Dry run: create promotion table. Pass --apply to execute.\n"; exit; }
$env = parse_ini_file(dirname(__DIR__, 2) . '/.env', true);
$db = $env['database'];
$prefix = $db['prefix'];
if (!preg_match('/^[a-zA-Z0-9_]*$/', $prefix)) throw new RuntimeException('Invalid table prefix');
$pdo = new PDO('mysql:host=' . (getenv('DATABASE_HOSTNAME') ?: $db['hostname']) . ';port=' . (getenv('DATABASE_HOSTPORT') ?: $db['hostport']) . ';dbname=' . (getenv('DATABASE_DATABASE') ?: $db['database']) . ';charset=utf8mb4', getenv('DATABASE_USERNAME') ?: $db['username'], getenv('DATABASE_PASSWORD') ?: $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$sql = file_get_contents(__DIR__ . '/install.sql');
if (!preg_match('/CREATE TABLE IF NOT EXISTS `__PREFIX__checkin_promotion`.*?;/s', $sql, $match)) throw new RuntimeException('Promotion schema missing');
$pdo->exec(str_replace('__PREFIX__', $prefix, $match[0]));
echo "Promotion table ready.\n";
