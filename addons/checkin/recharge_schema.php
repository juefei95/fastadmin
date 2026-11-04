<?php
// Preview by default; --apply creates missing tables and inserts missing default packages.
if (PHP_SAPI !== 'cli') exit(1);
if (!in_array('--apply', $argv, true)) {
    echo "Dry run: initialize checkin schema and default packages. Pass --apply to execute.\n";
    exit;
}
$root = dirname(__DIR__, 2);
$env = parse_ini_file($root . '/.env', true);
$db = $env['database'];
$prefix = $db['prefix'];
if (!preg_match('/^[a-zA-Z0-9_]*$/', $prefix)) throw new RuntimeException('Invalid table prefix');
$pdo = new PDO('mysql:host=' . (getenv('DATABASE_HOSTNAME') ?: $db['hostname']) . ';port=' . (getenv('DATABASE_HOSTPORT') ?: $db['hostport']) . ';dbname=' . (getenv('DATABASE_DATABASE') ?: $db['database']) . ';charset=utf8mb4', getenv('DATABASE_USERNAME') ?: $db['username'], getenv('DATABASE_PASSWORD') ?: $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$table = $prefix . 'checkin_recharge_package';
$exists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
$exists->execute([$table]);
$packageTableExists = (bool)$exists->fetchColumn();
if ($packageTableExists) {
    $column = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');
    $fields = ['name' => "varchar(60) NOT NULL DEFAULT ''", 'description' => "varchar(120) NOT NULL DEFAULT ''", 'recommended' => 'tinyint unsigned NOT NULL DEFAULT 0', 'createtime' => 'bigint NOT NULL DEFAULT 0', 'updatetime' => 'bigint NOT NULL DEFAULT 0'];
    foreach ($fields as $name => $definition) {
        $column->execute([$table, $name]);
        if (!$column->fetchColumn()) $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$name}` {$definition}");
    }
}
foreach (explode(';', str_replace('__PREFIX__', $prefix, file_get_contents(__DIR__ . '/install.sql'))) as $sql) {
    if ($packageTableExists && preg_match('/^\s*INSERT\s+IGNORE\s+INTO\s+`' . preg_quote($table, '/') . '`/i', $sql)) continue;
    if (trim($sql)) $pdo->exec($sql);
}
$pdo->exec("UPDATE `{$table}` SET name = CASE id WHEN 1 THEN '轻享套餐' WHEN 2 THEN '畅享套餐' WHEN 3 THEN '尊享套餐' ELSE CONCAT('套餐 ',id) END WHERE name = ''");
echo "Checkin schema initialized. Existing package rows were preserved.\n";
