CREATE TABLE `plugin` (
    `id`                  BINARY(16)                              NOT NULL,
    `name`                VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `base_class`          VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `composer_name`       VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `active`              TINYINT(1)                              NOT NULL DEFAULT 0,
    `managed_by_composer` TINYINT(1)                              NOT NULL DEFAULT 0,
    `path`                VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `autoload`            JSON                                    NOT NULL,
    `author`              VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `copyright`           VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `license`             VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `version`             VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `upgrade_version`     VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `icon`                MEDIUMBLOB                              NULL,
    `installed_at`        DATETIME(3)                             NULL,
    `upgraded_at`         DATETIME(3)                             NULL,
    `created_at`          DATETIME(3)                             NOT NULL,
    `updated_at`          DATETIME(3)                             NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.name` (`name`),
    UNIQUE KEY `uniq.baseClass` (`base_class`),
    CONSTRAINT `json.autoload` CHECK (JSON_VALID(`autoload`))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `migration` (
    `class`              VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `creation_timestamp` INT(8)                                  NOT NULL,
    `update`             TIMESTAMP(6)                            NULL,
    `update_destructive` TIMESTAMP(6)                            NULL,
    `message`            TEXT COLLATE utf8mb4_unicode_ci         NULL,
    PRIMARY KEY (`class`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE = utf8mb4_unicode_ci;
