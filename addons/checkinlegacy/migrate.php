<?php
// Migrate an imported old-site database in place. Preview by default; never deletes data.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__, 2);
$env = parse_ini_file($root . '/.env', true);
$db = $env['database'] ?? [];
$setting = function ($name, $default = '') use ($db) {
    $value = getenv('DATABASE_' . strtoupper($name));
    return $value !== false && $value !== '' ? $value : ($db[$name] ?? $default);
};
$p = $setting('prefix', 'fa_');
if (!preg_match('/^[a-zA-Z0-9_]*$/D', $p)) throw new RuntimeException('Invalid database prefix');
$pdo = new PDO('mysql:host=' . $setting('hostname') . ';port=' . $setting('hostport', 3306) . ';dbname=' . $setting('database') . ';charset=utf8mb4', $setting('username'), $setting('password'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$apply = in_array('--apply', $argv, true);
$tables = ['user', 'user_score_log', 'third', 'wallet_account', 'wallet_ledger', 'legacy_checkin_daily'];
foreach ($tables as $table) {
    $q = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
    $q->execute([$p . $table]);
    if (!$q->fetchColumn()) throw new RuntimeException('Missing table: ' . $p . $table);
}
$column = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');
$column->execute([$p . 'user', 'wechatopenid']);
if (!$column->fetchColumn()) throw new RuntimeException('Missing old user.wechatopenid column');

// One transaction makes all target inserts succeed or roll back together.
if ($apply) $pdo->beginTransaction();
try {
    $users = $pdo->query("SELECT id,score,wechatopenid,nickname FROM `{$p}user` ORDER BY id" . ($apply ? ' FOR UPDATE' : ''))->fetchAll(PDO::FETCH_ASSOC);
    $logs = $pdo->query("SELECT id,user_id,score,`before`,`after`,memo,createtime FROM `{$p}user_score_log` ORDER BY id" . ($apply ? ' FOR UPDATE' : ''))->fetchAll(PDO::FETCH_ASSOC);
    $targetCounts = [];
    foreach (['third', 'wallet_account', 'wallet_ledger', 'legacy_checkin_daily'] as $table) {
        $targetCounts[$table] = (int)$pdo->query("SELECT COUNT(*) FROM `{$p}{$table}`")->fetchColumn();
    }
    $ids = []; $openids = []; $days = []; $visitors = []; $latest = []; $types = []; $errors = [];
    foreach ($users as $user) {
        $ids[(int)$user['id']] = true;
        $openid = trim((string)$user['wechatopenid']);
        if ($openid !== '') {
            if (isset($openids[$openid])) $errors[] = 'Duplicate OpenID at user ' . $user['id'];
            $openids[$openid] = true;
        }
    }
    $tz = new DateTimeZone('Asia/Shanghai');
    foreach ($logs as $log) {
        $uid = (int)$log['user_id']; $id = (int)$log['id']; $memo = $log['memo'];
        if (!isset($ids[$uid])) $errors[] = "Orphan score log $id";
        if ((int)$log['before'] + (int)$log['score'] !== (int)$log['after']) $errors[] = "Invalid score arithmetic at log $id";
        if (isset($latest[$uid]) && $latest[$uid] !== (int)$log['before']) $errors[] = "Discontinuous score history at log $id";
        $latest[$uid] = (int)$log['after'];
        if ((int)$log['createtime'] <= 0) $errors[] = "Invalid timestamp at log $id";
        if ($memo === '每日签到') {
            $date = (new DateTimeImmutable('@' . (int)$log['createtime']))->setTimezone($tz)->format('Y-m-d');
            $key = $uid . ':' . $date;
            if (isset($days[$key])) $errors[] = "Duplicate daily checkin at log $id";
            $days[$key] = true;
            $type = 'legacy_checkin';
        } elseif ($memo === '观看视频广告') $type = 'legacy_ad';
        elseif ($memo === '虚拟定位') $type = 'legacy_virtual';
        elseif (strpos($memo, '邀请好友:') === 0) {
            $visitor = substr($memo, strlen('邀请好友:'));
            if (!preg_match('/^[1-9][0-9]*$/D', $visitor) || !isset($ids[(int)$visitor]) || (int)$visitor === $uid || isset($visitors[$visitor])) $errors[] = "Invalid or duplicate invitation at log $id";
            $visitors[$visitor] = true;
            $type = 'legacy_invite';
        } else $type = 'legacy';
        $types[$type] = ($types[$type] ?? 0) + 1;
    }
    foreach ($users as $user) {
        $uid = (int)$user['id'];
        if (isset($latest[$uid]) && $latest[$uid] !== (int)$user['score']) $errors[] = "Balance mismatch at user $uid";
        if (!isset($latest[$uid]) && (int)$user['score'] !== 0) echo "User $uid has balance without history; balance will be preserved.\n";
    }
    echo 'Source users=' . count($users) . ', bindings=' . count($openids) . ', logs=' . count($logs) . ', daily=' . count($days) . PHP_EOL;
    foreach ($types as $type => $count) echo "$type=$count\n";
    foreach ($targetCounts as $table => $count) echo "Target $table=$count\n";
    foreach ($errors as $error) echo "ERROR: $error\n";
    if ($errors) throw new RuntimeException('Source validation failed');
    if (array_sum($targetCounts) !== 0) {
        echo "Target tables must be empty; this script never clears or merges existing records.\n";
        if ($apply) throw new RuntimeException('Target tables are not empty');
    }
    if (!$apply) { echo "Preview only; pass --apply to migrate after resolving target data.\n"; exit; }
    $binding = $pdo->prepare("INSERT INTO `{$p}third` (user_id,platform,apptype,openid,unionid,openname,createtime,updatetime) VALUES (?, 'wechat_legacy', 'miniapp', ?, '', ?, ?, ?)");
    $account = $pdo->prepare("INSERT INTO `{$p}wallet_account` (user_id,currency,balance,createtime,updatetime) VALUES (?, 'coin', ?, ?, ?)");
    $ledger = $pdo->prepare("INSERT INTO `{$p}wallet_ledger` (user_id,currency,amount,`before`,`after`,type,source_type,source_id,memo,createtime) VALUES (?, 'coin', ?, ?, ?, ?, ?, ?, ?, ?)");
    $daily = $pdo->prepare("INSERT INTO `{$p}legacy_checkin_daily` (user_id,check_date,reward,createtime) VALUES (?, ?, ?, ?)");
    $now = time();
    foreach ($users as $user) {
        $account->execute([(int)$user['id'], (int)$user['score'], $now, $now]);
        $openid = trim((string)$user['wechatopenid']);
        if ($openid !== '') $binding->execute([(int)$user['id'], $openid, (string)$user['nickname'], $now, $now]);
    }
    foreach ($logs as $log) {
        $memo = $log['memo']; $sourceType = 'legacy_score'; $sourceId = (int)$log['id'];
        if ($memo === '每日签到') {
            $type = 'legacy_checkin';
            $date = (new DateTimeImmutable('@' . (int)$log['createtime']))->setTimezone($tz)->format('Y-m-d');
            $daily->execute([(int)$log['user_id'], $date, (int)$log['score'], (int)$log['createtime']]);
        } elseif ($memo === '观看视频广告') $type = 'legacy_ad';
        elseif ($memo === '虚拟定位') $type = 'legacy_virtual';
        elseif (strpos($memo, '邀请好友:') === 0) { $type = 'legacy_invite'; $sourceType = 'legacy_invite'; $sourceId = (int)substr($memo, strlen('邀请好友:')); }
        else $type = 'legacy';
        $ledger->execute([(int)$log['user_id'], (int)$log['score'], (int)$log['before'], (int)$log['after'], $type, $sourceType, $sourceId, $memo, (int)$log['createtime']]);
    }
    $pdo->commit();
    echo "Migration committed. Legacy users and score logs were retained.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
