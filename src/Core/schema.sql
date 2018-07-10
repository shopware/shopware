SET NAMES utf8mb4;

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `touchpoint`;
CREATE TABLE `touchpoint` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `configuration` LONGTEXT NULL DEFAULT NULL,
  `access_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `secret_access_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `catalog_ids` LONGTEXT NOT NULL,
  `currency_ids` LONGTEXT NOT NULL,
  `language_ids` LONGTEXT NOT NULL,
  `language_id` binary(16) NOT NULL,
  `language_tenant_id` binary(16) NOT NULL,
  `currency_id` binary(16) NOT NULL,
  `currency_tenant_id` binary(16) NOT NULL,
  `currency_version_id` binary(16) NOT NULL,
  `payment_method_id` binary(16) NOT NULL,
  `payment_method_tenant_id` binary(16) NOT NULL,
  `payment_method_version_id` binary(16) NOT NULL,
  `shipping_method_id` binary(16) NOT NULL,
  `shipping_method_tenant_id` binary(16) NOT NULL,
  `shipping_method_version_id` binary(16) NOT NULL,
  `country_id` binary(16) NOT NULL,
  `country_version_id` binary(16) NOT NULL,
  `country_tenant_id` binary(16) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `tax_calculation_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'vertical',
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`, `tenant_id`),
  INDEX `access_key` (`access_key`),
  CHECK (JSON_VALID(`catalog_ids`)),
  CHECK (JSON_VALID(`currency_ids`)),
  CHECK (JSON_VALID(`language_ids`)),
  CONSTRAINT `fk_touchpoint.country_id` FOREIGN KEY (`country_id`, `country_version_id`, `country_tenant_id`) REFERENCES `country` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_touchpoint.currency_id` FOREIGN KEY (`currency_id`, `currency_version_id`, `currency_tenant_id`) REFERENCES `currency` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_touchpoint.language_id` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_touchpoint.payment_method_id` FOREIGN KEY (`payment_method_id`, `payment_method_version_id`, `payment_method_tenant_id`) REFERENCES `payment_method` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_touchpoint.shipping_method_id` FOREIGN KEY (`shipping_method_id`, `shipping_method_version_id`, `shipping_method_tenant_id`) REFERENCES `shipping_method` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `cart`;
CREATE TABLE `cart` (
  `version_id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `token` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `container` LONGTEXT NOT NULL,
  `calculated` LONGTEXT NOT NULL,
  `price` float NOT NULL,
  `line_item_count` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `currency_id` binary(16) NOT NULL,
  `currency_tenant_id` binary(16) NOT NULL,
  `currency_version_id` binary(16) NOT NULL,
  `shipping_method_id` binary(16) NOT NULL,
  `shipping_method_tenant_id` binary(16) NOT NULL,
  `shipping_method_version_id` binary(16) NOT NULL,
  `payment_method_id` binary(16) NOT NULL,
  `payment_method_tenant_id` binary(16) NOT NULL,
  `payment_method_version_id` binary(16) NOT NULL,
  `country_id` binary(16) NOT NULL,
  `country_tenant_id` binary(16) NOT NULL,
  `country_version_id` binary(16) NOT NULL,
  `customer_id` binary(16) DEFAULT NULL,
  `customer_tenant_id` binary(16) DEFAULT NULL,
  `customer_version_id` binary(16) DEFAULT NULL,
  `touchpoint_id` binary(16) NOT NULL,
  `touchpoint_tenant_id` binary(16) NOT NULL,
  `created_at` datetime NOT NULL,
  CHECK (JSON_VALID(`container`)),
  CHECK (JSON_VALID(`calculated`)),
  PRIMARY KEY `token` (`token`, `name`, `tenant_id`),
  CONSTRAINT `fk_cart.country_id` FOREIGN KEY (`country_id`, `country_version_id`, `country_tenant_id`) REFERENCES `country` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cart.currency_id` FOREIGN KEY (`currency_id`, `currency_version_id`, `currency_tenant_id`) REFERENCES `currency` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cart.customer_id` FOREIGN KEY (`customer_id`, `customer_version_id`, `customer_tenant_id`) REFERENCES `customer` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cart.payment_method_id` FOREIGN KEY (`payment_method_id`, `payment_method_version_id`, `payment_method_tenant_id`) REFERENCES `payment_method` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cart.shipping_method_id` FOREIGN KEY (`shipping_method_id`, `shipping_method_version_id`, `shipping_method_tenant_id`) REFERENCES `shipping_method` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cart.touchpoint_id` FOREIGN KEY (`touchpoint_id`, `touchpoint_tenant_id`) REFERENCES `touchpoint` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `category`;
CREATE TABLE `category` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `catalog_id` binary(16) NOT NULL,
  `catalog_tenant_id` binary(16) NOT NULL,
  `auto_increment` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` binary(16) DEFAULT NULL,
  `parent_tenant_id` binary(16) DEFAULT NULL,
  `parent_version_id` binary(16) DEFAULT NULL,
  `media_id` binary(16) DEFAULT NULL,
  `media_tenant_id` binary(16) DEFAULT NULL,
  `media_version_id` binary(16) DEFAULT NULL,
  `path` longtext COLLATE utf8mb4_unicode_ci,
  `position` int(11) unsigned NOT NULL DEFAULT '1',
  `level` int(11) unsigned NOT NULL DEFAULT '1',
  `template` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `is_blog` tinyint(1) NOT NULL DEFAULT '0',
  `external` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hide_filter` tinyint(1) NOT NULL DEFAULT '0',
  `hide_top` tinyint(1) NOT NULL DEFAULT '0',
  `product_box_layout` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hide_sortings` tinyint(1) NOT NULL DEFAULT '0',
  `sorting_ids` longtext COLLATE utf8mb4_unicode_ci,
  `facet_ids` longtext COLLATE utf8mb4_unicode_ci,
  `child_count` int(11) unsigned NOT NULL DEFAULT '0',
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`, `version_id`, `tenant_id`),
  KEY `position` (`position`),
  KEY `level` (`level`),
  KEY `auto_increment` (`auto_increment`),
  CONSTRAINT `fk_category.catalog_id` FOREIGN KEY (`catalog_id`, `catalog_tenant_id`) REFERENCES `catalog` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_category.media_id` FOREIGN KEY (`media_id`, `media_version_id`, `media_tenant_id`) REFERENCES `media` (`id`, `version_id`, `tenant_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_category.parent_id` FOREIGN KEY (`parent_id`, `parent_version_id`, `parent_tenant_id`) REFERENCES `category` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `category_translation`;
CREATE TABLE `category_translation` (
  `category_id` binary(16) NOT NULL,
  `category_version_id` binary(16) NOT NULL,
  `category_tenant_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `language_tenant_id` binary(16) NOT NULL,
  `catalog_id` binary(16) NOT NULL,
  `catalog_tenant_id` binary(16) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `path_names` longtext COLLATE utf8mb4_unicode_ci,
  `meta_keywords` mediumtext COLLATE utf8mb4_unicode_ci,
  `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description` mediumtext COLLATE utf8mb4_unicode_ci,
  `cms_headline` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cms_description` mediumtext COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`category_id`, `category_version_id`, `category_tenant_id`, `language_id`, `language_tenant_id`),
  CONSTRAINT `category_translation_ibfk_1` FOREIGN KEY (`language_id`, `category_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `category_translation_ibfk_2` FOREIGN KEY (`category_id`, `category_version_id`, `language_tenant_id`) REFERENCES `category` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `category_translation_ibfk_3` FOREIGN KEY (`catalog_id`, `catalog_tenant_id`) REFERENCES `catalog` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `configuration_group`;
CREATE TABLE `configuration_group` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `position` int(11) NOT NULL DEFAULT '0',
  `filterable` tinyint(1) NOT NULL DEFAULT '0',
  `comparable` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`, `version_id`, `tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `configuration_group_translation`;
