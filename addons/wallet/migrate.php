<?php
// php addons/wallet/migrate.php [--apply]; dry run by default, idempotent inserts.
$root = dirname(__DIR__, 2);
$db = parse_ini_file($root . '/.env', true)['database'];
$host = getenv('DATABASE_HOSTNAME') ?: $db['hostname'];
$name = getenv('DATABASE_DATABASE') ?: $db['database'];
$user = getenv('DATABASE_USERNAME') ?: $db['username'];
$password = getenv('DATABASE_PASSWORD') ?: $db['password'];
$port = getenv('DATABASE_HOSTPORT') ?: $db['hostport'];
$prefix = $db['prefix'];
$pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$apply = in_array('--apply', $argv, true);
$count = $pdo->query('SELECT COUNT(*) FROM `' . $prefix . 'user`')->fetchColumn();
$logCount = $pdo->query('SELECT COUNT(*) FROM `' . $prefix . 'user_score_log`')->fetchColumn();
echo "Legacy: $count users, $logCount score logs.\n";
if (!$apply) { echo "Dry run. Pass --apply to copy.\n"; exit; }
foreach (explode(';', str_replace('__PREFIX__', $prefix, file_get_contents(__DIR__ . '/install.sql'))) as $sql) if (trim($sql)) $pdo->exec($sql);
$accounts = $pdo->query('SELECT id,score FROM `' . $prefix . 'user` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$insertAccount = $pdo->prepare('INSERT INTO `' . $prefix . 'wallet_account` (user_id,currency,balance,createtime,updatetime) VALUES (? ,"coin", ?, ?, ?) ON DUPLICATE KEY UPDATE user_id=user_id');
$selectLogs = $pdo->prepare('SELECT id,score,`before`,`after`,memo,createtime FROM `' . $prefix . 'user_score_log` WHERE user_id=? ORDER BY id');
$insertLog = $pdo->prepare('INSERT INTO `' . $prefix . 'wallet_ledger` (user_id,currency,amount,`before`,`after`,type,source_type,source_id,memo,createtime) VALUES (?,"coin",?,?,?,?,"legacy_score",?,?,?) ON DUPLICATE KEY UPDATE id=id');
$copied = 0;
foreach ($accounts as $account) {
    $pdo->beginTransaction();
    try {
        $id = (int)$account['id'];
        $insertAccount->execute([$id, (int)$account['score'], time(), time()]);
        $selectLogs->execute([$id]);
        foreach ($selectLogs->fetchAll(PDO::FETCH_ASSOC) as $log) {
            $type = $log['memo'] === '每日签到' ? 'checkin' : ($log['memo'] === '观看视频广告' ? 'ad_reward' : ($log['memo'] === '虚拟定位' ? 'virtual_location' : 'legacy'));
            $insertLog->execute([$id, (int)$log['score'], (int)$log['before'], (int)$log['after'], $type, (int)$log['id'], $log['memo'], (int)$log['createtime']]);
            if ($insertLog->rowCount() === 1) $copied++;
        }
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
}
$mismatch = $pdo->query('SELECT COUNT(*) FROM `' . $prefix . 'user` u LEFT JOIN `' . $prefix . 'wallet_account` w ON w.user_id=u.id AND w.currency="coin" WHERE w.user_id IS NULL OR w.balance<>u.score')->fetchColumn();
$missing = $pdo->query('SELECT COUNT(*) FROM `' . $prefix . 'user_score_log` s JOIN `' . $prefix . 'user` u ON u.id=s.user_id LEFT JOIN `' . $prefix . 'wallet_ledger` l ON l.source_type="legacy_score" AND l.source_id=s.id WHERE l.id IS NULL')->fetchColumn();
$orphans = $pdo->query('SELECT COUNT(*) FROM `' . $prefix . 'user_score_log` s LEFT JOIN `' . $prefix . 'user` u ON u.id=s.user_id WHERE u.id IS NULL')->fetchColumn();
echo "Copied $copied new logs; balance mismatches=$mismatch, missing existing-user logs=$missing, deleted-user logs=$orphans.\n";
if ($mismatch || $missing) exit(1);
