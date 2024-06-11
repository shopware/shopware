DROP TABLE IF EXISTS `attribute_entity_currency`;
DROP TABLE IF EXISTS `attribute_entity_translation`;
DROP TABLE IF EXISTS `attribute_entity_agg`;
DROP TABLE IF EXISTS `attribute_entity`;

CREATE TABLE `attribute_entity` (
    `id` BINARY(16) NOT NULL,
    `string` VARCHAR(255) NOT NULL,
    `text` LONGTEXT NULL,
    `int` INT(11) NULL,
    `float` DOUBLE NULL,
    `bool` TINYINT(1) NULL DEFAULT '0',
    `datetime` DATETIME(3) NULL,
    `auto_increment` int NOT NULL AUTO_INCREMENT,
    `json` JSON NULL,
    `date` DATE NULL,
    `date_interval` VARCHAR(255) NULL,
    `time_zone` VARCHAR(255) NULL,
    `serialized` JSON NULL,
    `string_storage` VARCHAR(255) NOT NULL,
    `text_storage` LONGTEXT NULL,
    `int_storage` INT(11) NULL,
    `float_storage` DOUBLE NULL,
    `bool_storage` TINYINT(1) NULL DEFAULT '0',
    `datetime_storage` DATETIME(3) NULL,
    `json_storage` JSON NULL,
    `date_storage` DATE NULL,
    `date_interval_storage` VARCHAR(255) NULL,
    `time_zone_storage` VARCHAR(255) NULL,
    `serialized_storage` JSON NULL,
    `currency_id` BINARY(16) NULL,
    `currency_storage_id` BINARY(16) NULL,
    `follow_id` BINARY(16) NULL,
    `follow_storage_id` BINARY(16) NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `auto_increment` (`auto_increment`),
    CONSTRAINT `json.attribute_entity.json` CHECK (JSON_VALID(`json`)),
    KEY `fk.attribute_entity.currency_id` (`currency_id`),
    KEY `fk.attribute_entity.currency_storage_id` (`currency_storage_id`),
    CONSTRAINT `fk.attribute_entity.currency_id` FOREIGN KEY (`currency_id`) REFERENCES `currency` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.attribute_entity.currency_storage_id` FOREIGN KEY (`currency_storage_id`) REFERENCES `currency` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.attribute_entity.follow_id` FOREIGN KEY (`follow_id`) REFERENCES `currency` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk.attribute_entity.follow_storage_id` FOREIGN KEY (`follow_storage_id`) REFERENCES `currency` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `attribute_entity_currency` (
    `attribute_entity_id` BINARY(16) NOT NULL,
    `currency_id` BINARY(16) NOT NULL,
    PRIMARY KEY (`attribute_entity_id`,`currency_id`),
    KEY `fk.attribute_entity_currency.attribute_entity_id` (`attribute_entity_id`),
    KEY `fk.attribute_entity_currency.currency_id` (`currency_id`),
    CONSTRAINT `fk.attribute_entity_currency.attribute_entity_id` FOREIGN KEY (`attribute_entity_id`) REFERENCES `attribute_entity` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.attribute_entity_currency.currency_id` FOREIGN KEY (`currency_id`) REFERENCES `currency` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `attribute_entity_translation` (
    `trans_string` VARCHAR(255) NOT NULL,
    `trans_text` LONGTEXT NULL,
    `trans_int` INT(11) NULL,
    `trans_float` DOUBLE NULL,
    `trans_bool` TINYINT(1) NULL DEFAULT '0',
    `trans_datetime` DATETIME(3) NULL,
    `trans_json` JSON NULL,
    `trans_date` DATE NULL,
    `trans_date_interval` VARCHAR(255) NULL,
    `trans_time_zone` VARCHAR(255) NULL,
    `string_storage` VARCHAR(255) NOT NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    `attribute_entity_id` BINARY(16) NOT NULL,
    `language_id` BINARY(16) NOT NULL,
    PRIMARY KEY (`attribute_entity_id`,`language_id`),
    CONSTRAINT `json.attribute_entity_translation.trans_json` CHECK (JSON_VALID(`trans_json`)),
    KEY `fk.attribute_entity_translation.attribute_entity_id` (`attribute_entity_id`),
    KEY `fk.attribute_entity_translation.language_id` (`language_id`),
    CONSTRAINT `fk.attribute_entity_translation.attribute_entity_id` FOREIGN KEY (`attribute_entity_id`) REFERENCES `attribute_entity` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.attribute_entity_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `attribute_entity_agg` (
    `id` BINARY(16) NOT NULL,
    `attribute_entity_id` BINARY(16) NOT NULL,
    `number` VARCHAR(255) NOT NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