CREATE TABLE `configuration_group_translation` (
  `configuration_group_id` binary(16) NOT NULL,
  `configuration_group_tenant_id` binary(16) NOT NULL,
  `configuration_group_version_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `language_tenant_id` binary(16) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`configuration_group_id`, `configuration_group_version_id`, `configuration_group_tenant_id`, `language_id`, `language_tenant_id`),
  CONSTRAINT `configuration_group_translation_ibfk_1` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `configuration_group_translation_ibfk_2` FOREIGN KEY (`configuration_group_id`, `configuration_group_version_id`, `configuration_group_tenant_id`) REFERENCES `configuration_group` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `configuration_group_option`;
CREATE TABLE `configuration_group_option` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `configuration_group_id` binary(16) NOT NULL,
  `configuration_group_tenant_id` binary(16) NOT NULL,
  `configuration_group_version_id` binary(16) NOT NULL,
  `color_hex_code` VARCHAR(20) NULL DEFAULT NULL,
  `media_id` binary(16) NULL DEFAULT NULL,
  `media_tenant_id` binary(16) NULL DEFAULT NULL,
  `media_version_id` binary(16) NULL DEFAULT NULL,
  PRIMARY KEY (`id`, `version_id`, `tenant_id`),
  CONSTRAINT `fk_configuration_group_option.configuration_group_id` FOREIGN KEY (`configuration_group_id`, `configuration_group_version_id`, `configuration_group_tenant_id`) REFERENCES `configuration_group` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_configuration_group_option.media_id` FOREIGN KEY (`media_id`, `media_version_id`, `media_tenant_id`) REFERENCES `media` (`id`, `version_id`, `tenant_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `configuration_group_option_translation`;
CREATE TABLE `configuration_group_option_translation` (
  `configuration_group_option_id` binary(16) NOT NULL,
  `configuration_group_option_version_id` binary(16) NOT NULL,
  `configuration_group_option_tenant_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `language_tenant_id` binary(16) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`configuration_group_option_id`, `configuration_group_option_version_id`, `configuration_group_option_tenant_id`, `language_id`, `language_tenant_id`),
  CONSTRAINT `configuration_group_option_translation_ibfk_1` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `configuration_group_option_translation_ibfk_2` FOREIGN KEY (`configuration_group_option_id`, `configuration_group_option_version_id`, `configuration_group_option_tenant_id`) REFERENCES `configuration_group_option` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `product_datasheet`;
CREATE TABLE `product_datasheet` (
  `product_tenant_id` binary(16) NOT NULL,
  `product_id` binary(16) NOT NULL,
  `product_version_id` binary(16) NOT NULL,
  `configuration_group_option_id` binary(16) NOT NULL,
  `configuration_group_option_version_id` binary(16) NOT NULL,
  `configuration_group_option_tenant_id` binary(16) NOT NULL,
  PRIMARY KEY (`product_id`, `product_version_id`, `product_tenant_id`, `configuration_group_option_id`, `configuration_group_option_version_id`, `configuration_group_option_tenant_id`),
  CONSTRAINT `fk_product_datasheet.product_id` FOREIGN KEY (`product_id`, `product_version_id`, `product_tenant_id`) REFERENCES `product` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_datasheet.configuration_option_id` FOREIGN KEY (`configuration_group_option_id`, `configuration_group_option_version_id`, `configuration_group_option_tenant_id`) REFERENCES `configuration_group_option` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
);

DROP TABLE IF EXISTS `product_variation`;
CREATE TABLE `product_variation` (
  `product_id` binary(16) NOT NULL,
  `product_tenant_id` binary(16) NOT NULL,
  `product_version_id` binary(16) NOT NULL,
  `configuration_group_option_id` binary(16) NOT NULL,
  `configuration_group_option_tenant_id` binary(16) NOT NULL,
  `configuration_group_option_version_id` binary(16) NOT NULL,
  PRIMARY KEY (`product_id`, `product_version_id`, `product_tenant_id`, `configuration_group_option_id`, `configuration_group_option_version_id`, `configuration_group_option_tenant_id`),
  CONSTRAINT `fk_product_variation.product_id` FOREIGN KEY (`product_id`, `product_version_id`, `product_tenant_id`) REFERENCES `product` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_variation.configuration_group_option_id` FOREIGN KEY (`configuration_group_option_id`, `configuration_group_option_version_id`, `configuration_group_option_tenant_id`) REFERENCES `configuration_group_option` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
);

DROP TABLE IF EXISTS `product_configurator`;
CREATE TABLE `product_configurator` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `product_id` binary(16) NOT NULL,
  `product_tenant_id` binary(16) NOT NULL,
  `product_version_id` binary(16) NOT NULL,
  `configuration_group_option_id` binary(16) NOT NULL,
  `configuration_group_option_tenant_id` binary(16) NOT NULL,
  `configuration_group_option_version_id` binary(16) NOT NULL,
  `price` LONGTEXT NULL,
  `prices` LONGTEXT NULL DEFAULT NULL,
  PRIMARY KEY (`id`, `version_id`, `tenant_id`),
  CONSTRAINT `fk_product_configurator.product_id` FOREIGN KEY (`product_id`, `product_version_id`, `product_tenant_id`) REFERENCES `product` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_configurator.configuration_group_option_id` FOREIGN KEY (`configuration_group_option_id`, `configuration_group_option_version_id`, `configuration_group_option_tenant_id`) REFERENCES `configuration_group_option` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
);

DROP TABLE IF EXISTS `product_service`;
CREATE TABLE `product_service` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `product_id` binary(16) NOT NULL,
  `product_tenant_id` binary(16) NOT NULL,
  `product_version_id` binary(16) NOT NULL,
  `configuration_group_option_id` binary(16) NOT NULL,
  `configuration_group_option_tenant_id` binary(16) NOT NULL,
  `configuration_group_option_version_id` binary(16) NOT NULL,
  `tax_id` binary(16) NOT NULL,
  `tax_tenant_id` binary(16) NOT NULL,
  `tax_version_id` binary(16) NOT NULL,
  `price` LONGTEXT NULL,
  `prices` LONGTEXT NULL DEFAULT NULL,
  PRIMARY KEY (`id`, `version_id`, `tenant_id`),
  CONSTRAINT `fk_product_service.product_id` FOREIGN KEY (`product_id`, `product_version_id`, `product_tenant_id`) REFERENCES `product` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_service.configuration_group_option_id` FOREIGN KEY (`configuration_group_option_id`, `configuration_group_option_version_id`, `configuration_group_option_tenant_id`) REFERENCES `configuration_group_option` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_service.tax_id` FOREIGN KEY (`tax_id`, `tax_version_id`, `tax_tenant_id`) REFERENCES `tax` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
);

DROP TABLE IF EXISTS `country`;
CREATE TABLE `country` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `iso` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` int(11) NOT NULL DEFAULT '1',
  `shipping_free` tinyint(1) NOT NULL DEFAULT '0',
  `tax_free` tinyint(1) NOT NULL DEFAULT '0',
  `taxfree_for_vat_id` tinyint(1) NOT NULL DEFAULT '0',
  `taxfree_vatid_checked` tinyint(1) NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `iso3` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_state_in_registration` tinyint(1) NOT NULL DEFAULT '0',
  `force_state_in_registration` tinyint(1) NOT NULL DEFAULT '0',
  `country_area_id` binary(16) NULL,
  `country_area_tenant_id` binary(16) NULL,
  `country_area_version_id` binary(16) NULL,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`, `version_id`, `tenant_id`),
  CONSTRAINT `fk_area_country.country_area_id` FOREIGN KEY (`country_area_id`, `country_area_version_id`, `country_area_tenant_id`) REFERENCES `country_area` (`id`, `version_id`, `tenant_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `country_area`;
CREATE TABLE `country_area` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`, `version_id`, `tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `country_area_translation`;
CREATE TABLE `country_area_translation` (
  `country_area_id` binary(16) NOT NULL,
  `country_area_tenant_id` binary(16) NOT NULL,
  `country_area_version_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `language_tenant_id` binary(16) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`country_area_id`, `country_area_version_id`, `country_area_tenant_id`, `language_id`, `language_tenant_id`),
  CONSTRAINT `country_area_translation_ibfk_1` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `country_area_translation_ibfk_2` FOREIGN KEY (`country_area_id`, `country_area_version_id`, `country_area_tenant_id`) REFERENCES `country_area` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `country_state`;
CREATE TABLE `country_state` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `country_id` binary(16) NOT NULL,
  `country_tenant_id` binary(16) NOT NULL,
  `country_version_id` binary(16) NOT NULL,
  `short_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int(11) NOT NULL DEFAULT '1',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`, `version_id`, `tenant_id`),
  CONSTRAINT `fk_area_country_state.country_id` FOREIGN KEY (`country_id`, `country_version_id`, `country_tenant_id`) REFERENCES `country` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `country_state_translation`;
CREATE TABLE `country_state_translation` (
  `country_state_id` binary(16) NOT NULL,
  `country_state_tenant_id` binary(16) NOT NULL,
  `country_state_version_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `language_tenant_id` binary(16) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`country_state_id`, `country_state_version_id`, `country_state_tenant_id`, `language_id`, `language_tenant_id`),
  CONSTRAINT `country_state_translation_ibfk_1` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `country_state_translation_ibfk_2` FOREIGN KEY (`country_state_id`, `country_state_version_id`, `country_state_tenant_id`) REFERENCES `country_state` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `country_translation`;
CREATE TABLE `country_translation` (
  `country_id` binary(16) NOT NULL,
  `country_tenant_id` binary(16) NOT NULL,
  `country_version_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `language_tenant_id` binary(16) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`country_id`, `country_version_id`, `country_tenant_id`, `language_id`, `language_tenant_id`),
  CONSTRAINT `country_translation_ibfk_1` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `country_translation_ibfk_2` FOREIGN KEY (`country_id`, `country_version_id`, `country_tenant_id`) REFERENCES `country` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `currency`;
