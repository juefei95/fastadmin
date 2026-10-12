CREATE TABLE IF NOT EXISTS `__PREFIX__game_rank_player` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` int(10) unsigned NOT NULL COMMENT '会员ID',
  `nickname` varchar(32) NOT NULL DEFAULT '' COMMENT '游戏昵称',
  `avatar_url` varchar(255) NOT NULL DEFAULT '' COMMENT '游戏头像',
  `total_points` int(11) NOT NULL DEFAULT '0' COMMENT '累计积分',
  `rank_visible` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否展示在排行榜',
  `last_played_at` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '最后游戏时间',
  `points_updated_at` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '积分更新时间',
  `createtime` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  `updatetime` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_id` (`user_id`),
  KEY `idx_leaderboard` (`rank_visible`,`total_points`,`points_updated_at`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='游戏排行榜玩家档案';

CREATE TABLE IF NOT EXISTS `__PREFIX__game_rank_point_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` int(10) unsigned NOT NULL COMMENT '会员ID',
  `delta` int(11) NOT NULL COMMENT '本次积分变化',
  `points_before` int(11) NOT NULL COMMENT '变更前积分',
  `points_after` int(11) NOT NULL COMMENT '变更后积分',
  `source_type` varchar(32) NOT NULL DEFAULT 'game_settlement' COMMENT '来源类型',
  `source_ref` varchar(64) NOT NULL DEFAULT '' COMMENT '来源标识',
  `idempotency_key` varchar(128) NOT NULL COMMENT '幂等键',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `createtime` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_idempotency_key` (`idempotency_key`),
  KEY `idx_user_time` (`user_id`,`createtime`),
  KEY `idx_source` (`source_type`,`source_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='游戏排行榜积分流水';

CREATE TABLE IF NOT EXISTS `__PREFIX__game_rank_record` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` int(10) unsigned NOT NULL COMMENT '会员ID',
  `game_code` varchar(32) NOT NULL DEFAULT 'paw_beat' COMMENT '游戏标识',
  `song_id` varchar(64) NOT NULL COMMENT '曲目标识',
  `difficulty` varchar(32) NOT NULL COMMENT '难度标识',
  `best_score` int(11) NOT NULL DEFAULT '0' COMMENT '最佳成绩',
  `total_score` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '累计成绩',
  `best_accuracy` int(11) NOT NULL DEFAULT '0' COMMENT '最佳准确率，万分比',
  `max_combo` int(11) NOT NULL DEFAULT '0' COMMENT '最佳连击',
  `last_score` int(11) NOT NULL DEFAULT '0' COMMENT '最近成绩',
  `play_count` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '游玩次数',
  `last_played_at` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '最后游玩时间',
  `createtime` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  `updatetime` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_player_chart` (`user_id`,`game_code`,`song_id`,`difficulty`),
  KEY `idx_chart_rank` (`game_code`,`song_id`,`difficulty`,`best_score`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='游戏曲目难度成绩';
