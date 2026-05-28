-- Telegram Bot plugin tables

CREATE TABLE IF NOT EXISTS `__PREFIX__plugin_telegrambot_user` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tg_user_id`          BIGINT NOT NULL,
    `tg_chat_id`          BIGINT NOT NULL DEFAULT 0,
    `user_id`             INT UNSIGNED NOT NULL DEFAULT 0,
    `username`            VARCHAR(64)  DEFAULT NULL,
    `first_name`          VARCHAR(128) DEFAULT NULL,
    `last_name`           VARCHAR(128) DEFAULT NULL,
    `language_code`       VARCHAR(16)  DEFAULT NULL,
    `phone`               VARCHAR(32)  DEFAULT NULL,
    `state`               VARCHAR(64)  DEFAULT NULL,
    `state_data`          TEXT         DEFAULT NULL,
    `cart`                TEXT         DEFAULT NULL,
    `current_message_id`  INT          NOT NULL DEFAULT 0,
    `message_thread_id`   INT          NOT NULL DEFAULT 0,
    `is_banned`           TINYINT(1)   NOT NULL DEFAULT 0,
    `last_msg_at`         INT          NOT NULL DEFAULT 0,
    `create_time`         DATETIME     NULL DEFAULT NULL,
    `update_time`         DATETIME     NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_tg_user` (`tg_user_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_thread` (`message_thread_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `__PREFIX__plugin_telegrambot_msgmap` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tg_user_id`            BIGINT NOT NULL,
    `user_chat_message_id`  INT NOT NULL,
    `group_chat_message_id` INT NOT NULL,
    `create_time`           DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_user_msg`  (`user_chat_message_id`),
    KEY `idx_group_msg` (`group_chat_message_id`),
    KEY `idx_user`      (`tg_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `__PREFIX__plugin_telegrambot_sso` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `token`       VARCHAR(64)  NOT NULL,
    `user_id`     INT UNSIGNED NOT NULL,
    `tg_user_id`  BIGINT NOT NULL,
    `expire_at`   INT NOT NULL,
    `used`        TINYINT(1) NOT NULL DEFAULT 0,
    `create_time` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `__PREFIX__plugin_telegrambot_order` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `trade_no`    VARCHAR(64) NOT NULL,
    `tg_user_id`  BIGINT NOT NULL,
    `tg_chat_id`  BIGINT NOT NULL,
    `message_id`  INT NOT NULL DEFAULT 0,
    `pay_url`     TEXT,
    `amount`      DECIMAL(12,2) NOT NULL DEFAULT 0,
    `commodity_name` VARCHAR(255) DEFAULT NULL,
    `notified`    TINYINT(1) NOT NULL DEFAULT 0,
    `create_time` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_trade_no` (`trade_no`),
    KEY `idx_tg_user` (`tg_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `__PREFIX__plugin_telegrambot_state` (
    `k`           VARCHAR(64) NOT NULL,
    `v`           TEXT,
    `update_time` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