CREATE TABLE `currency` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `factor` double NOT NULL,
  `symbol` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `symbol_position` int(11) unsigned NOT NULL DEFAULT '0',
  `position` int(11) NOT NULL DEFAULT '1',
  `created_at` datetime,
  `updated_at` datetime,
   PRIMARY KEY (`id`, `version_id`, `tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `currency_translation`;
CREATE TABLE `currency_translation` (
  `currency_id` binary(16) NOT NULL,
  `currency_tenant_id` binary(16) NOT NULL,
  `currency_version_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `language_tenant_id` binary(16) NOT NULL,
  `short_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`currency_id`, `currency_version_id`, `currency_tenant_id`, `language_id`, `language_tenant_id`),
  CONSTRAINT `currency_translation_ibfk_1` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `currency_translation_ibfk_2` FOREIGN KEY (`currency_id`, `currency_version_id`, `currency_tenant_id`) REFERENCES `currency` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `customer`;
CREATE TABLE `customer` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `auto_increment` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_group_id` binary(16) NOT NULL,
  `customer_group_tenant_id` binary(16) NOT NULL,
  `customer_group_version_id` binary(16) NOT NULL,
  `default_payment_method_id` binary(16) NOT NULL,
  `default_payment_method_tenant_id` binary(16) NOT NULL,
  `default_payment_method_version_id` binary(16) NOT NULL,
  `touchpoint_id` binary(16) NOT NULL,
  `touchpoint_tenant_id` binary(16) NOT NULL,
  `last_payment_method_id` binary(16) DEFAULT NULL,
  `last_payment_method_tenant_id` binary(16) DEFAULT NULL,
  `last_payment_method_version_id` binary(16) DEFAULT NULL,
  `default_billing_address_id` binary(16) NOT NULL,
  `default_billing_address_tenant_id` binary(16) NOT NULL,
  `default_shipping_address_id` binary(16) NOT NULL,
  `default_shipping_address_tenant_id` binary(16) NOT NULL,
  `customer_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `salutation` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(1024) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(70) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `encoder` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'md5',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `account_mode` int(11) NOT NULL DEFAULT '0',
  `confirmation_key` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_login` date DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `session_id` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `newsletter` tinyint(1) NOT NULL DEFAULT '0',
  `validation` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `affiliate` tinyint(1) DEFAULT NULL,
  `referer` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `internal_comment` mediumtext COLLATE utf8mb4_unicode_ci,
  `failed_logins` int(11) NOT NULL DEFAULT '0',
  `locked_until` datetime DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`, `version_id`, `tenant_id`),
  UNIQUE `email` (`email`, `tenant_id`, `version_id`),
  UNIQUE `auto_increment` (`auto_increment`),
  KEY `sessionID` (`session_id`),
  KEY `firstlogin` (`first_login`),
  KEY `lastlogin` (`last_login`),
  KEY `validation` (`validation`),
  KEY `fk_customer.default_billing_address_id` (`default_billing_address_id`, `default_billing_address_tenant_id`),
  KEY `fk_customer.default_shipping_address_id` (`default_shipping_address_id`, `default_shipping_address_tenant_id`),
  CONSTRAINT `fk_customer.customer_group_id` FOREIGN KEY (`customer_group_id`, `customer_group_version_id`, `customer_group_tenant_id`) REFERENCES `customer_group` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_customer.default_payment_method_id` FOREIGN KEY (`default_payment_method_id`, `default_payment_method_version_id`, `default_payment_method_tenant_id`) REFERENCES `payment_method` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_customer.last_payment_method_id` FOREIGN KEY (`last_payment_method_id`, `last_payment_method_version_id`, `last_payment_method_tenant_id`) REFERENCES `payment_method` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_customer.touchpoint_id` FOREIGN KEY (`touchpoint_id`, `touchpoint_tenant_id`) REFERENCES `touchpoint` (`id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `customer_address`;
CREATE TABLE `customer_address` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `customer_id` binary(16) NOT NULL,
  `customer_tenant_id` binary(16) NOT NULL,
  `customer_version_id` binary(16) NOT NULL,
  `country_id` binary(16) NOT NULL,
  `country_tenant_id` binary(16) NOT NULL,
  `country_version_id` binary(16) NOT NULL,
  `country_state_id` binary(16) DEFAULT NULL,
  `country_state_tenant_id` binary(16) DEFAULT NULL,
  `country_state_version_id` binary(16) DEFAULT NULL,
  `company` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department` varchar(35) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `salutation` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `street` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zipcode` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `city` varchar(70) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vat_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_number` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `additional_address_line1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `additional_address_line2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime,
  `updated_at` datetime,
   PRIMARY KEY (`id`, `version_id`, `tenant_id`),
   CONSTRAINT `fk_customer_address.country_id` FOREIGN KEY (`country_id`, `country_version_id`, `country_tenant_id`) REFERENCES `country` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
   CONSTRAINT `fk_customer_address.country_state_id` FOREIGN KEY (`country_state_id`, `country_state_version_id`, `country_state_tenant_id`) REFERENCES `country_state` (`id`, `version_id`, `tenant_id`) ON DELETE SET NULL ON UPDATE CASCADE,
   CONSTRAINT `fk_customer_address.customer_id` FOREIGN KEY (`customer_id`, `customer_version_id`, `customer_tenant_id`) REFERENCES `customer` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `customer_group`;
CREATE TABLE `customer_group` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `display_gross` tinyint(1) NOT NULL DEFAULT '1',
  `input_gross` tinyint(1) NOT NULL DEFAULT '1',
  `has_global_discount` tinyint(1) NOT NULL DEFAULT '0',
  `percentage_global_discount` double DEFAULT NULL,
  `minimum_order_amount` double DEFAULT NULL,
  `minimum_order_amount_surcharge` double DEFAULT NULL,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`, `version_id`, `tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `customer_group_discount`;
CREATE TABLE `customer_group_discount` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `customer_group_id` binary(16) NOT NULL,
  `customer_group_tenant_id` binary(16) NOT NULL,
  `customer_group_version_id` binary(16) NOT NULL,
  `percentage_discount` double NOT NULL,
  `minimum_cart_amount` double NOT NULL,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`, `version_id`, `tenant_id`),
  CONSTRAINT `fk_customer_group_discount.customer_group_id` FOREIGN KEY (`customer_group_id`, `customer_group_version_id`, `customer_group_tenant_id`) REFERENCES `customer_group` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `customer_group_translation`;
CREATE TABLE `customer_group_translation` (
  `customer_group_id` binary(16) NOT NULL,
  `customer_group_tenant_id` binary(16) NOT NULL,
  `customer_group_version_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `language_tenant_id` binary(16) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`customer_group_id`, `customer_group_version_id`, `customer_group_tenant_id`, `language_id`, `language_tenant_id`),
  CONSTRAINT `customer_group_translation_ibfk_1` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `customer_group_translation_ibfk_2` FOREIGN KEY (`customer_group_id`, `customer_group_version_id`, `customer_group_tenant_id`) REFERENCES `customer_group` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `listing_facet`;
CREATE TABLE `listing_facet` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `active` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `unique_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_in_categories` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `deletable` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `position` int(11) NOT NULL DEFAULT '1',
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`, `version_id`, `tenant_id`),
  UNIQUE KEY `unique_identifier` (`unique_key`, `version_id`, `tenant_id`),
  KEY `sorting` (`display_in_categories`,`position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `listing_facet_translation`;
CREATE TABLE `listing_facet_translation` (
  `listing_facet_id` binary(16) NOT NULL,
  `listing_facet_tenant_id` binary(16) NOT NULL,
  `listing_facet_version_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `language_tenant_id` binary(16) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`listing_facet_id`, `listing_facet_version_id`, `listing_facet_tenant_id`, `language_id`, `language_tenant_id`),
  CONSTRAINT `listing_facet_translation_ibfk_1` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `listing_facet_translation_ibfk_2` FOREIGN KEY (`listing_facet_id`, `listing_facet_version_id`, `listing_facet_tenant_id`) REFERENCES `listing_facet` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `listing_sorting`;
CREATE TABLE `listing_sorting` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `active` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `unique_key` varchar(30) NOT NULL,
  `display_in_categories` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `position` int(11) NOT NULL DEFAULT '1',
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`, `version_id`, `tenant_id`),
  UNIQUE KEY `uniqueKey` (`unique_key`, `tenant_id`),
  KEY `sorting` (`display_in_categories`,`position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `listing_sorting_translation`;
CREATE TABLE `listing_sorting_translation` (
  `listing_sorting_id` binary(16) NOT NULL,
  `listing_sorting_tenant_id` binary(16) NOT NULL,
  `listing_sorting_version_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `language_tenant_id` binary(16) NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`listing_sorting_id`, `listing_sorting_version_id`, `listing_sorting_tenant_id`, `language_id`, `language_tenant_id`),
  CONSTRAINT `listing_sorting_translation_ibfk_1` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `listing_sorting_translation_ibfk_2` FOREIGN KEY (`listing_sorting_id`, `listing_sorting_version_id`, `listing_sorting_tenant_id`) REFERENCES `listing_sorting` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `locale`;
CREATE TABLE `locale` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`, `version_id`, `tenant_id`),
  UNIQUE KEY `locale` (`code`, `version_id`, `tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `locale_translation`;
CREATE TABLE `locale_translation` (
  `locale_id` binary(16) NOT NULL,
  `locale_tenant_id` binary(16) NOT NULL,
  `locale_version_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `language_tenant_id` binary(16) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `territory` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`locale_id`, `locale_version_id`, `locale_tenant_id`, `language_id`, `language_tenant_id`),
  CONSTRAINT `locale_translation_ibfk_1` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `locale_translation_ibfk_2` FOREIGN KEY (`locale_id`, `locale_version_id`, `locale_tenant_id`) REFERENCES `locale` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `media`;
CREATE TABLE `media` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `catalog_id` binary(16) NOT NULL,
  `catalog_tenant_id` binary(16) NOT NULL,
  `media_album_id` binary(16) NOT NULL,
  `media_album_tenant_id` binary(16) NOT NULL,
  `media_album_version_id` binary(16) NOT NULL,
  `user_id` binary(16) DEFAULT NULL,
  `user_tenant_id` binary(16) DEFAULT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int(10) unsigned NOT NULL,
  `meta_data` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime,
  `updated_at` datetime,
   PRIMARY KEY (`id`, `version_id`, `tenant_id`),
   KEY `path` (`file_name`),
   CONSTRAINT `fk_media.media_album_id` FOREIGN KEY (`media_album_id`, `media_album_version_id`, `media_album_tenant_id`) REFERENCES `media_album` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
   CONSTRAINT `fk_media.user_id` FOREIGN KEY (`user_id`, `user_tenant_id`) REFERENCES `user` (`id`, `tenant_id`) ON DELETE SET NULL ON UPDATE CASCADE,
   CONSTRAINT `fk_media.catalog_id` FOREIGN KEY (`catalog_id`, `catalog_tenant_id`) REFERENCES `catalog` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `media_album`;
CREATE TABLE `media_album` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `catalog_id` binary(16) NOT NULL,
  `catalog_tenant_id` binary(16) NOT NULL,
  `parent_id` binary(16) DEFAULT NULL,
  `parent_tenant_id` binary(16) DEFAULT NULL,
  `parent_version_id` binary(16) DEFAULT NULL,
  `position` int(11) NOT NULL DEFAULT '1',
  `create_thumbnails` tinyint(1) NOT NULL DEFAULT '0',
  `thumbnail_size` text COLLATE utf8mb4_unicode_ci,
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `thumbnail_high_dpi` tinyint(1) NOT NULL DEFAULT '1',
  `thumbnail_quality` int(11) DEFAULT NULL,
  `thumbnail_high_dpi_quality` int(11) DEFAULT NULL,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`, `version_id`, `tenant_id`),
  CONSTRAINT `fk_album.parent_id` FOREIGN KEY (`parent_id`, `parent_version_id`, `parent_tenant_id`) REFERENCES `media_album` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_album.catalog_id` FOREIGN KEY (`catalog_id`, `catalog_tenant_id`) REFERENCES `catalog` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `media_album_translation`;
CREATE TABLE `media_album_translation` (
  `media_album_id` binary(16) NOT NULL,
  `media_album_tenant_id` binary(16) NOT NULL,
  `media_album_version_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `language_tenant_id` binary(16) NOT NULL,
  `catalog_id` binary(16) NOT NULL,
  `catalog_tenant_id` binary(16) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`media_album_id`, `media_album_version_id`, `media_album_tenant_id`, `language_id`, `language_tenant_id`),
  CONSTRAINT `media_album_translation_ibfk_1` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `media_album_translation_ibfk_2` FOREIGN KEY (`media_album_id`, `media_album_version_id`, `media_album_tenant_id`) REFERENCES `media_album` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `media_album_translation_ibfk_3` FOREIGN KEY (`catalog_id`, `catalog_tenant_id`) REFERENCES `catalog` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `media_translation`;
