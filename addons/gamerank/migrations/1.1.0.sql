CREATE TABLE IF NOT EXISTS `fa_game_rank_record` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `game_code` varchar(32) NOT NULL,
  `song_id` varchar(64) NOT NULL,
  `difficulty` varchar(32) NOT NULL,
  `best_score` int(11) NOT NULL DEFAULT '0',
  `total_score` bigint(20) unsigned NOT NULL DEFAULT '0',
  `best_accuracy` int(11) NOT NULL DEFAULT '0',
  `max_combo` int(11) NOT NULL DEFAULT '0',
  `last_score` int(11) NOT NULL DEFAULT '0',
  `play_count` int(11) unsigned NOT NULL DEFAULT '0',
  `last_played_at` int(10) unsigned NOT NULL DEFAULT '0',
  `createtime` int(10) unsigned NOT NULL DEFAULT '0',
  `updatetime` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_player_chart` (`user_id`,`game_code`,`song_id`,`difficulty`),
  KEY `idx_chart_rank` (`game_code`,`song_id`,`difficulty`,`best_score`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='游戏曲目难度成绩';
