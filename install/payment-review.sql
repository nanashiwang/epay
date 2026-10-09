CREATE TABLE IF NOT EXISTS `pre_payment_review` (
 `id` bigint unsigned NOT NULL AUTO_INCREMENT, `uid` int unsigned NOT NULL,
 `trade_no` varchar(32) NOT NULL, `actor` varchar(100) NOT NULL,
 `action` varchar(20) NOT NULL, `reason` varchar(500) NOT NULL, `reference` varchar(200) NOT NULL DEFAULT '',
 `request_key` char(64) NOT NULL, `state` varchar(20) NOT NULL, `result` varchar(300) NOT NULL DEFAULT '',
 `created_at` datetime NOT NULL, `completed_at` datetime DEFAULT NULL,
 PRIMARY KEY (`id`), UNIQUE KEY (`request_key`), KEY (`uid`,`id`), KEY (`trade_no`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
