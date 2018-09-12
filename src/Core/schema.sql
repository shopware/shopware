CREATE TABLE `plugin` (
  `id` VARCHAR(250) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` mediumtext COLLATE utf8mb4_unicode_ci,
  `description_long` mediumtext COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) NOT NULL,
  `installation_date` datetime(3) DEFAULT NULL,
  `update_date` datetime(3) DEFAULT NULL,
  `refresh_date` datetime(3) DEFAULT NULL,
  `author` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `copyright` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `version` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `support` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `changes` mediumtext COLLATE utf8mb4_unicode_ci,
  `link` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `store_version` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `store_date` datetime(3) DEFAULT NULL,
  `capability_update` tinyint(1) NOT NULL,
  `capability_install` tinyint(1) NOT NULL,
  `capability_enable` tinyint(1) NOT NULL,
  `update_source` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `update_version` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `capability_secure_uninstall` tinyint(1) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `migration` (
    `class` VARCHAR(255) NOT NULL,
    `creation_timestamp` INT(8) NOT NULL,
    `update` TIMESTAMP(6) NULL DEFAULT NULL,
    `update_destructive` TIMESTAMP(6) NULL DEFAULT NULL,
    `message` TEXT DEFAULT NULL,
    PRIMARY KEY (`class`)
) ENGINE=InnoDB COLLATE=utf8_unicode_ci;