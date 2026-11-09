CREATE TABLE IF NOT EXISTS `__PREFIX__wallet_account` (
  `user_id` int unsigned NOT NULL,
  `currency` varchar(16) NOT NULL DEFAULT 'coin',
  `balance` bigint NOT NULL DEFAULT 0,
  `createtime` bigint NOT NULL,
  `updatetime` bigint NOT NULL,
  PRIMARY KEY (`user_id`,`currency`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='钱包余额';

CREATE TABLE IF NOT EXISTS `__PREFIX__wallet_ledger` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `currency` varchar(16) NOT NULL DEFAULT 'coin',
  `amount` bigint NOT NULL,
  `before` bigint NOT NULL,
  `after` bigint NOT NULL,
  `type` varchar(32) NOT NULL,
  `source_type` varchar(32) DEFAULT NULL,
  `source_id` bigint unsigned DEFAULT NULL,
  `memo` varchar(100) NOT NULL DEFAULT '',
  `createtime` bigint NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `source` (`source_type`,`source_id`),
  KEY `user_currency_time` (`user_id`,`currency`,`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='钱包流水';
