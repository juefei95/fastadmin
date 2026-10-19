CREATE TABLE IF NOT EXISTS `__PREFIX__paw_chess_player` (
 `id` bigint unsigned NOT NULL AUTO_INCREMENT, `member_id` bigint unsigned DEFAULT NULL, `name` varchar(32) NOT NULL,
 `points` int NOT NULL DEFAULT 1000, `games` int unsigned NOT NULL DEFAULT 0, `wins` int unsigned NOT NULL DEFAULT 0,
 `draws` int unsigned NOT NULL DEFAULT 0, `created_at` bigint NOT NULL, PRIMARY KEY (`id`),
 UNIQUE KEY `member_id` (`member_id`), KEY `ranking` (`points`,`wins`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `__PREFIX__paw_chess_dev_identity` (
 `identity_hash` char(64) NOT NULL, `player_id` bigint unsigned NOT NULL, `created_at` bigint NOT NULL,
 PRIMARY KEY (`identity_hash`), UNIQUE KEY `player_id` (`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `__PREFIX__paw_chess_session` (
 `token_hash` char(64) NOT NULL, `player_id` bigint unsigned NOT NULL, `expires_at` bigint NOT NULL,
 PRIMARY KEY (`token_hash`), KEY `player_id` (`player_id`), KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `__PREFIX__paw_chess_room` (
 `id` char(24) NOT NULL, `code` char(6) NOT NULL, `active_code` char(6) DEFAULT NULL,
 `status` varchar(12) NOT NULL, `red_id` bigint unsigned NOT NULL, `blue_id` bigint unsigned DEFAULT NULL,
 `red_pet` varchar(16) NOT NULL, `blue_pet` varchar(16) DEFAULT NULL, `red_seen` bigint NOT NULL, `blue_seen` bigint DEFAULT NULL,
 `board_json` text NOT NULL, `moves_json` mediumtext NOT NULL, `captures_json` text NOT NULL, `positions_json` mediumtext NOT NULL,
 `turn` char(1) NOT NULL, `revision` int unsigned NOT NULL DEFAULT 0, `quiet` int unsigned NOT NULL DEFAULT 0,
 `turn_ms` int unsigned NOT NULL, `deadline` bigint NOT NULL DEFAULT 0, `rated` tinyint NOT NULL DEFAULT 1,
 `ready_json` varchar(80) NOT NULL, `rematch_ready_json` varchar(80) NOT NULL, `rematch_id` char(24) DEFAULT NULL,
 `series_json` text NOT NULL, `result_json` text DEFAULT NULL, `rating_json` text DEFAULT NULL, `last_json` varchar(80) DEFAULT NULL,
 `created_at` bigint NOT NULL, `started_at` bigint DEFAULT NULL, `ended_at` bigint DEFAULT NULL,
 PRIMARY KEY (`id`), UNIQUE KEY `active_code` (`active_code`), KEY `red_active` (`red_id`,`status`),
 KEY `blue_active` (`blue_id`,`status`), KEY `timeout` (`status`,`deadline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `__PREFIX__paw_chess_match` (
 `id` bigint unsigned NOT NULL AUTO_INCREMENT, `room_id` char(24) NOT NULL, `red_id` bigint unsigned NOT NULL,
 `blue_id` bigint unsigned DEFAULT NULL, `winner` varchar(8) NOT NULL, `reason` varchar(64) NOT NULL,
 `rated` tinyint NOT NULL, `red_delta` int NOT NULL DEFAULT 0, `blue_delta` int NOT NULL DEFAULT 0,
 `moves_json` mediumtext NOT NULL, `created_at` bigint NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `room_id` (`room_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `__PREFIX__paw_chess_rating_log` (
 `id` bigint unsigned NOT NULL AUTO_INCREMENT, `match_id` bigint unsigned NOT NULL, `player_id` bigint unsigned NOT NULL,
 `delta` int NOT NULL, `points_before` int NOT NULL, `points_after` int NOT NULL, `created_at` bigint NOT NULL,
 PRIMARY KEY (`id`), UNIQUE KEY `once_per_match` (`match_id`,`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
