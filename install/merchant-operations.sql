CREATE TABLE IF NOT EXISTS `pre_subscription_resolution` (
 `trade_no` varchar(32) NOT NULL, `uid` int unsigned NOT NULL,
 `action` varchar(20) NOT NULL, `reason` varchar(500) NOT NULL, `reference` varchar(200) NOT NULL DEFAULT '',
 `actor` varchar(100) NOT NULL, `old_gid` int unsigned NOT NULL, `new_gid` int unsigned NOT NULL,
 `old_endtime` datetime DEFAULT NULL, `new_endtime` datetime DEFAULT NULL, `created_at` datetime NOT NULL,
 PRIMARY KEY (`trade_no`), KEY (`uid`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `pre_subscription_reminder` (
 `uid` int unsigned NOT NULL, `endtime` datetime NOT NULL, `stage` varchar(10) NOT NULL,
 `attempts` int unsigned NOT NULL DEFAULT 0, `sent_at` datetime DEFAULT NULL, `retry_at` datetime DEFAULT NULL,
 PRIMARY KEY (`uid`,`endtime`,`stage`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
