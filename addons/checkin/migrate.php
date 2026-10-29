<?php
// php addons/checkin/migrate.php [--apply]; creates third-party bindings and daily records.
$root = dirname(__DIR__, 2);
$db = parse_ini_file($root . '/.env', true)['database'];
$pdo = new PDO('mysql:host=' . (getenv('DATABASE_HOSTNAME') ?: $db['hostname']) . ';port=' . (getenv('DATABASE_HOSTPORT') ?: $db['hostport']) . ';dbname=' . (getenv('DATABASE_DATABASE') ?: $db['database']) . ';charset=utf8mb4', getenv('DATABASE_USERNAME') ?: $db['username'], getenv('DATABASE_PASSWORD') ?: $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$p = $db['prefix'];
$users = $pdo->query('SELECT id,wechatopenid,nickname FROM `' . $p . 'user` WHERE wechatopenid IS NOT NULL AND wechatopenid<>""')->fetchAll(PDO::FETCH_ASSOC);
echo count($users) . " old WeChat bindings.\n";
if (!in_array('--apply', $argv, true)) { echo "Dry run. Pass --apply to copy.\n"; exit; }
foreach (explode(';', str_replace('__PREFIX__', $p, file_get_contents(__DIR__ . '/install.sql'))) as $sql) if (trim($sql)) $pdo->exec($sql);
$find = $pdo->prepare('SELECT user_id FROM `' . $p . 'third` WHERE platform="wechat" AND openid=?');
$insert = $pdo->prepare('INSERT INTO `' . $p . 'third` (user_id,platform,apptype,openid,unionid,openname,createtime,updatetime) VALUES (? ,"wechat","miniapp",?,"",?,?,?)');
$daily = $pdo->prepare('INSERT INTO `' . $p . 'checkin_daily` (user_id,check_date,reward,createtime) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE id=id');
foreach ($users as $u) {
    $find->execute([$u['wechatopenid']]);
    $bound = $find->fetchColumn();
    if ($bound !== false && (int)$bound !== (int)$u['id']) throw new RuntimeException('OpenID conflict at user ' . $u['id']);
    if ($bound === false) $insert->execute([$u['id'], $u['wechatopenid'], $u['nickname'], time(), time()]);
}
$logs = $pdo->query('SELECT s.user_id,s.score,s.createtime FROM `' . $p . 'user_score_log` s JOIN `' . $p . 'user` u ON u.id=s.user_id WHERE s.memo="每日签到" ORDER BY s.id');
foreach ($logs as $log) $daily->execute([(int)$log['user_id'], date('Y-m-d', (int)$log['createtime']), (int)$log['score'], (int)$log['createtime']]);
echo "Bindings and daily records copied; legacy data retained.\n";
