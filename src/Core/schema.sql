CREATE TABLE `plugin` (
    `id`              binary(16)                              NOT NULL,
    `name`            varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `composer_name`   varchar(255) COLLATE utf8mb4_unicode_ci NULL,
    `active`          tinyint(1)                              NOT NULL DEFAULT 0,
    `path`            varchar(255) COLLATE utf8mb4_unicode_ci NULL,
    `author`          varchar(255) COLLATE utf8mb4_unicode_ci NULL,
    `copyright`       varchar(255) COLLATE utf8mb4_unicode_ci NULL,
    `license`         varchar(255) COLLATE utf8mb4_unicode_ci NULL,
    `version`         varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `upgrade_version` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
    `installed_at`    datetime(3)                             NULL,
    `upgraded_at`     datetime(3)                             NULL,
    `created_at`      datetime(3)                             NOT NULL,
    `updated_at`      datetime(3)                             NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.name` (`name`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `migration` (
    `class`              varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `creation_timestamp` int(8)                                  NOT NULL,
    `update`             timestamp(6)                            NULL,
    `update_destructive` timestamp(6)                            NULL,
    `message`            text COLLATE utf8mb4_unicode_ci         NULL,
    PRIMARY KEY (`class`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE = utf8mb4_unicode_ci;