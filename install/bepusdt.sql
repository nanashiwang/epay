CREATE TABLE IF NOT EXISTS `pre_bepusdt_account` (
 `id` int unsigned NOT NULL, `uid` int unsigned NOT NULL,
 `endpoint` varchar(250) NOT NULL, `secret` text NOT NULL,
 `address` varchar(128) NOT NULL DEFAULT '', `timeout` int NOT NULL DEFAULT 1200,
 `verified_at` datetime DEFAULT NULL, `tested_at` datetime DEFAULT NULL,
 `last_callback` datetime DEFAULT NULL, `last_error` varchar(200) DEFAULT NULL,
 `created_at` datetime NOT NULL, `deleted_at` datetime DEFAULT NULL,
 PRIMARY KEY (`id`), KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `pre_bepusdt_route` (
 `uid` int unsigned NOT NULL, `type` int unsigned NOT NULL, `account_id` int unsigned NOT NULL,
 PRIMARY KEY (`uid`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `pre_bepusdt_order` (
 `trade_no` varchar(32) NOT NULL, `uid` int unsigned NOT NULL, `account_id` int unsigned NOT NULL DEFAULT 0,
 `channel_id` int unsigned NOT NULL, `config_snapshot` text NOT NULL,
 `money` decimal(10,2) NOT NULL, `network` varchar(32) NOT NULL,
 `provider_id` varchar(64) DEFAULT NULL, `address` varchar(128) DEFAULT NULL,
 `coin_amount` varchar(40) DEFAULT NULL, `payment_url` varchar(1000) DEFAULT NULL,
 `expires_at` datetime DEFAULT NULL, `state` varchar(20) NOT NULL,
 `txid` varchar(128) DEFAULT NULL, `receipt_key` char(64) DEFAULT NULL,
 `last_error` varchar(200) DEFAULT NULL, `created_at` datetime NOT NULL, `paid_at` datetime DEFAULT NULL,
 PRIMARY KEY (`trade_no`), UNIQUE KEY (`receipt_key`), KEY (`uid`,`created_at`), KEY (`account_id`,`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `pre_bepusdt_audit` (
 `id` bigint unsigned NOT NULL AUTO_INCREMENT, `uid` int unsigned NOT NULL, `account_id` int unsigned NOT NULL,
 `action` varchar(40) NOT NULL, `detail` varchar(300) NOT NULL DEFAULT '', `created_at` datetime NOT NULL,
 PRIMARY KEY (`id`), KEY (`uid`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `pre_subscription_purchase` (
 `trade_no` varchar(32) NOT NULL, `uid` int unsigned NOT NULL, `gid` int unsigned NOT NULL,
 `request_key` char(64) NOT NULL, `created_at` datetime NOT NULL,
 PRIMARY KEY (`trade_no`), UNIQUE KEY (`request_key`), KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `pre_subscription_event` (
 `trade_no` varchar(32) NOT NULL, `uid` int unsigned NOT NULL, `gid` int unsigned NOT NULL,
 `months` int unsigned NOT NULL, `money` decimal(10,2) NOT NULL,
 `old_endtime` datetime DEFAULT NULL, `new_endtime` datetime DEFAULT NULL,
 `state` varchar(20) NOT NULL, `created_at` datetime NOT NULL,
 PRIMARY KEY (`trade_no`), KEY (`uid`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
