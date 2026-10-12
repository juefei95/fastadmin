ALTER TABLE `fa_game_rank_record`
  ADD COLUMN `total_score` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '累计成绩' AFTER `best_score`;
