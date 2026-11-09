CREATE TABLE IF NOT EXISTS `__PREFIX__checkin_user` (
  `user_id` int unsigned NOT NULL,
  `createtime` bigint NOT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='打卡用户';

CREATE TABLE IF NOT EXISTS `__PREFIX__checkin_daily` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `check_date` date NOT NULL,
  `reward` int NOT NULL,
  `createtime` bigint NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_date` (`user_id`,`check_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='每日打卡';

CREATE TABLE IF NOT EXISTS `__PREFIX__checkin_recharge_package` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(60) NOT NULL DEFAULT '',
  `description` varchar(120) NOT NULL DEFAULT '',
  `recommended` tinyint unsigned NOT NULL DEFAULT 0,
  `coins` int unsigned NOT NULL,
  `price_fen` int unsigned NOT NULL,
  `original_price_fen` int unsigned NOT NULL,
  `enabled` tinyint unsigned NOT NULL DEFAULT 1,
  `weigh` int NOT NULL DEFAULT 0,
  `createtime` bigint NOT NULL DEFAULT 0,
  `updatetime` bigint NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='充值套餐';

INSERT IGNORE INTO `__PREFIX__checkin_recharge_package` (`id`,`name`,`coins`,`price_fen`,`original_price_fen`,`enabled`,`weigh`) VALUES
(1,'轻享套餐',50,99,1000,1,1),(2,'畅享套餐',100,198,2000,1,2),(3,'尊享套餐',500,495,10000,1,3);

CREATE TABLE IF NOT EXISTS `__PREFIX__checkin_recharge_order` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(64) NOT NULL,
  `user_id` int unsigned NOT NULL,
  `package_id` int unsigned NOT NULL,
  `coins` int unsigned NOT NULL,
  `price_fen` int unsigned NOT NULL,
  `pay_type` varchar(16) NOT NULL,
  `payment_appid` varchar(64) NOT NULL DEFAULT '',
  `merchant_id` varchar(32) NOT NULL DEFAULT '',
  `status` varchar(16) NOT NULL DEFAULT 'pending',
  `transaction_no` varchar(128) DEFAULT NULL,
  `createtime` bigint NOT NULL,
  `paidtime` bigint DEFAULT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `order_no` (`order_no`),
  KEY `user_time` (`user_id`,`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='充值订单';

CREATE TABLE IF NOT EXISTS `__PREFIX__checkin_announcement` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `content` text NOT NULL,
  `enabled` tinyint unsigned NOT NULL DEFAULT 0,
  `starttime` bigint NOT NULL DEFAULT 0,
  `endtime` bigint NOT NULL DEFAULT 0,
  `version` int unsigned NOT NULL DEFAULT 1,
  `weigh` int NOT NULL DEFAULT 0,
  `createtime` bigint NOT NULL DEFAULT 0,
  `updatetime` bigint NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`), KEY `active` (`enabled`,`starttime`,`endtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Android公告';

CREATE TABLE IF NOT EXISTS `__PREFIX__checkin_promotion` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(40) NOT NULL,
  `description` varchar(80) NOT NULL DEFAULT '',
  `target_type` varchar(16) NOT NULL,
  `target` varchar(1000) NOT NULL,
  `enabled` tinyint unsigned NOT NULL DEFAULT 0,
  `weigh` int unsigned NOT NULL DEFAULT 0,
  `createtime` bigint NOT NULL DEFAULT 0,
  `updatetime` bigint NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`), KEY `display_order` (`enabled`,`weigh`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Android首页推广';