CREATE TABLE `media_translation` (
  `media_id` binary(16) NOT NULL,
  `media_tenant_id` binary(16) NOT NULL,
  `media_version_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `language_tenant_id` binary(16) NOT NULL,
  `catalog_id` binary(16) NOT NULL,
  `catalog_tenant_id` binary(16) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`media_id`, `media_version_id`, `media_tenant_id`, `language_id`, `language_tenant_id`),
  CONSTRAINT `media_translation_ibfk_1` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `media_translation_ibfk_2` FOREIGN KEY (`media_id`, `media_version_id`, `media_tenant_id`) REFERENCES `media` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `media_translation_ibfk_3` FOREIGN KEY (`catalog_id`, `catalog_tenant_id`) REFERENCES `catalog` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `order`;
CREATE TABLE `order` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `auto_increment` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` binary(16) NOT NULL,
  `customer_tenant_id` binary(16) NOT NULL,
  `customer_version_id` binary(16) NOT NULL,
  `order_state_id` binary(16) NOT NULL,
  `order_state_tenant_id` binary(16) NOT NULL,
  `order_state_version_id` binary(16) NOT NULL,
  `payment_method_id` binary(16) NOT NULL,
  `payment_method_tenant_id` binary(16) NOT NULL,
  `payment_method_version_id` binary(16) NOT NULL,
  `currency_id` binary(16) NOT NULL,
  `currency_tenant_id` binary(16) NOT NULL,
  `currency_version_id` binary(16) NOT NULL,
  `touchpoint_id` binary(16) NOT NULL,
  `touchpoint_tenant_id` binary(16) NOT NULL,
  `billing_address_id` binary(16) NOT NULL,
  `billing_address_tenant_id` binary(16) NOT NULL,
  `billing_address_version_id` binary(16) NOT NULL,
  `order_date` datetime NOT NULL,
  `amount_total` double NOT NULL,
  `position_price` double NOT NULL,
  `shipping_total` double NOT NULL,
  `is_net` tinyint(1) NOT NULL,
  `is_tax_free` tinyint(1) NOT NULL,
  `created_at` datetime,
  `updated_at` datetime,
   PRIMARY KEY (`id`, `version_id`, `tenant_id`),
   UNIQUE `auto_increment` (`auto_increment`),
   CONSTRAINT `fk_order.billing_address_id` FOREIGN KEY (`billing_address_id`, `billing_address_version_id`, `billing_address_tenant_id`) REFERENCES `order_address` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
   CONSTRAINT `fk_order.currency_id` FOREIGN KEY (`currency_id`, `currency_version_id`, `currency_tenant_id`) REFERENCES `currency` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
   CONSTRAINT `fk_order.customer_id` FOREIGN KEY (`customer_id`, `customer_version_id`, `customer_tenant_id`) REFERENCES `customer` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
   CONSTRAINT `fk_order.order_state_id` FOREIGN KEY (`order_state_id`, `order_state_version_id`, `order_state_tenant_id`) REFERENCES `order_state` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
   CONSTRAINT `fk_order.payment_method_id` FOREIGN KEY (`payment_method_id`, `payment_method_version_id`, `payment_method_tenant_id`) REFERENCES `payment_method` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
   CONSTRAINT `fk_order.touchpoint_id` FOREIGN KEY (`touchpoint_id`, `touchpoint_tenant_id`) REFERENCES `touchpoint` (`id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `order_address`;
CREATE TABLE `order_address` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `country_id` binary(16) NOT NULL,
  `country_tenant_id` binary(16) NOT NULL,
  `country_version_id` binary(16) NOT NULL,
  `country_state_id` binary(16) DEFAULT NULL,
  `country_state_tenant_id` binary(16) DEFAULT NULL,
  `country_state_version_id` binary(16) DEFAULT NULL,
  `company` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department` varchar(35) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `salutation` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `street` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `zipcode` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `city` varchar(70) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vat_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_number` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `additional_address_line1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `additional_address_line2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`, `version_id`, `tenant_id`),
  CONSTRAINT `fk_order_address.country_id` FOREIGN KEY (`country_id`, `country_version_id`, `country_tenant_id`) REFERENCES `country` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_order_address.country_state_id` FOREIGN KEY (`country_state_id`, `country_state_version_id`, `country_state_tenant_id`) REFERENCES `country_state` (`id`, `version_id`, `tenant_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `order_delivery`;
CREATE TABLE `order_delivery` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `order_id` binary(16) NOT NULL,
  `order_tenant_id` binary(16) NOT NULL,
  `order_version_id` binary(16) NOT NULL,
  `shipping_address_id` binary(16) NOT NULL,
  `shipping_address_tenant_id` binary(16) NOT NULL,
  `shipping_address_version_id` binary(16) NOT NULL,
  `shipping_method_id` binary(16) NOT NULL,
  `shipping_method_tenant_id` binary(16) NOT NULL,
  `shipping_method_version_id` binary(16) NOT NULL,
  `order_state_id` binary(16) NOT NULL,
  `order_state_tenant_id` binary(16) NOT NULL,
  `order_state_version_id` binary(16) NOT NULL,
  `tracking_code` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_date_earliest` date NOT NULL,
  `shipping_date_latest` date NOT NULL,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`, `version_id`, `tenant_id`),
  CONSTRAINT `fk_order_delivery.order_id` FOREIGN KEY (`order_id`, `order_version_id`, `order_tenant_id`) REFERENCES `order` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_order_delivery.order_state_id` FOREIGN KEY (`order_state_id`, `order_state_version_id`, `order_state_tenant_id`) REFERENCES `order_state` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_order_delivery.shipping_address_id` FOREIGN KEY (`shipping_address_id`, `shipping_address_version_id`, `shipping_address_tenant_id`) REFERENCES `order_address` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_order_delivery.shipping_method_id` FOREIGN KEY (`shipping_method_id`, `shipping_method_version_id`, `shipping_method_tenant_id`) REFERENCES `shipping_method` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `order_delivery_position`;
CREATE TABLE `order_delivery_position` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `order_delivery_id` binary(16) NOT NULL,
  `order_delivery_tenant_id` binary(16) NOT NULL,
  `order_delivery_version_id` binary(16) NOT NULL,
  `order_line_item_id` binary(16) NOT NULL,
  `order_line_item_tenant_id` binary(16) NOT NULL,
  `order_line_item_version_id` binary(16) NOT NULL,
  `unit_price` double NOT NULL,
  `total_price` double NOT NULL,
  `quantity` double NOT NULL,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`, `version_id`, `tenant_id`),
  CONSTRAINT `fk_order_delivery_position.order_delivery_id` FOREIGN KEY (`order_delivery_id`, `order_delivery_version_id`, `order_delivery_tenant_id`) REFERENCES `order_delivery` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_order_delivery_position.order_line_item_id` FOREIGN KEY (`order_line_item_id`, `order_line_item_version_id`, `order_line_item_tenant_id`) REFERENCES `order_line_item` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `order_line_item`;
CREATE TABLE `order_line_item` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `order_id` binary(16) NOT NULL,
  `order_tenant_id` binary(16) NOT NULL,
  `order_version_id` binary(16) NOT NULL,
  `parent_id` binary(16) DEFAULT NULL,
  `parent_tenant_id` binary(16) DEFAULT NULL,
  `identifier` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` double NOT NULL,
  `total_price` double NOT NULL,
  `type` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`, `version_id`, `tenant_id`),
  CONSTRAINT `fk_order_line_item.order_id` FOREIGN KEY (`order_id`, `order_version_id`, `order_tenant_id`) REFERENCES `order` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `order_state`;
CREATE TABLE `order_state` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `position` int(11) NOT NULL DEFAULT '1',
  `has_mail` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`, `version_id`, `tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `order_state_translation`;
CREATE TABLE `order_state_translation` (
  `order_state_id` binary(16) NOT NULL,
  `order_state_tenant_id` binary(16) NOT NULL,
  `order_state_version_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `language_tenant_id` binary(16) NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`order_state_id`, `order_state_version_id`, `order_state_tenant_id`, `language_id`, `language_tenant_id`),
  CONSTRAINT `order_state_translation_ibfk_1` FOREIGN KEY (`language_id`, `order_state_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `order_state_translation_ibfk_2` FOREIGN KEY (`order_state_id`, `order_state_version_id`, `order_state_tenant_id`) REFERENCES `order_state` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `payment_method`;
CREATE TABLE `payment_method` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `technical_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `template` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `class` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `table` varchar(70) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hide` tinyint(1) NOT NULL DEFAULT '0',
  `percentage_surcharge` double DEFAULT NULL,
  `absolute_surcharge` double DEFAULT NULL,
  `surcharge_string` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` int(11) NOT NULL DEFAULT '1',
  `active` tinyint(1) NOT NULL DEFAULT '0',
  `allow_esd` tinyint(1) NOT NULL DEFAULT '0',
  `used_iframe` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hide_prospect` tinyint(1) NOT NULL DEFAULT '1',
  `action` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` int(11) DEFAULT NULL,
  `mobile_inactive` tinyint(1) NOT NULL DEFAULT '0',
  `risk_rules` longtext COLLATE utf8mb4_unicode_ci,
  `plugin_id` VARCHAR(250) DEFAULT NULL,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`, `version_id`, `tenant_id`),
  UNIQUE KEY `name` (`technical_name`, `version_id`, `tenant_id`),
  CONSTRAINT `fk_payment_method.plugin_id` FOREIGN KEY (`plugin_id`) REFERENCES `plugin` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `payment_method_translation`;
CREATE TABLE `payment_method_translation` (
  `payment_method_id` binary(16) NOT NULL,
  `payment_method_tenant_id` binary(16) NOT NULL,
  `payment_method_version_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `language_tenant_id` binary(16) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `additional_description` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`payment_method_id`, `payment_method_version_id`, `payment_method_tenant_id`, `language_id`, `language_tenant_id`),
  CONSTRAINT `payment_method_translation_ibfk_1` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `payment_method_translation_ibfk_2` FOREIGN KEY (`payment_method_id`, `payment_method_version_id`, `payment_method_tenant_id`) REFERENCES `payment_method` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `payment_token`;
