CREATE TABLE IF NOT EXISTS `fa_remote_member` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `expire_time` bigint(16) DEFAULT NULL COMMENT '远控到期时间',
  `trial_given` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '是否领取体验:0=否,1=是',
  `trial_started_at` bigint(16) DEFAULT NULL COMMENT '体验领取时间',
  `control_enabled` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '控制权限:0=禁用,1=启用',
  `last_paid_at` bigint(16) DEFAULT NULL COMMENT '最近付费时间',
  `total_paid` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '累计消费',
  `created_at` bigint(16) DEFAULT NULL COMMENT '创建时间',
  `updated_at` bigint(16) DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `expire_time` (`expire_time`),
  KEY `control_enabled` (`control_enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='远控用户权益表';

CREATE TABLE IF NOT EXISTS `fa_remote_package` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '套餐名称',
  `days` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '套餐天数',
  `price` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '售价',
  `recommended` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '是否推荐:0=否,1=是',
  `status` varchar(30) NOT NULL DEFAULT 'normal' COMMENT '状态',
  `weigh` int(10) NOT NULL DEFAULT '0' COMMENT '权重',
  `description` varchar(255) NOT NULL DEFAULT '' COMMENT '客户端展示文案',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint(16) DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `weigh` (`weigh`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='远控套餐表';

CREATE TABLE IF NOT EXISTS `fa_remote_order` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `order_no` varchar(64) NOT NULL DEFAULT '' COMMENT '订单号',
  `user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `package_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '套餐ID',
  `package_name` varchar(50) NOT NULL DEFAULT '' COMMENT '套餐名称',
  `days` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '套餐天数',
  `amount` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '订单金额',
  `pay_type` varchar(30) NOT NULL DEFAULT '' COMMENT '支付方式',
  `status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '状态:0=待支付,1=已支付,2=已关闭,3=已退款',
  `paid_at` bigint(16) DEFAULT NULL COMMENT '支付时间',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint(16) DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_no` (`order_no`),
  KEY `user_id` (`user_id`),
  KEY `package_id` (`package_id`),
  KEY `status` (`status`),
  KEY `paid_at` (`paid_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='远控订单表';
