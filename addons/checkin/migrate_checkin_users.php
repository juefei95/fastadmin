<?php
// Preview by default. --apply creates the relation table and imports existing new-miniapp bindings.
if (PHP_SAPI !== 'cli') exit(1);
$env = parse_ini_file(dirname(__DIR__, 2) . '/.env', true);
$db = $env['database'];
$prefix = $db['prefix'];
if (!preg_match('/^[a-zA-Z0-9_]*$/', $prefix)) throw new RuntimeException('Invalid table prefix');
$pdo = new PDO('mysql:host=' . (getenv('DATABASE_HOSTNAME') ?: $db['hostname']) . ';port=' . (getenv('DATABASE_HOSTPORT') ?: $db['hostport']) . ';dbname=' . (getenv('DATABASE_DATABASE') ?: $db['database']) . ';charset=utf8mb4', getenv('DATABASE_USERNAME') ?: $db['username'], getenv('DATABASE_PASSWORD') ?: $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$count = $pdo->query("SELECT COUNT(DISTINCT t.user_id) FROM `{$prefix}third` t INNER JOIN `{$prefix}user` u ON u.id=t.user_id WHERE t.platform='wechat' AND t.apptype='miniapp'")->fetchColumn();
echo "Eligible new-miniapp members: {$count}.\n";
if (!in_array('--apply', $argv, true)) { echo "Dry run. Pass --apply to create and populate checkin_user.\n"; exit; }
$pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}checkin_user` (`user_id` int unsigned NOT NULL, `createtime` bigint NOT NULL, PRIMARY KEY (`user_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='打卡用户'");
$stmt = $pdo->prepare("INSERT IGNORE INTO `{$prefix}checkin_user` (`user_id`,`createtime`) SELECT t.user_id, COALESCE(MIN(NULLIF(t.createtime,0)), ?) FROM `{$prefix}third` t INNER JOIN `{$prefix}user` u ON u.id=t.user_id WHERE t.platform='wechat' AND t.apptype='miniapp' GROUP BY t.user_id");
$stmt->execute([time()]);
echo 'New relations inserted: ' . $stmt->rowCount() . ". Existing relations preserved.\n";
