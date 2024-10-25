DROP TABLE IF EXISTS `attribute_entity_order`;
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
    `custom_fields` JSON NULL,
    `date` DATE NULL,
    `date_interval` VARCHAR(255) NULL,
    `time_zone` VARCHAR(255) NULL,
    `serialized` JSON NULL,
    `price` LONGTEXT NULL,
    `currency_id` BINARY(16) NULL,
    `state_id` BINARY(16) NULL,
    `follow_id` BINARY(16) NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `auto_increment` (`auto_increment`),
    CONSTRAINT `json.attribute_entity.json` CHECK (JSON_VALID(`json`)),
    CONSTRAINT `json.attribute_entity.price` CHECK (JSON_VALID(`price`)),
    KEY `fk.attribute_entity.currency_id` (`currency_id`),
    CONSTRAINT `fk.attribute_entity.currency_id` FOREIGN KEY (`currency_id`) REFERENCES `currency` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.attribute_entity.state_id` FOREIGN KEY (`state_id`) REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.attribute_entity.follow_id` FOREIGN KEY (`follow_id`) REFERENCES `currency` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
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
    `another_column_name` VARCHAR(255) NULL,
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

CREATE TABLE `attribute_entity_order` (
    `attribute_entity_id` BINARY(16) NOT NULL,
    `order_id` BINARY(16) NOT NULL,
    `order_version_id` BINARY(16) NOT NULL,
    PRIMARY KEY (`attribute_entity_id`,`order_id`, `order_version_id`),
    KEY `fk.attribute_entity_order.attribute_entity_id` (`attribute_entity_id`),
    KEY `fk.attribute_entity_order.order_id` (`order_id`, `order_version_id`),
    CONSTRAINT `fk.attribute_entity_order.attribute_entity_id` FOREIGN KEY (`attribute_entity_id`) REFERENCES `attribute_entity` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.attribute_entity_order.order_id` FOREIGN KEY (`order_id`, `order_version_id`) REFERENCES `order` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