CREATE TABLE `payment_token` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `token` varchar(255) NOT NULL,
  `payment_method_id` binary(16) NOT NULL,
  `payment_method_tenant_id` binary(16) NOT NULL,
  `payment_method_version_id` binary(16) NOT NULL,
  `order_transaction_id` binary(16) NOT NULL,
  `order_transaction_tenant_id` binary(16) NOT NULL,
  `order_transaction_version_id` binary(16) NOT NULL,
  `expires` datetime NOT NULL,
  PRIMARY KEY (`id`, `tenant_id`),
  UNIQUE KEY `token` (`token`, `tenant_id`),
  CONSTRAINT `fk_payment_token.payment_method_id` FOREIGN KEY (`payment_method_id`, `payment_method_version_id`, `payment_method_tenant_id`) REFERENCES `payment_method` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_payment_token.order_transaction_id` FOREIGN KEY (`order_transaction_id`, `order_transaction_tenant_id`, `order_transaction_version_id`) REFERENCES `order_transaction` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `plugin`;
CREATE TABLE `plugin` (
  `id` VARCHAR(250) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` mediumtext COLLATE utf8mb4_unicode_ci,
  `description_long` mediumtext COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) NOT NULL,
  `installation_date` datetime DEFAULT NULL,
  `update_date` datetime DEFAULT NULL,
  `refresh_date` datetime DEFAULT NULL,
  `author` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `copyright` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `version` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `support` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `changes` mediumtext COLLATE utf8mb4_unicode_ci,
  `link` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `store_version` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `store_date` datetime DEFAULT NULL,
  `capability_update` tinyint(1) NOT NULL,
  `capability_install` tinyint(1) NOT NULL,
  `capability_enable` tinyint(1) NOT NULL,
  `update_source` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `update_version` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `capability_secure_uninstall` tinyint(1) NOT NULL,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `product`;
CREATE TABLE `product` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `catalog_id` binary(16) NOT NULL,
  `catalog_tenant_id` binary(16) NOT NULL,
  `auto_increment` bigint unsigned NOT NULL AUTO_INCREMENT,
  `active` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `parent_id` binary(16) DEFAULT NULL,
  `parent_tenant_id` binary(16) DEFAULT NULL,
  `parent_version_id` binary(16) DEFAULT NULL,
  `tax_id` binary(16) DEFAULT NULL,
  `tax_tenant_id` binary(16) DEFAULT NULL,
  `tax_version_id` binary(16) DEFAULT NULL,
  `product_manufacturer_id` binary(16) DEFAULT NULL,
  `product_manufacturer_tenant_id` binary(16) DEFAULT NULL,
  `product_manufacturer_version_id` binary(16) DEFAULT NULL,
  `unit_id` binary(16) DEFAULT NULL,
  `unit_tenant_id` binary(16) DEFAULT NULL,
  `unit_version_id` binary(16) DEFAULT NULL,
  `category_tree` LONGTEXT DEFAULT NULL,
  `variation_ids` LONGTEXT DEFAULT NULL,
  `datasheet_ids` LONGTEXT DEFAULT NULL,
  `tax` binary(16) NULL,
  `manufacturer` binary(16) NULL,
  `unit` binary(16) NULL,
  `media` binary(16) NULL,
  `priceRules` binary(16) NULL,
  `services` binary(16) NULL,
  `datasheet` binary(16) NULL,
  `categories` binary(16) NULL,
  `translations` binary(16) NULL,
  `price` LONGTEXT DEFAULT NULL,
  `listing_prices` LONGTEXT DEFAULT NULL,
  `supplier_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ean` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stock` int(11) DEFAULT NULL,
  `min_delivery_time` int(11) DEFAULT NULL,
  `max_delivery_time` int(11) DEFAULT NULL,
  `restock_time` int(11) DEFAULT NULL,
  `is_closeout` tinyint(1) DEFAULT NULL,
  `min_stock` int(11) unsigned DEFAULT NULL,
  `purchase_steps` int(11) unsigned DEFAULT NULL,
  `max_purchase` int(11) unsigned DEFAULT NULL,
  `min_purchase` int(11) unsigned DEFAULT NULL,
  `purchase_unit` decimal(11,4) unsigned DEFAULT NULL,
  `reference_unit` decimal(10,3) unsigned DEFAULT NULL,
  `shipping_free` tinyint(4) DEFAULT NULL,
  `purchase_price` double DEFAULT NULL,
  `pseudo_sales` int(11) DEFAULT NULL,
  `mark_as_topseller` tinyint(1) unsigned DEFAULT NULL,
  `sales` int(11) DEFAULT NULL,
  `position` int(11) unsigned DEFAULT NULL,
  `weight` decimal(10,3) unsigned DEFAULT NULL,
  `width` decimal(10,3) unsigned DEFAULT NULL,
  `height` decimal(10,3) unsigned DEFAULT NULL,
  `length` decimal(10,3) unsigned DEFAULT NULL,
  `template` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `allow_notification` tinyint(1) unsigned DEFAULT NULL,
  `release_date` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime,
  CHECK (JSON_VALID(`category_tree`)),
  CHECK (JSON_VALID(`listing_prices`)),
  CHECK (JSON_VALID(`price`)),
  CHECK (JSON_VALID(`datasheet_ids`)),
  CHECK (JSON_VALID(`variation_ids`)),
  PRIMARY KEY (`id`, `version_id`, `tenant_id`),
  KEY `auto_increment` (`auto_increment`),
  CONSTRAINT `fk_product.product_manufacturer_id` FOREIGN KEY (`product_manufacturer_id`, `product_manufacturer_version_id`, `product_manufacturer_tenant_id`) REFERENCES `product_manufacturer` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_product.tax_id` FOREIGN KEY (`tax_id`, `tax_version_id`, `tax_tenant_id`) REFERENCES `tax` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_product.unit_id` FOREIGN KEY (`unit_id`, `unit_version_id`, `unit_tenant_id`) REFERENCES `unit` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_product.parent_id` FOREIGN KEY (`parent_id`, `parent_version_id`, `parent_tenant_id`) REFERENCES `product` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product.catalog_id` FOREIGN KEY (`catalog_id`, `catalog_tenant_id`) REFERENCES `catalog` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `product_price_rule`;
CREATE TABLE `product_price_rule` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `rule_id` binary(16) NOT NULL,
  `rule_tenant_id` binary(16) NOT NULL,
  `product_id` binary(16) NOT NULL,
  `product_tenant_id` binary(16) NOT NULL,
  `product_version_id` binary(16) NOT NULL,
  `currency_id` binary(16) NOT NULL,
  `currency_tenant_id` binary(16) NOT NULL,
  `currency_version_id` binary(16) NOT NULL,
  `price` LONGTEXT NOT NULL,
  `quantity_start` INT(11) NOT NULL,
  `quantity_end` INT(11) NULL DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id`, `version_id`, `tenant_id`),
  CONSTRAINT `fk_product_price_rule.product_id` FOREIGN KEY (`product_id`, `product_version_id`, `product_tenant_id`) REFERENCES `product` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_price_rule.currency_id` FOREIGN KEY (`currency_id`, `currency_version_id`, `currency_tenant_id`) REFERENCES `currency` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_price_rule.rule_id` FOREIGN KEY (`rule_id`, `rule_tenant_id`) REFERENCES `rule` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `product_category`;
