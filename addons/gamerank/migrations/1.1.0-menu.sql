INSERT INTO `fa_auth_rule`
(`type`, `pid`, `name`, `title`, `icon`, `ismenu`, `py`, `pinyin`, `createtime`, `updatetime`, `weigh`, `status`)
SELECT 'file', `id`, 'gamerank/record', '曲目成绩', 'fa fa-circle-o', 1, 'qmcj', 'qumujichengji', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'
FROM `fa_auth_rule`
WHERE `name` = 'gamerank'
  AND NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'gamerank/record');

UPDATE `fa_auth_rule`
SET `ismenu` = 1, `updatetime` = UNIX_TIMESTAMP()
WHERE `name` IN ('gamerank/player', 'gamerank/record', 'gamerank/pointlog');
