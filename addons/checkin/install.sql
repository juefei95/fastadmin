CREATE TABLE IF NOT EXISTS `__PREFIX__checkin_daily` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `check_date` date NOT NULL,
  `reward` int NOT NULL,
  `createtime` bigint NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_date` (`user_id`,`check_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='每日打卡';
