CREATE TABLE IF NOT EXISTS `pre_merchant_channel_template` (
 `plugin` varchar(32) NOT NULL, `type` int unsigned NOT NULL, `channel` int unsigned NOT NULL,
 PRIMARY KEY (`plugin`,`type`), UNIQUE KEY (`channel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `pre_merchant_channel_account` (
 `id` int unsigned NOT NULL, `uid` int unsigned NOT NULL, `plugin` varchar(32) NOT NULL,
 `type` int unsigned NOT NULL, `secret` mediumtext NOT NULL, `revision` int unsigned NOT NULL DEFAULT 1,
 `tested_at` datetime DEFAULT NULL, `last_callback` datetime DEFAULT NULL,
 `created_at` datetime NOT NULL, `deleted_at` datetime DEFAULT NULL,
 PRIMARY KEY (`id`), KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `pre_merchant_channel_route` (
 `uid` int unsigned NOT NULL, `type` int unsigned NOT NULL, `account_id` int unsigned NOT NULL,
 PRIMARY KEY (`uid`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `pre_merchant_channel_order` (
 `trade_no` varchar(32) NOT NULL, `uid` int unsigned NOT NULL, `account_id` int unsigned NOT NULL,
 `channel_id` int unsigned NOT NULL, `type` int unsigned NOT NULL, `plugin` varchar(32) NOT NULL,
 `revision` int unsigned NOT NULL, `config_snapshot` mediumtext NOT NULL, `money` decimal(10,2) NOT NULL,
 `receipt_key` char(64) DEFAULT NULL, `provider_id` varchar(128) DEFAULT NULL,
 `created_at` datetime NOT NULL, `paid_at` datetime DEFAULT NULL,
 PRIMARY KEY (`trade_no`), UNIQUE KEY (`receipt_key`), KEY (`uid`,`created_at`), KEY (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `pre_merchant_channel_audit` (
 `id` bigint unsigned NOT NULL AUTO_INCREMENT, `uid` int unsigned NOT NULL, `account_id` int unsigned NOT NULL,
 `action` varchar(40) NOT NULL, `detail` varchar(100) NOT NULL DEFAULT '', `created_at` datetime NOT NULL,
 PRIMARY KEY (`id`), KEY (`uid`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