CREATE TABLE `product_category` (
  `product_id` binary(16) NOT NULL,
  `product_tenant_id` binary(16) NOT NULL,
  `product_version_id` binary(16) NOT NULL,
  `category_id` binary(16) NOT NULL,
  `category_tenant_id` binary(16) NOT NULL,
  `category_version_id` binary(16) NOT NULL,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`product_id`, `product_version_id`, `product_tenant_id`, `category_id`, `category_version_id`, `category_tenant_id`),
  CONSTRAINT `fk_product_category.category_id` FOREIGN KEY (`category_id`, `category_version_id`, `category_tenant_id`) REFERENCES `category` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_category.product_id` FOREIGN KEY (`product_id`, `product_version_id`, `product_tenant_id`) REFERENCES `product` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `search_keyword`;
CREATE TABLE `search_keyword` (
  `tenant_id` binary(16) NOT NULL,
  `keyword` varchar(500) NOT NULL,
  `reversed` varchar(500) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `language_tenant_id` binary(16) NOT NULL,
  PRIMARY KEY `language_keyword` (`keyword`, `language_id`, `version_id`, `tenant_id`, `language_tenant_id`),
  CONSTRAINT `fk_search_keyword.language_id` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
);

DROP TABLE IF EXISTS `product_search_keyword`;
CREATE TABLE `product_search_keyword` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `keyword` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `language_id` binary(16) NOT NULL,
  `language_tenant_id` binary(16) NOT NULL,
  `product_id` binary(16) NOT NULL,
  `product_tenant_id` binary(16) NOT NULL,
  `product_version_id` binary(16) NOT NULL,
  `ranking` float NOT NULL,
  PRIMARY KEY (`id`, `version_id`, `tenant_id`),
  CONSTRAINT `fk_product_search_keyword.product_id` FOREIGN KEY (`product_id`, `product_version_id`, `product_tenant_id`) REFERENCES `product` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_search_keyword.language_id` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY (`language_id`, `keyword`, `product_id`, `ranking`, `version_id`, `tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `product_manufacturer`;
CREATE TABLE `product_manufacturer` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `catalog_id` binary(16) NOT NULL,
  `catalog_tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `link` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
  `media_id` binary(16) DEFAULT NULL,
  `media_tenant_id` binary(16) DEFAULT NULL,
  `media_version_id` binary(16) DEFAULT NULL,
  `created_at` datetime,
  `updated_at` datetime,
   PRIMARY KEY (`id`, `version_id`, `tenant_id`),
   CONSTRAINT `fk_product_manufacturer.media_id` FOREIGN KEY (`media_id`, `media_version_id`, `media_tenant_id`) REFERENCES `media` (`id`, `version_id`, `tenant_id`) ON DELETE SET NULL ON UPDATE CASCADE,
   CONSTRAINT `fk_product_manufacturer.catalog_id` FOREIGN KEY (`catalog_id`, `catalog_tenant_id`) REFERENCES `catalog` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `product_manufacturer_translation`;
CREATE TABLE `product_manufacturer_translation` (
  `product_manufacturer_id` binary(16) NOT NULL,
  `product_manufacturer_tenant_id` binary(16) NOT NULL,
  `product_manufacturer_version_id` binary(16) NOT NULL,
  `catalog_id` binary(16) NOT NULL,
  `catalog_tenant_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `language_tenant_id` binary(16) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_keywords` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`product_manufacturer_id`, `product_manufacturer_version_id`, `product_manufacturer_tenant_id`,`language_id`, `language_tenant_id`),
  CONSTRAINT `product_manufacturer_translation_ibfk_1` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `product_manufacturer_translation_ibfk_2` FOREIGN KEY (`product_manufacturer_id`, `product_manufacturer_version_id`, `product_manufacturer_tenant_id`) REFERENCES `product_manufacturer` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `product_manufacturer_translation_ibfk_3` FOREIGN KEY (`catalog_id`, `catalog_tenant_id`) REFERENCES `catalog` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `product_media`;
CREATE TABLE `product_media` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `catalog_id` binary(16) NOT NULL,
  `catalog_tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `is_cover` tinyint(1) NOT NULL,
  `position` int(11) NOT NULL DEFAULT '1',
  `product_id` binary(16) NOT NULL,
  `product_tenant_id` binary(16) NOT NULL,
  `product_version_id` binary(16) NOT NULL,
  `media_id` binary(16) NOT NULL,
  `media_tenant_id` binary(16) NOT NULL,
  `media_version_id` binary(16) NOT NULL,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`, `version_id`, `tenant_id`),
  CONSTRAINT `fk_product_media.media_id` FOREIGN KEY (`media_id`, `media_version_id`, `media_tenant_id`) REFERENCES `media` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_media.product_id` FOREIGN KEY (`product_id`, `product_version_id`, `product_tenant_id`) REFERENCES `product` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_media.catalog_id` FOREIGN KEY (`catalog_id`, `catalog_tenant_id`) REFERENCES `catalog` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `product_translation`;
