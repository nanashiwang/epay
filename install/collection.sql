CREATE TABLE IF NOT EXISTS `pre_collection_account` (
  `id` int unsigned NOT NULL,
  `uid` int unsigned NOT NULL,
  `alipay_uid` varchar(16) NOT NULL,
  `appid` varchar(32) NOT NULL,
  `qr_url` varchar(200) NOT NULL,
  `secret` text NOT NULL,
  `verified_at` datetime DEFAULT NULL,
  `checked_at` datetime DEFAULT NULL,
  `last_ok` datetime DEFAULT NULL,
  `heartbeat_at` datetime DEFAULT NULL,
  `last_error` varchar(120) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY (`alipay_uid`), UNIQUE KEY (`appid`), UNIQUE KEY (`qr_url`), KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `pre_collection_route` (
  `uid` int unsigned NOT NULL, `account_id` int unsigned NOT NULL, PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `pre_collection_receipt` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int unsigned NOT NULL, `uid` int unsigned NOT NULL,
  `receipt_no` varchar(64) NOT NULL, `amount` decimal(12,2) NOT NULL,
  `paid_at` datetime NOT NULL, `memo` varchar(200) NOT NULL DEFAULT '',
  `state` varchar(20) NOT NULL DEFAULT 'unmatched', `trade_no` varchar(32) DEFAULT NULL,
  `review_note` varchar(300) DEFAULT NULL, `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY (`receipt_no`), KEY (`uid`,`id`), KEY (`account_id`,`paid_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `pre_collection_reservation` (
  `account_id` int unsigned NOT NULL, `amount_cents` bigint unsigned NOT NULL,
  `trade_no` varchar(32) NOT NULL, `expires_at` datetime NOT NULL,
  PRIMARY KEY (`account_id`,`amount_cents`), UNIQUE KEY (`trade_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `pre_collection_audit` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `uid` int unsigned NOT NULL,
  `account_id` int unsigned NOT NULL, `action` varchar(40) NOT NULL,
  `detail` varchar(400) NOT NULL, `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`), KEY (`uid`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `pre_collection_notify` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `uid` int unsigned NOT NULL,
  `trade_no` varchar(32) NOT NULL, `target` varchar(300) NOT NULL,
  `http_code` int NOT NULL DEFAULT 0, `duration_ms` int NOT NULL DEFAULT 0,
  `success` tinyint NOT NULL DEFAULT 0, `response_summary` varchar(120) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`), KEY (`uid`,`id`), KEY (`trade_no`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