CREATE TABLE `product_translation` (
  `product_id` binary(16) NOT NULL,
  `product_version_id` binary(16) NOT NULL,
  `product_tenant_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `language_tenant_id` binary(16) NOT NULL,
  `catalog_id` binary(16) NOT NULL,
  `catalog_tenant_id` binary(16) NOT NULL,
  `additional_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
  `keywords` mediumtext COLLATE utf8mb4_unicode_ci,
  `description` mediumtext COLLATE utf8mb4_unicode_ci,
  `description_long` mediumtext COLLATE utf8mb4_unicode_ci,
  `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pack_unit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`product_id`, `language_id`, `product_version_id`, `language_tenant_id`, `product_tenant_id`),
  CONSTRAINT `fk_product_trans.language_id` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_trans.product_id` FOREIGN KEY (`product_id`, `product_version_id`, `product_tenant_id`) REFERENCES `product` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_trans.catalog_id` FOREIGN KEY (`catalog_id`, `catalog_tenant_id`) REFERENCES `catalog` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `schema_version`;
CREATE TABLE `schema_version` (
  `version` varchar(14) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` datetime NOT NULL,
  `complete_date` datetime DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `error_msg` longtext COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `language`;
CREATE TABLE `language` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_id` binary(16) NULL DEFAULT NULL,
  `parent_tenant_id` binary(16) NULL DEFAULT NULL,
  `locale_id` binary(16) NOT NULL,
  `locale_tenant_id` binary(16) NOT NULL,
  `locale_version_id` binary(16) NOT NULL,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`, `tenant_id`),
  CONSTRAINT `fk_language.parent_id` FOREIGN KEY (`parent_id`, `parent_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_language.locale_id` FOREIGN KEY (`locale_id`, `locale_version_id`, `locale_tenant_id`) REFERENCES `locale` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `seo_url`;
CREATE TABLE `seo_url` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `touchpoint_id` binary(16) NOT NULL,
  `touchpoint_tenant_id` binary(16) NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `foreign_key` binary(16) NOT NULL,
  `foreign_key_version_id` binary(16) NOT NULL,
  `path_info` varchar(750) COLLATE 'utf8mb4_unicode_ci' NOT NULL,
  `seo_path_info` varchar(750) COLLATE 'utf8mb4_unicode_ci' NOT NULL,
  `is_canonical` tinyint(1) NOT NULL DEFAULT '0',
  `is_modified` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`, `version_id`, `tenant_id`),
  INDEX `seo_routing` (`version_id`, `touchpoint_id`, `seo_path_info`, `tenant_id`),
  INDEX `entity_canonical_url` (`touchpoint_id`, `foreign_key`, `name`, `is_canonical`, `tenant_id`),
  CONSTRAINT `fk_seo_url.touchpoint_id` FOREIGN KEY (`touchpoint_id`, `touchpoint_tenant_id`) REFERENCES `touchpoint` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `session`;
CREATE TABLE `session` (
  `id` varchar(128) COLLATE utf8mb4_bin NOT NULL,
  `data` mediumblob NOT NULL,
  `modified` int(10) unsigned NOT NULL,
  `expiry` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sess_expiry` (`expiry`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
  `sess_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sess_data` blob NOT NULL,
  `sess_time` int(10) unsigned NOT NULL,
  `sess_lifetime` mediumint(9) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `shipping_method`;
CREATE TABLE `shipping_method` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `type` int(11) unsigned NOT NULL,
  `active` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `position` int(11) NOT NULL DEFAULT '1',
  `calculation` int(1) unsigned NOT NULL DEFAULT '0',
  `surcharge_calculation` int(1) unsigned DEFAULT NULL,
  `tax_calculation` int(11) unsigned NOT NULL DEFAULT '0',
  `min_delivery_time` int(11) DEFAULT '1',
  `max_delivery_time` int(11) DEFAULT '2',
  `shipping_free` decimal(10,2) unsigned DEFAULT NULL,
  `bind_shippingfree` tinyint(1) NOT NULL,
  `bind_time_from` int(11) unsigned DEFAULT NULL,
  `bind_time_to` int(11) unsigned DEFAULT NULL,
  `bind_instock` tinyint(1) DEFAULT NULL,
  `bind_laststock` tinyint(1) NOT NULL,
  `bind_weekday_from` int(1) unsigned DEFAULT NULL,
  `bind_weekday_to` int(1) unsigned DEFAULT NULL,
  `bind_weight_from` decimal(10,3) DEFAULT NULL,
  `bind_weight_to` decimal(10,3) DEFAULT NULL,
  `bind_price_from` decimal(10,2) DEFAULT NULL,
  `bind_price_to` decimal(10,2) DEFAULT NULL,
  `bind_sql` mediumtext COLLATE utf8mb4_unicode_ci,
  `status_link` mediumtext COLLATE utf8mb4_unicode_ci,
  `calculation_sql` mediumtext COLLATE utf8mb4_unicode_ci,
  `created_at` datetime,
  `updated_at` datetime,
   PRIMARY KEY (`id`, `version_id`, `tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `shipping_method_price`;
CREATE TABLE `shipping_method_price` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `shipping_method_id` binary(16) NOT NULL,
  `shipping_method_tenant_id` binary(16) NOT NULL,
  `shipping_method_version_id` binary(16) NOT NULL,
  `quantity_from` decimal(10,3) unsigned NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `factor` decimal(10,2) NOT NULL,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`, `version_id`, `tenant_id`),
  UNIQUE KEY `shipping_method_uuid_quantity_from` (`shipping_method_id`, `quantity_from`, `version_id`, `tenant_id`),
  CONSTRAINT `fk_shipping_method_price.shipping_method_id` FOREIGN KEY (`shipping_method_id`, `shipping_method_version_id`, `shipping_method_tenant_id`) REFERENCES `shipping_method` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `shipping_method_translation`;
CREATE TABLE `shipping_method_translation` (
  `shipping_method_id` binary(16) NOT NULL,
  `shipping_method_version_id` binary(16) NOT NULL,
  `shipping_method_tenant_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `language_tenant_id` binary(16) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` mediumtext COLLATE utf8mb4_unicode_ci,
  `comment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`shipping_method_id`, `shipping_method_version_id`, `shipping_method_tenant_id`, `language_id`, `language_tenant_id`),
  CONSTRAINT `shipping_method_translation_ibfk_1` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `shipping_method_translation_ibfk_2` FOREIGN KEY (`shipping_method_id`, `shipping_method_version_id`, `shipping_method_tenant_id`) REFERENCES `shipping_method` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `shop`;
CREATE TABLE `shop` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `catalog_ids` LONGTEXT NOT NULL,
  `host` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `base_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `base_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shop_template_id` binary(16) NOT NULL,
  `shop_template_tenant_id` binary(16) NOT NULL,
  `shop_template_version_id` binary(16) NOT NULL,
  `document_template_id` binary(16) NOT NULL,
  `document_template_tenant_id` binary(16) NOT NULL,
  `document_template_version_id` binary(16) NOT NULL,
  `category_id` binary(16) NOT NULL,
  `category_tenant_id` binary(16) NOT NULL,
  `category_version_id` binary(16) NOT NULL,
  `locale_id` binary(16) NOT NULL,
  `locale_tenant_id` binary(16) NOT NULL,
  `locale_version_id` binary(16) NOT NULL,
  `currency_id` binary(16) NOT NULL,
  `currency_tenant_id` binary(16) NOT NULL,
  `currency_version_id` binary(16) NOT NULL,
  `customer_group_id` binary(16) NOT NULL,
  `customer_group_tenant_id` binary(16) NOT NULL,
  `customer_group_version_id` binary(16) NOT NULL,
  `fallback_translation_id` binary(16) DEFAULT NULL,
  `fallback_translation_tenant_id` binary(16) DEFAULT NULL,
  `fallback_translation_version_id` binary(16) DEFAULT NULL,
  `payment_method_id` binary(16) NOT NULL,
  `payment_method_tenant_id` binary(16) NOT NULL,
  `payment_method_version_id` binary(16) NOT NULL,
  `shipping_method_id` binary(16) NOT NULL,
  `shipping_method_tenant_id` binary(16) NOT NULL,
  `shipping_method_version_id` binary(16) NOT NULL,
  `country_id` binary(16) NOT NULL,
  `country_tenant_id` binary(16) NOT NULL,
  `country_version_id` binary(16) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` int(11) NOT NULL,
  `hosts` text COLLATE utf8mb4_unicode_ci,
  `is_secure` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `customer_scope` tinyint(1) NOT NULL DEFAULT '0',
  `is_default` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `tax_calculation_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'vertical',
  `created_at` datetime,
  `updated_at` datetime,
  CHECK (JSON_VALID(`catalog_ids`)),
  PRIMARY KEY (`id`, `version_id`, `tenant_id`),
  KEY `host` (`host`),
  CONSTRAINT `fk_shop.category_id` FOREIGN KEY (`category_id`, `category_version_id`, `category_tenant_id`) REFERENCES `category` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_shop.country_id` FOREIGN KEY (`country_id`, `country_version_id`, `country_tenant_id`) REFERENCES `country` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_shop.currency_id` FOREIGN KEY (`currency_id`, `currency_version_id`, `currency_tenant_id`) REFERENCES `currency` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_shop.customer_group_id` FOREIGN KEY (`customer_group_id`, `customer_group_version_id`, `customer_group_tenant_id`) REFERENCES `customer_group` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_shop.document_template_id` FOREIGN KEY (`document_template_id`, `document_template_version_id`, `document_template_tenant_id`) REFERENCES `shop_template` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_shop.fallback_translation_id` FOREIGN KEY (`fallback_translation_id`, `fallback_translation_version_id`, `fallback_translation_tenant_id`) REFERENCES `shop` (`id`, `version_id`, `tenant_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_shop.locale_id` FOREIGN KEY (`locale_id`, `locale_version_id`, `locale_tenant_id`) REFERENCES `locale` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_shop.payment_method_id` FOREIGN KEY (`payment_method_id`, `payment_method_version_id`, `payment_method_tenant_id`) REFERENCES `payment_method` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_shop.shipping_method_id` FOREIGN KEY (`shipping_method_id`, `shipping_method_version_id`, `shipping_method_tenant_id`) REFERENCES `shipping_method` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_shop.shop_template_id` FOREIGN KEY (`shop_template_id`, `shop_template_version_id`, `shop_template_tenant_id`) REFERENCES `shop_template` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `shop_template`;
CREATE TABLE `shop_template` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `template` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `author` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `esi` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `style_support` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `version` int(11) unsigned NOT NULL DEFAULT '0',
  `emotion` tinyint(1) unsigned NOT NULL,
  `plugin_id` VARCHAR(250) DEFAULT NULL,
  `parent_id` binary(16) DEFAULT NULL,
  `parent_tenant_id` binary(16) DEFAULT NULL,
  `parent_version_id` binary(16) DEFAULT NULL,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`, `version_id`, `tenant_id`),
  UNIQUE KEY `basename` (`template`, `version_id`, `tenant_id`),
  CONSTRAINT `fk_shop_template.parent_id` FOREIGN KEY (`parent_id`, `parent_version_id`, `parent_tenant_id`) REFERENCES `shop_template` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_shop_template.plugin_id` FOREIGN KEY (`plugin_id`) REFERENCES `plugin` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `shop_template_config_form`;
CREATE TABLE `shop_template_config_form` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `options` text COLLATE utf8mb4_unicode_ci,
  `parent_id` binary(16) DEFAULT NULL,
  `parent_tenant_id` binary(16) DEFAULT NULL,
  `parent_version_id` binary(16) DEFAULT NULL,
  `shop_template_id` binary(16) NOT NULL,
  `shop_template_tenant_id` binary(16) NOT NULL,
  `shop_template_version_id` binary(16) NOT NULL,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`, `version_id`, `tenant_id`),
  CONSTRAINT `fk_shop_template_config_form.parent_id` FOREIGN KEY (`parent_id`, `parent_version_id`, `parent_tenant_id`) REFERENCES `shop_template_config_form` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_shop_template_config_form.shop_template_id` FOREIGN KEY (`shop_template_id`, `shop_template_version_id`, `shop_template_tenant_id`) REFERENCES `shop_template` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `shop_template_config_form_field`;
CREATE TABLE `shop_template_config_form_field` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `shop_template_id` binary(16) NOT NULL,
  `shop_template_tenant_id` binary(16) NOT NULL,
  `shop_template_version_id` binary(16) NOT NULL,
  `shop_template_config_form_id` binary(16) NOT NULL,
  `shop_template_config_form_tenant_id` binary(16) NOT NULL,
  `shop_template_config_form_version_id` binary(16) NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int(11) NOT NULL DEFAULT '0',
  `default_value` text COLLATE utf8mb4_unicode_ci,
  `selection` text COLLATE utf8mb4_unicode_ci,
  `field_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `support_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `allow_blank` tinyint(1) NOT NULL DEFAULT '1',
  `attributes` text COLLATE utf8mb4_unicode_ci,
  `less_compatible` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`, `version_id`, `tenant_id`),
  UNIQUE KEY `template_id_name` (`name`, `version_id`, `tenant_id`),
  CONSTRAINT `fk_shop_template_cff.shop_template_config_form_id` FOREIGN KEY (`shop_template_config_form_id`, `shop_template_config_form_version_id`, `shop_template_config_form_tenant_id`) REFERENCES `shop_template_config_form` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_shop_template_config_form_field.shop_template_id` FOREIGN KEY (`shop_template_id`, `shop_template_version_id`, `shop_template_tenant_id`) REFERENCES `shop_template` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `shop_template_config_form_field_value`;
CREATE TABLE `shop_template_config_form_field_value` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `shop_template_config_form_field_id` binary(16) NOT NULL,
  `shop_template_config_form_field_tenant_id` binary(16) NOT NULL,
  `shop_template_config_form_field_version_id` binary(16) NOT NULL,
  `shop_id` binary(16) NOT NULL,
  `shop_tenant_id` binary(16) NOT NULL,
  `shop_version_id` binary(16) NOT NULL,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`, `version_id`, `tenant_id`),
  CONSTRAINT `fk_shop_template_cffv.shop_id` FOREIGN KEY (`shop_id`, `shop_version_id`, `shop_tenant_id`) REFERENCES `shop` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_shop_template_cffv.shop_template_config_form_field_id` FOREIGN KEY (`shop_template_config_form_field_id`, `shop_template_config_form_field_version_id`, `shop_template_config_form_field_tenant_id`) REFERENCES `shop_template_config_form_field` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `shop_template_config_preset`;
CREATE TABLE `shop_template_config_preset` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `element_values` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `shop_template_id` binary(16) NOT NULL,
  `shop_template_tenant_id` binary(16) NOT NULL,
  `shop_template_version_id` binary(16) NOT NULL,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`, `version_id`, `tenant_id`),
  CONSTRAINT `fk_shop_template_config_preset.shop_template_id` FOREIGN KEY (`shop_template_id`, `shop_template_version_id`, `shop_template_tenant_id`) REFERENCES `shop_template` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `snippet`;
CREATE TABLE `snippet` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `language_tenant_id` binary(16) NOT NULL,
  `translation_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`, `tenant_id`),
  UNIQUE (`tenant_id`, `language_id`, `translation_key`),
  CONSTRAINT `fk_snippet.language_id` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `tax`;
CREATE TABLE `tax` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `tax_rate` decimal(10,2) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`, `version_id`, `tenant_id`),
  KEY `tax` (`tax_rate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `tax_area_rule`;
CREATE TABLE `tax_area_rule` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `tax_rate` decimal(10,2) NOT NULL,
  `active` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `country_area_id` binary(16) DEFAULT NULL,
  `country_area_tenant_id` binary(16) DEFAULT NULL,
  `country_area_version_id` binary(16) DEFAULT NULL,
  `country_id` binary(16) DEFAULT NULL,
  `country_tenant_id` binary(16) DEFAULT NULL,
  `country_version_id` binary(16) DEFAULT NULL,
  `country_state_id` binary(16) DEFAULT NULL,
  `country_state_tenant_id` binary(16) DEFAULT NULL,
  `country_state_version_id` binary(16) DEFAULT NULL,
  `tax_id` binary(16) NOT NULL,
  `tax_tenant_id` binary(16) NOT NULL,
  `tax_version_id` binary(16) NOT NULL,
  `customer_group_id` binary(16) NOT NULL,
  `customer_group_tenant_id` binary(16) NOT NULL,
  `customer_group_version_id` binary(16) NOT NULL,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`, `version_id`, `tenant_id`),
  CONSTRAINT `fk_tax_area_rule.country_area_id` FOREIGN KEY (`country_area_id`, `country_area_version_id`, `country_area_tenant_id`) REFERENCES `country_area` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tax_area_rule.country_id` FOREIGN KEY (`country_id`, `country_version_id`, `country_tenant_id`) REFERENCES `country` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tax_area_rule.country_state_id` FOREIGN KEY (`country_state_id`, `country_state_version_id`, `country_state_tenant_id`) REFERENCES `country_state` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tax_area_rule.customer_group_id` FOREIGN KEY (`customer_group_id`, `customer_group_version_id`, `customer_group_tenant_id`) REFERENCES `customer_group` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tax_area_rule.tax_id` FOREIGN KEY (`tax_id`, `tax_version_id`, `tax_tenant_id`) REFERENCES `tax` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `tax_area_rule_translation`;
CREATE TABLE `tax_area_rule_translation` (
  `tax_area_rule_id` binary(16) NOT NULL,
  `tax_area_rule_version_id` binary(16) NOT NULL,
  `tax_area_rule_tenant_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `language_tenant_id` binary(16) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`tax_area_rule_id`, `tax_area_rule_version_id`, `tax_area_rule_tenant_id`,`language_id`, `language_tenant_id`),
  CONSTRAINT `tax_area_rule_translation_ibfk_1` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `tax_area_rule_translation_ibfk_2` FOREIGN KEY (`tax_area_rule_id`, `tax_area_rule_version_id`, `tax_area_rule_tenant_id`) REFERENCES `tax_area_rule` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `order_transaction_state` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `position` int(11) NOT NULL,
  `has_mail` tinyint NOT NULL,
  `created_at` datetime,
  `updated_at` datetime,
   PRIMARY KEY (`id`, `version_id`, `tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `order_transaction_state_translation` (
  `order_transaction_state_id` binary(16) NOT NULL,
  `order_transaction_state_tenant_id` binary(16) NOT NULL,
  `order_transaction_state_version_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `language_tenant_id` binary(16) NOT NULL,
  `language_version_id` binary(16) NOT NULL,
  `description` varchar(255) NOT NULL,
  PRIMARY KEY (`order_transaction_state_id`, `language_id`, `order_transaction_state_tenant_id`, `language_tenant_id`),
  CONSTRAINT `order_transaction_state_translation_ibfk_1` FOREIGN KEY (`language_id`, `language_version_id`, `language_tenant_id`) REFERENCES `shop` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `order_transaction_state_translation_ibfk_2` FOREIGN KEY (`order_transaction_state_id`, `order_transaction_state_version_id`, `order_transaction_state_tenant_id`) REFERENCES `order_transaction_state` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `order_transaction`;
CREATE TABLE `order_transaction` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `order_id` binary(16) NOT NULL,
  `order_tenant_id` binary(16) NOT NULL,
  `order_version_id` binary(16) NOT NULL,
  `payment_method_id` binary(16) NOT NULL,
  `payment_method_tenant_id` binary(16) NOT NULL,
  `payment_method_version_id` binary(16) NOT NULL,
  `order_transaction_state_id` binary(16) NOT NULL,
  `order_transaction_state_tenant_id` binary(16) NOT NULL,
  `order_transaction_state_version_id` binary(16) NOT NULL,
  `amount` longtext NOT NULL,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`, `version_id`, `tenant_id`),
  CONSTRAINT `fk_order_transaction.order_id` FOREIGN KEY (`order_id`, `order_version_id`, `order_tenant_id`) REFERENCES `order` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_order_transaction.payment_method_id` FOREIGN KEY (`payment_method_id`, `payment_method_version_id`, `payment_method_tenant_id`) REFERENCES `payment_method` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_order_transaction.order_transaction_state_id` FOREIGN KEY (`order_transaction_state_id`, `order_transaction_state_version_id`, `order_transaction_state_tenant_id`) REFERENCES `order_transaction_state` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `unit`;
CREATE TABLE `unit` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`, `version_id`, `tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `unit_translation`;
CREATE TABLE `unit_translation` (
  `unit_id` binary(16) NOT NULL,
  `unit_version_id` binary(16) NOT NULL,
  `unit_tenant_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `language_tenant_id` binary(16) NOT NULL,
  `short_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`unit_id`,`language_id`, `unit_version_id`, `language_tenant_id`, `unit_tenant_id`),
  CONSTRAINT `unit_translation_ibfk_1` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `unit_translation_ibfk_2` FOREIGN KEY (`unit_id`, `unit_version_id`, `unit_tenant_id`) REFERENCES `unit` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `user`;
CREATE TABLE `user` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_login` datetime DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '0',
  `failed_logins` int(11) NOT NULL DEFAULT '0',
  `locked_until` datetime DEFAULT NULL,
  `locale_id` binary(16) NOT NULL,
  `locale_version_id` binary(16) NOT NULL,
  `locale_tenant_id` binary(16) NOT NULL,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`, `tenant_id`),
  CONSTRAINT `fk_user.locale_id` FOREIGN KEY (`locale_id`, `locale_version_id`, `locale_tenant_id`) REFERENCES `locale` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_access_key`;
CREATE TABLE `user_access_key` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `user_id` binary(16) NOT NULL,
  `user_tenant_id` binary(16) NOT NULL,
  `write_access` tinyint(1) NOT NULL,
  `access_key` varchar(255) NOT NULL,
  `secret_access_key` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL,
  `last_usage_at` datetime NULL,
  PRIMARY KEY (`id`, `tenant_id`),
  INDEX `user_id_user_tenant_id` (`user_id`, `user_tenant_id`),
  INDEX `access_key` (`access_key`),
  CONSTRAINT `fk_user_access_key.user_id` FOREIGN KEY (`user_id`, `user_tenant_id`) REFERENCES `user` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `rule`;
CREATE TABLE `rule` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `name` varchar(500) NOT NULL,
  `priority` int(11) NOT NULL,
  `payload` longtext NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id`, `tenant_id`),
  CHECK (JSON_VALID (`payload`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `discount_surcharge`;
CREATE TABLE `discount_surcharge` (
  `id` BINARY(16) NOT NULL,
  `tenant_id` BINARY(16) NOT NULL,
  `rule_id` BINARY(16) NOT NULL,
  `rule_tenant_id` BINARY(16) NOT NULL,
  `filter_rule` LONGTEXT NOT NULL,
  `type` VARCHAR(255),
  `amount` FLOAT,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
   PRIMARY KEY (`id`, `tenant_id`),
   CHECK (JSON_VALID (`filter_rule`)),
   CONSTRAINT `fk_discount_surcharge.rule_id` FOREIGN KEY (`rule_id`, rule_tenant_id) REFERENCES `rule` (`id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `discount_surcharge_translation`;
CREATE TABLE `discount_surcharge_translation` (
  `discount_surcharge_id` BINARY(16) NOT NULL,
  `discount_surcharge_tenant_id` BINARY(16) NOT NULL,
  `language_id` BINARY(16) NOT NULL,
  `language_tenant_id` BINARY(16) NOT NULL,
  `name` VARCHAR(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`discount_surcharge_id`, `discount_surcharge_tenant_id`, `language_id`, `language_tenant_id`),
  CONSTRAINT `discount_surcharge_translation_ibfk_1` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `discount_surcharge_translation_ibfk_2` FOREIGN KEY (`discount_surcharge_id`, `discount_surcharge_tenant_id`) REFERENCES `discount_surcharge` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `version`;
CREATE TABLE `version` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`, `tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `version_commit`;
CREATE TABLE `version_commit` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `auto_increment` bigint NOT NULL AUTO_INCREMENT UNIQUE,
  `is_merge` TINYINT(1) NOT NULL DEFAULT 0,
  `message` varchar(5000) NULL DEFAULT NULL,
  `user_id` binary(16) DEFAULT NULL,
  `user_tenant_id` binary(16) DEFAULT NULL,
  `integration_id` binary(16) DEFAULT NULL,
  `integration_tenant_id` binary(16) DEFAULT NULL,
  `version_id` binary(16) NOT NULL,
  `version_tenant_id` binary(16) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`, `tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `version_commit_data`;
CREATE TABLE `version_commit_data` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `auto_increment` bigint NOT NULL AUTO_INCREMENT UNIQUE,
  `version_commit_id` binary(16) NOT NULL,
  `version_commit_tenant_id` binary(16) NOT NULL,
  `entity_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` LONGTEXT NOT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `user_id` binary(16) DEFAULT NULL,
  `user_tenant_id` binary(16) DEFAULT NULL,
  `integration_id` binary(16) DEFAULT NULL,
  `integration_tenant_id` binary(16) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  CHECK (JSON_VALID (`entity_id`)),
  CHECK (JSON_VALID (`payload`)),
  PRIMARY KEY (`id`, `tenant_id`),
  FOREIGN KEY (`version_commit_id`, `version_commit_tenant_id`) REFERENCES `version_commit` (`id`, `tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `catalog`;
CREATE TABLE `catalog` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` datetime,
  `updated_at` datetime,
  PRIMARY KEY (`id`, `tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `storefront_api_context`;
CREATE TABLE `storefront_api_context` (
  `tenant_id` binary(16) NOT NULL,
  `token` binary(16) NOT NULL,
  `payload` LONGTEXT NOT NULL,
  PRIMARY KEY (`token`, `tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `product_category_tree`;
CREATE TABLE `product_category_tree` (
  `product_id` binary(16) NOT NULL,
  `product_tenant_id` binary(16) NOT NULL,
  `product_version_id` binary(16) NOT NULL,
  `category_id` binary(16) NOT NULL,
  `category_tenant_id` binary(16) NOT NULL,
  `category_version_id` binary(16) NOT NULL,
  PRIMARY KEY (`product_id`, `product_version_id`, `product_tenant_id`, `category_id`, `category_version_id`, `category_tenant_id`),
  CONSTRAINT `product_category_tree_ibfk_1` FOREIGN KEY (`product_id`, `product_version_id`, `product_tenant_id`) REFERENCES `product` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `product_category_tree_ibfk_2` FOREIGN KEY (`category_id`, `category_version_id`, `category_tenant_id`) REFERENCES `category` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `integration`;
CREATE TABLE `integration` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NOT NULL,
  `write_access` tinyint(1) NOT NULL,
  `access_key` varchar(255) NOT NULL,
  `secret_access_key` varchar(255) NOT NULL,
  `label` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL,
  `last_usage_at` datetime NULL,
  PRIMARY KEY (`id`, `tenant_id`),
  INDEX `access_key` (`access_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;