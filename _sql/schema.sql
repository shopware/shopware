SET NAMES utf8mb4;

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `cart`;
CREATE TABLE `cart` (
  `token` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `container` json NOT NULL,
  `calculated` json NOT NULL,
  `price` float NOT NULL,
  `line_item_count` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `currency_id` binary(16) NOT NULL,
  `shipping_method_id` binary(16) NOT NULL,
  `payment_method_id` binary(16) NOT NULL,
  `country_id` binary(16) NOT NULL,
  `customer_id` binary(16) DEFAULT NULL,
  `shop_id` binary(16) NOT NULL,
  UNIQUE KEY `token` (`token`),
  KEY `fk_cart.currency_id` (`currency_id`),
  KEY `fk_cart.payment_method_id` (`payment_method_id`),
  KEY `fk_cart.customer_id` (`customer_id`),
  KEY `fk_cart.shipping_method_id` (`shipping_method_id`),
  KEY `fk_cart.country_id` (`country_id`),
  KEY `fk_cart.shop_id` (`shop_id`),
  CONSTRAINT `fk_cart.country_id` FOREIGN KEY (`country_id`) REFERENCES `country` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cart.currency_id` FOREIGN KEY (`currency_id`) REFERENCES `currency` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cart.customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cart.payment_method_id` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_method` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cart.shipping_method_id` FOREIGN KEY (`shipping_method_id`) REFERENCES `shipping_method` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cart.shop_id` FOREIGN KEY (`shop_id`) REFERENCES `shop` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `category`;
CREATE TABLE `category` (
  `id` binary(16) NOT NULL,
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
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `parent_id` binary(16) DEFAULT NULL,
  `media_id` binary(16) DEFAULT NULL,
  `product_stream_id` binary(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `position` (`position`),
  KEY `level` (`level`),
  KEY `active_query_builder` (`position`),
  KEY `fk_category.product_stream_id` (`product_stream_id`),
  KEY `fk_category.media_id` (`media_id`),
  KEY `fk_category.parent_id` (`parent_id`),
  CONSTRAINT `fk_category.media_id` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_category.parent_id` FOREIGN KEY (`parent_id`) REFERENCES `category` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_category.product_stream_id` FOREIGN KEY (`product_stream_id`) REFERENCES `product_stream` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `category_translation`;
CREATE TABLE `category_translation` (
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `path_names` longtext COLLATE utf8mb4_unicode_ci,
  `meta_keywords` mediumtext COLLATE utf8mb4_unicode_ci,
  `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description` mediumtext COLLATE utf8mb4_unicode_ci,
  `cms_headline` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cms_description` mediumtext COLLATE utf8mb4_unicode_ci,
  `category_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  PRIMARY KEY (`category_id`,`language_id`),
  KEY `language_id` (`language_id`),
  CONSTRAINT `category_translation_ibfk_1` FOREIGN KEY (`language_id`) REFERENCES `shop` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `category_translation_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `config_form`;
CREATE TABLE `config_form` (
  `id` binary(16) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int(11) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `parent_id` binary(16) DEFAULT NULL,
  `plugin_id` VARCHAR(250) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `fk_config_form.parent_id` (`parent_id`),
  KEY `fk_config_form.plugin_id` (`plugin_id`),
  CONSTRAINT `fk_config_form.parent_id` FOREIGN KEY (`parent_id`) REFERENCES `config_form` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_config_form.plugin_id` FOREIGN KEY (`plugin_id`) REFERENCES `plugin` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `config_form_field`;
CREATE TABLE `config_form_field` (
  `id` binary(16) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `required` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `position` int(11) NOT NULL DEFAULT '1',
  `scope` int(11) unsigned NOT NULL DEFAULT '0',
  `options` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `config_form_id` binary(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `form_id_2` (`name`),
  KEY `fk_config_form_field.config_form_id` (`config_form_id`),
  CONSTRAINT `fk_config_form_field.config_form_id` FOREIGN KEY (`config_form_id`) REFERENCES `config_form` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `config_form_field_translation`;
CREATE TABLE `config_form_field_translation` (
  `id` binary(16) NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `config_form_field_id` binary(16) NOT NULL,
  `locale_id` binary(16) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_config_form_field_translation.config_form_field_id` (`config_form_field_id`),
  KEY `fk_config_form_field_translation.locale_id` (`locale_id`),
  CONSTRAINT `fk_config_form_field_translation.config_form_field_id` FOREIGN KEY (`config_form_field_id`) REFERENCES `config_form_field` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_config_form_field_translation.locale_id` FOREIGN KEY (`locale_id`) REFERENCES `locale` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `config_form_field_value`;
CREATE TABLE `config_form_field_value` (
  `id` binary(16) NOT NULL,
  `value` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `config_form_field_id` binary(16) NOT NULL,
  `shop_id` binary(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_config_form_field_value.config_form_field_id` (`config_form_field_id`),
  KEY `fk_config_form_field_value.shop_id` (`shop_id`),
  CONSTRAINT `fk_config_form_field_value.config_form_field_id` FOREIGN KEY (`config_form_field_id`) REFERENCES `config_form_field` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_config_form_field_value.shop_id` FOREIGN KEY (`shop_id`) REFERENCES `shop` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `config_form_translation`;
CREATE TABLE `config_form_translation` (
  `id` binary(16) NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `config_form_id` binary(16) NOT NULL,
  `locale_id` binary(16) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_config_form_translation.config_form_id` (`config_form_id`),
  KEY `fk_config_form_translation.locale_id` (`locale_id`),
  CONSTRAINT `fk_config_form_translation.config_form_id` FOREIGN KEY (`config_form_id`) REFERENCES `config_form` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_config_form_translation.locale_id` FOREIGN KEY (`locale_id`) REFERENCES `locale` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `country`;
CREATE TABLE `country` (
  `id` binary(16) NOT NULL,
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
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `country_area_id` binary(16) NULL,
  PRIMARY KEY (`id`),
  KEY `fk_area_country.country_area_id` (`country_area_id`),
  CONSTRAINT `fk_area_country.country_area_id` FOREIGN KEY (`country_area_id`) REFERENCES `country_area` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `country_area`;
CREATE TABLE `country_area` (
  `id` binary(16) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `country_area_translation`;
CREATE TABLE `country_area_translation` (
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_area_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  PRIMARY KEY (`country_area_id`,`language_id`),
  KEY `language_id` (`language_id`),
  CONSTRAINT `country_area_translation_ibfk_1` FOREIGN KEY (`language_id`) REFERENCES `shop` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `country_area_translation_ibfk_2` FOREIGN KEY (`country_area_id`) REFERENCES `country_area` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `country_state`;
CREATE TABLE `country_state` (
  `id` binary(16) NOT NULL,
  `short_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int(11) NOT NULL DEFAULT '1',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `country_id` binary(16) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_area_country_state.country_id` (`country_id`),
  CONSTRAINT `fk_area_country_state.country_id` FOREIGN KEY (`country_id`) REFERENCES `country` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `country_state_translation`;
CREATE TABLE `country_state_translation` (
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_state_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  PRIMARY KEY (`country_state_id`,`language_id`),
  KEY `language_id` (`language_id`),
  CONSTRAINT `country_state_translation_ibfk_1` FOREIGN KEY (`language_id`) REFERENCES `shop` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `country_state_translation_ibfk_2` FOREIGN KEY (`country_state_id`) REFERENCES `country_state` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `country_translation`;
CREATE TABLE `country_translation` (
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  PRIMARY KEY (`country_id`,`language_id`),
  KEY `language_id` (`language_id`),
  CONSTRAINT `country_translation_ibfk_1` FOREIGN KEY (`language_id`) REFERENCES `shop` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `country_translation_ibfk_2` FOREIGN KEY (`country_id`) REFERENCES `country` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `currency`;
CREATE TABLE `currency` (
  `id` binary(16) NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `factor` double NOT NULL,
  `symbol` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `symbol_position` int(11) unsigned NOT NULL DEFAULT '0',
  `position` int(11) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `currency_translation`;
CREATE TABLE `currency_translation` (
  `short_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `currency_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  PRIMARY KEY (`currency_id`,`language_id`),
  KEY `language_id` (`language_id`),
  CONSTRAINT `currency_translation_ibfk_1` FOREIGN KEY (`language_id`) REFERENCES `shop` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `currency_translation_ibfk_2` FOREIGN KEY (`currency_id`) REFERENCES `currency` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `customer`;
CREATE TABLE `customer` (
  `id` binary(16) NOT NULL,
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
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `customer_group_id` binary(16) NOT NULL,
  `default_payment_method_id` binary(16) NOT NULL,
  `shop_id` binary(16) NOT NULL,
  `last_payment_method_id` binary(16) DEFAULT NULL,
  `default_billing_address_id` binary(16) NOT NULL,
  `default_shipping_address_id` binary(16) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `email` (`email`),
  KEY `sessionID` (`session_id`),
  KEY `firstlogin` (`first_login`),
  KEY `lastlogin` (`last_login`),
  KEY `validation` (`validation`),
  KEY `fk_customer.last_payment_method_id` (`last_payment_method_id`),
  KEY `fk_customer.customer_group_id` (`customer_group_id`),
  KEY `fk_customer.default_payment_method_id` (`default_payment_method_id`),
  KEY `fk_customer.shop_id` (`shop_id`),
  KEY `fk_customer.default_billing_address_id` (`default_billing_address_id`),
  KEY `fk_customer.default_shipping_address_id` (`default_shipping_address_id`),
  CONSTRAINT `fk_customer.customer_group_id` FOREIGN KEY (`customer_group_id`) REFERENCES `customer_group` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_customer.default_payment_method_id` FOREIGN KEY (`default_payment_method_id`) REFERENCES `payment_method` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_customer.last_payment_method_id` FOREIGN KEY (`last_payment_method_id`) REFERENCES `payment_method` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_customer.shop_id` FOREIGN KEY (`shop_id`) REFERENCES `shop` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `customer_address`;
CREATE TABLE `customer_address` (
  `id` binary(16) NOT NULL,
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
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `customer_id` binary(16) NOT NULL,
  `country_id` binary(16) NOT NULL,
  `country_state_id` binary(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_customer_address.customer_id` (`customer_id`),
  KEY `fk_customer_address.country_id` (`country_id`),
  KEY `fk_customer_address.country_state_id` (`country_state_id`),
  CONSTRAINT `fk_customer_address.country_id` FOREIGN KEY (`country_id`) REFERENCES `country` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_customer_address.country_state_id` FOREIGN KEY (`country_state_id`) REFERENCES `country_state` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_customer_address.customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `customer_group`;
CREATE TABLE `customer_group` (
  `id` binary(16) NOT NULL,
  `display_gross` tinyint(1) NOT NULL DEFAULT '1',
  `input_gross` tinyint(1) NOT NULL DEFAULT '1',
  `has_global_discount` tinyint(1) NOT NULL DEFAULT '0',
  `percentage_global_discount` double DEFAULT NULL,
  `minimum_order_amount` double DEFAULT NULL,
  `minimum_order_amount_surcharge` double DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `customer_group_discount`;
CREATE TABLE `customer_group_discount` (
  `id` binary(16) NOT NULL,
  `percentage_discount` double NOT NULL,
  `minimum_cart_amount` double NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `customer_group_id` binary(16) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customer_group_uuid_minimum_cart_amount` (`minimum_cart_amount`),
  KEY `fk_customer_group_discount.customer_group_id` (`customer_group_id`),
  CONSTRAINT `fk_customer_group_discount.customer_group_id` FOREIGN KEY (`customer_group_id`) REFERENCES `customer_group` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `customer_group_translation`;
CREATE TABLE `customer_group_translation` (
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_group_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  PRIMARY KEY (`customer_group_id`,`language_id`),
  KEY `language_id` (`language_id`),
  CONSTRAINT `customer_group_translation_ibfk_1` FOREIGN KEY (`language_id`) REFERENCES `shop` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `customer_group_translation_ibfk_2` FOREIGN KEY (`customer_group_id`) REFERENCES `customer_group` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `listing_facet`;
CREATE TABLE `listing_facet` (
  `id` binary(16) NOT NULL,
  `active` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `unique_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_in_categories` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `deletable` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `position` int(11) NOT NULL DEFAULT '1',
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_identifier` (`unique_key`),
  KEY `sorting` (`display_in_categories`,`position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `listing_facet_translation`;
CREATE TABLE `listing_facet_translation` (
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `listing_facet_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  PRIMARY KEY (`listing_facet_id`,`language_id`),
  KEY `language_id` (`language_id`),
  CONSTRAINT `listing_facet_translation_ibfk_1` FOREIGN KEY (`language_id`) REFERENCES `shop` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `listing_facet_translation_ibfk_2` FOREIGN KEY (`listing_facet_id`) REFERENCES `listing_facet` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `listing_sorting`;
CREATE TABLE `listing_sorting` (
  `id` binary(16) NOT NULL,
  `active` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `display_in_categories` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `position` int(11) NOT NULL DEFAULT '1',
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sorting` (`display_in_categories`,`position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `listing_sorting_translation`;
CREATE TABLE `listing_sorting_translation` (
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `listing_sorting_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  PRIMARY KEY (`listing_sorting_id`,`language_id`),
  KEY `language_id` (`language_id`),
  CONSTRAINT `listing_sorting_translation_ibfk_1` FOREIGN KEY (`language_id`) REFERENCES `shop` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `listing_sorting_translation_ibfk_2` FOREIGN KEY (`listing_sorting_id`) REFERENCES `listing_sorting` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `locale`;
CREATE TABLE `locale` (
  `id` binary(16) NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `locale` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `locale_translation`;
CREATE TABLE `locale_translation` (
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `territory` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `locale_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  PRIMARY KEY (`locale_id`,`language_id`),
  KEY `language_id` (`language_id`),
  CONSTRAINT `locale_translation_ibfk_1` FOREIGN KEY (`language_id`) REFERENCES `shop` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `locale_translation_ibfk_2` FOREIGN KEY (`locale_id`) REFERENCES `locale` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `log`;
CREATE TABLE `log` (
  `id` binary(16) NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `text` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` datetime NOT NULL,
  `user` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value4` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `mail`;
CREATE TABLE `mail` (
  `id` binary(16) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_html` tinyint(1) NOT NULL,
  `attachment` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mail_type` int(11) NOT NULL DEFAULT '1',
  `context` longtext COLLATE utf8mb4_unicode_ci,
  `dirty` tinyint(1) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `order_state_id` binary(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `fk_mail.order_state_id` (`order_state_id`),
  CONSTRAINT `fk_mail.order_state_id` FOREIGN KEY (`order_state_id`) REFERENCES `order_state` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `mail_attachment`;
CREATE TABLE `mail_attachment` (
  `id` binary(16) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `mail_id` binary(16) NOT NULL,
  `media_id` binary(16) NOT NULL,
  `shop_id` binary(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_mail_attachment.mail_id` (`mail_id`),
  KEY `fk_mail_attachment.media_id` (`media_id`),
  KEY `fk_mail_attachment.shop_id` (`shop_id`),
  CONSTRAINT `fk_mail_attachment.mail_id` FOREIGN KEY (`mail_id`) REFERENCES `mail` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mail_attachment.media_id` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_mail_attachment.shop_id` FOREIGN KEY (`shop_id`) REFERENCES `shop` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


DROP TABLE IF EXISTS `mail_translation`;
CREATE TABLE `mail_translation` (
  `from_mail` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `content_html` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `mail_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  PRIMARY KEY (`mail_id`,`language_id`),
  KEY `language_id` (`language_id`),
  CONSTRAINT `mail_translation_ibfk_1` FOREIGN KEY (`language_id`) REFERENCES `shop` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `mail_translation_ibfk_2` FOREIGN KEY (`mail_id`) REFERENCES `mail` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `media`;
CREATE TABLE `media` (
  `id` binary(16) NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int(10) unsigned NOT NULL,
  `meta_data` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `media_album_id` binary(16) NOT NULL,
  `user_id` binary(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `path` (`file_name`),
  KEY `fk_media.media_album_id` (`media_album_id`),
  KEY `fk_media.user_id` (`user_id`),
  CONSTRAINT `fk_media.media_album_id` FOREIGN KEY (`media_album_id`) REFERENCES `media_album` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_media.user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `media_album`;
CREATE TABLE `media_album` (
  `id` binary(16) NOT NULL,
  `position` int(11) NOT NULL DEFAULT '1',
  `create_thumbnails` tinyint(1) NOT NULL DEFAULT '0',
  `thumbnail_size` text COLLATE utf8mb4_unicode_ci,
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `thumbnail_high_dpi` tinyint(1) NOT NULL DEFAULT '1',
  `thumbnail_quality` int(11) DEFAULT NULL,
  `thumbnail_high_dpi_quality` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `parent_id` binary(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_album.parent_id` (`parent_id`),
  CONSTRAINT `fk_album.parent_id` FOREIGN KEY (`parent_id`) REFERENCES `media_album` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `media_album_translation`;
CREATE TABLE `media_album_translation` (
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `media_album_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  PRIMARY KEY (`media_album_id`,`language_id`),
  KEY `language_id` (`language_id`),
  CONSTRAINT `media_album_translation_ibfk_1` FOREIGN KEY (`language_id`) REFERENCES `shop` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `media_album_translation_ibfk_2` FOREIGN KEY (`media_album_id`) REFERENCES `media_album` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `media_translation`;
CREATE TABLE `media_translation` (
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `media_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  PRIMARY KEY (`media_id`,`language_id`),
  KEY `language_id` (`language_id`),
  CONSTRAINT `media_translation_ibfk_1` FOREIGN KEY (`language_id`) REFERENCES `shop` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `media_translation_ibfk_2` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `order`;
CREATE TABLE `order` (
  `id` binary(16) NOT NULL,
  `order_date` datetime NOT NULL,
  `amount_total` double NOT NULL,
  `position_price` double NOT NULL,
  `shipping_total` double NOT NULL,
  `is_net` tinyint(1) NOT NULL,
  `is_tax_free` tinyint(1) NOT NULL,
  `context` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `customer_id` binary(16) NOT NULL,
  `order_state_id` binary(16) NOT NULL,
  `payment_method_id` binary(16) NOT NULL,
  `currency_id` binary(16) NOT NULL,
  `shop_id` binary(16) NOT NULL,
  `billing_address_id` binary(16) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_order.customer_id` (`customer_id`),
  KEY `fk_order.order_state_id` (`order_state_id`),
  KEY `fk_order.payment_method_id` (`payment_method_id`),
  KEY `fk_order.currency_id` (`currency_id`),
  KEY `fk_order.billing_address_id` (`billing_address_id`),
  KEY `fk_order.shop_id` (`shop_id`),
  CONSTRAINT `fk_order.billing_address_id` FOREIGN KEY (`billing_address_id`) REFERENCES `order_address` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_order.currency_id` FOREIGN KEY (`currency_id`) REFERENCES `currency` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_order.customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_order.order_state_id` FOREIGN KEY (`order_state_id`) REFERENCES `order_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_order.payment_method_id` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_method` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_order.shop_id` FOREIGN KEY (`shop_id`) REFERENCES `shop` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `order_address`;
CREATE TABLE `order_address` (
  `id` binary(16) NOT NULL,
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
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `country_id` binary(16) NOT NULL,
  `country_state_id` binary(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_order_address.country_state_id` (`country_state_id`),
  KEY `fk_order_address.country_id` (`country_id`),
  CONSTRAINT `fk_order_address.country_id` FOREIGN KEY (`country_id`) REFERENCES `country` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_order_address.country_state_id` FOREIGN KEY (`country_state_id`) REFERENCES `country_state` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `order_delivery`;
CREATE TABLE `order_delivery` (
  `id` binary(16) NOT NULL,
  `tracking_code` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_date_earliest` date NOT NULL,
  `shipping_date_latest` date NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `order_id` binary(16) NOT NULL,
  `shipping_address_id` binary(16) NOT NULL,
  `order_state_id` binary(16) NOT NULL,
  `shipping_method_id` binary(16) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_order_delivery.order_id` (`order_id`),
  KEY `fk_order_delivery.shipping_address_id` (`shipping_address_id`),
  KEY `fk_order_delivery.shipping_method_id` (`shipping_method_id`),
  KEY `fk_order_delivery.order_state_id` (`order_state_id`),
  CONSTRAINT `fk_order_delivery.order_id` FOREIGN KEY (`order_id`) REFERENCES `order` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_order_delivery.order_state_id` FOREIGN KEY (`order_state_id`) REFERENCES `order_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_order_delivery.shipping_address_id` FOREIGN KEY (`shipping_address_id`) REFERENCES `order_address` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_order_delivery.shipping_method_id` FOREIGN KEY (`shipping_method_id`) REFERENCES `shipping_method` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `order_delivery_position`;
CREATE TABLE `order_delivery_position` (
  `id` binary(16) NOT NULL,
  `unit_price` double NOT NULL,
  `total_price` double NOT NULL,
  `quantity` double NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `order_delivery_id` binary(16) NOT NULL,
  `order_line_item_id` binary(16) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_order_delivery_position.order_delivery_id` (`order_delivery_id`),
  KEY `fk_order_delivery_position.order_line_item_id` (`order_line_item_id`),
  CONSTRAINT `fk_order_delivery_position.order_delivery_id` FOREIGN KEY (`order_delivery_id`) REFERENCES `order_delivery` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_order_delivery_position.order_line_item_id` FOREIGN KEY (`order_line_item_id`) REFERENCES `order_line_item` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `order_line_item`;
CREATE TABLE `order_line_item` (
  `id` binary(16) NOT NULL,
  `identifier` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` double NOT NULL,
  `total_price` double NOT NULL,
  `type` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `order_id` binary(16) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_order_line_item.order_id` (`order_id`),
  CONSTRAINT `fk_order_line_item.order_id` FOREIGN KEY (`order_id`) REFERENCES `order` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `order_state`;
CREATE TABLE `order_state` (
  `id` binary(16) NOT NULL,
  `name` varchar(55) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int(11) NOT NULL DEFAULT '1',
  `has_mail` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `order_state_translation`;
CREATE TABLE `order_state_translation` (
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_state_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  PRIMARY KEY (`order_state_id`,`language_id`),
  KEY `language_id` (`language_id`),
  CONSTRAINT `order_state_translation_ibfk_1` FOREIGN KEY (`language_id`) REFERENCES `shop` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `order_state_translation_ibfk_2` FOREIGN KEY (`order_state_id`) REFERENCES `order_state` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `payment_method`;
CREATE TABLE `payment_method` (
  `id` binary(16) NOT NULL,
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
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `plugin_id` VARCHAR(250) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`technical_name`),
  KEY `fk_payment_method.plugin_id` (`plugin_id`),
  CONSTRAINT `fk_payment_method.plugin_id` FOREIGN KEY (`plugin_id`) REFERENCES `plugin` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `payment_method_translation`;
CREATE TABLE `payment_method_translation` (
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `additional_description` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_method_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  PRIMARY KEY (`payment_method_id`,`language_id`),
  KEY `language_id` (`language_id`),
  CONSTRAINT `payment_method_translation_ibfk_1` FOREIGN KEY (`language_id`) REFERENCES `shop` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `payment_method_translation_ibfk_2` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_method` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `plugin`;
CREATE TABLE `plugin` (
  `id` VARCHAR(250) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` mediumtext COLLATE utf8mb4_unicode_ci,
  `description_long` mediumtext COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
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
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `product`;
CREATE TABLE `product` (
  `id` binary(16) NOT NULL,
  `is_main` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `active` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `supplier_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ean` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stock` int(11) NOT NULL DEFAULT '0',
  `is_closeout` tinyint(1) NOT NULL DEFAULT '0',
  `min_stock` int(11) unsigned DEFAULT NULL,
  `purchase_steps` int(11) unsigned DEFAULT NULL,
  `max_purchase` int(11) unsigned DEFAULT NULL,
  `min_purchase` int(11) unsigned NOT NULL DEFAULT '1',
  `purchase_unit` decimal(11,4) unsigned DEFAULT NULL,
  `reference_unit` decimal(10,3) unsigned DEFAULT NULL,
  `shipping_free` tinyint(4) NOT NULL DEFAULT '0',
  `purchase_price` double NOT NULL DEFAULT '0',
  `pseudo_sales` int(11) NOT NULL DEFAULT '0',
  `mark_as_topseller` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `sales` int(11) NOT NULL DEFAULT '0',
  `position` int(11) unsigned NOT NULL DEFAULT '1',
  `weight` decimal(10,3) unsigned NOT NULL DEFAULT '0',
  `width` decimal(10,3) unsigned NOT NULL DEFAULT '0',
  `height` decimal(10,3) unsigned NOT NULL DEFAULT '0',
  `length` decimal(10,3) unsigned NOT NULL DEFAULT '0',
  `template` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `allow_notification` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `release_date` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `container_id` binary(16) DEFAULT NULL,
  `tax_id` binary(16) DEFAULT NULL,
  `product_manufacturer_id` binary(16) DEFAULT NULL,
  `price_group_id` binary(16) DEFAULT NULL,
  `unit_id` binary(16) DEFAULT NULL,
  `category_tree` json NULL,
  PRIMARY KEY (`id`),
  KEY `fk_product.product_manufacturer_id` (`product_manufacturer_id`),
  KEY `fk_product.tax_id` (`tax_id`),
  KEY `fk_product.unit_id` (`unit_id`),
  CONSTRAINT `fk_product.product_manufacturer_id` FOREIGN KEY (`product_manufacturer_id`) REFERENCES `product_manufacturer` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_product.tax_id` FOREIGN KEY (`tax_id`) REFERENCES `tax` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_product.unit_id` FOREIGN KEY (`unit_id`) REFERENCES `unit` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `product_category`;
CREATE TABLE `product_category` (
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `product_id` binary(16) NOT NULL,
  `category_id` binary(16) NOT NULL,
  PRIMARY KEY (`product_id`, `category_id`),
  KEY `fk_product_category.category_id` (`category_id`),
  KEY `fk_product_category.product_id` (`product_id`),
  CONSTRAINT `fk_product_category.category_id` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_category.product_id` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `product_listing_price`;
CREATE TABLE `product_listing_price` (
  `id` binary(16) NOT NULL,
  `sorting_price` float NOT NULL,
  `price` float NOT NULL,
  `display_from_price` TINYINT(1) NOT NULL,
  `product_id` binary(16) NOT NULL,
  `customer_group_id` binary(16) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_product_listing_price.product_id` (`product_id`),
  KEY `fk_product_listing_price.customer_group_id` (`customer_group_id`),
  CONSTRAINT `fk_product_listing_price.customer_group_id` FOREIGN KEY (`customer_group_id`) REFERENCES `customer_group` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_listing_price.product_id` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `search_keyword` (
  `keyword` varchar(500) NOT NULL,
  `shop_id` binary(16) NOT NULL,
  PRIMARY KEY `keyword_shop_uuid` (`keyword`, `shop_id`),
  CONSTRAINT `fk_search_keyword.shop_id` FOREIGN KEY (`shop_id`) REFERENCES `shop` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE `product_search_keyword` (
  `id` binary(16) NOT NULL,
  `keyword` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shop_id` binary(16) NOT NULL,
  `product_id` binary(16) NOT NULL,
  `ranking` float NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY (`keyword`,`shop_id`,`product_id`),
  KEY `product_id` (`product_id`),
  KEY `shop_id` (`shop_id`),
  CONSTRAINT `product_id` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `shop_id` FOREIGN KEY (`shop_id`) REFERENCES `shop` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `audit_log` (
  `id` binary(16) NOT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` binary(16) NULL,
  `entity` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `foreign_key` binary(16) NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `product_manufacturer`;
CREATE TABLE `product_manufacturer` (
  `id` binary(16) NOT NULL,
  `link` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `media_id` binary(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_product_manufacturer.media_id` (`media_id`),
  CONSTRAINT `fk_product_manufacturer.media_id` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `product_manufacturer_translation`;
CREATE TABLE `product_manufacturer_translation` (
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_keywords` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_manufacturer_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  PRIMARY KEY (`product_manufacturer_id`,`language_id`),
  KEY `language_id` (`language_id`),
  CONSTRAINT `product_manufacturer_translation_ibfk_1` FOREIGN KEY (`language_id`) REFERENCES `shop` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `product_manufacturer_translation_ibfk_2` FOREIGN KEY (`product_manufacturer_id`) REFERENCES `product_manufacturer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `product_media`;
CREATE TABLE `product_media` (
  `id` binary(16) NOT NULL,
  `is_cover` tinyint(1) NOT NULL,
  `position` int(11) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `product_id` binary(16) NOT NULL,
  `media_id` binary(16) NOT NULL,
  `parent_id` binary(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_product_media.media_id` (`media_id`),
  KEY `fk_product_media.product_id` (`product_id`),
  CONSTRAINT `fk_product_media.media_id` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_media.product_id` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `product_price`;
CREATE TABLE `product_price` (
  `id` binary(16) NOT NULL,
  `quantity_start` int(11) NOT NULL DEFAULT '0',
  `quantity_end` int(11) DEFAULT NULL,
  `price` double NOT NULL DEFAULT '0',
  `pseudo_price` double DEFAULT NULL,
  `base_price` double DEFAULT NULL,
  `percentage` decimal(10,2) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `customer_group_id` binary(16) NOT NULL,
  `product_id` binary(16) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `pricegroup_2` (`quantity_start`),
  KEY `pricegroup` (`quantity_end`),
  KEY `product_prices` (`quantity_start`),
  KEY `fk_product_price.customer_group_id` (`customer_group_id`),
  KEY `fk_product_price.product_id` (`product_id`),
  CONSTRAINT `fk_product_price.customer_group_id` FOREIGN KEY (`customer_group_id`) REFERENCES `customer_group` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_price.product_id` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `product_seo_category`;
CREATE TABLE `product_seo_category` (
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `shop_id` binary(16) NOT NULL,
  `product_id` binary(16) NOT NULL,
  `category_id` binary(16) NOT NULL,
  KEY `fk_product_seo_category.shop_id` (`shop_id`),
  KEY `fk_product_seo_category.category_id` (`category_id`),
  KEY `fk_product_seo_category.product_id` (`product_id`),
  CONSTRAINT `fk_product_seo_category.category_id` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_seo_category.product_id` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_seo_category.shop_id` FOREIGN KEY (`shop_id`) REFERENCES `shop` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `product_stream`;
CREATE TABLE `product_stream` (
  `id` binary(16) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `conditions` text COLLATE utf8mb4_unicode_ci,
  `type` int(11) DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `listing_sorting_id` binary(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_product_stream.listing_sorting_id` (`listing_sorting_id`),
  CONSTRAINT `fk_product_stream.listing_sorting_id` FOREIGN KEY (`listing_sorting_id`) REFERENCES `listing_sorting` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `product_stream_assignment`;
CREATE TABLE `product_stream_assignment` (
  `id` binary(16) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `product_stream_id` binary(16) NOT NULL,
  `product_id` binary(16) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_product_stream_assignment.product_stream_id` (`product_stream_id`),
  KEY `fk_product_stream_assignment.product_id` (`product_id`),
  CONSTRAINT `fk_product_stream_assignment.product_id` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_stream_assignment.product_stream_id` FOREIGN KEY (`product_stream_id`) REFERENCES `product_stream` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Contains the manually assigned products of a stream';


DROP TABLE IF EXISTS `product_stream_tab`;
CREATE TABLE `product_stream_tab` (
  `id` binary(16) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `product_stream_id` binary(16) NOT NULL,
  `product_id` binary(16) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_product_stream_tab.product_stream_id` (`product_stream_id`),
  KEY `fk_product_stream_tab.product_id` (`product_id`),
  CONSTRAINT `fk_product_stream_tab.product_id` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_stream_tab.product_stream_id` FOREIGN KEY (`product_stream_id`) REFERENCES `product_stream` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='used to assign stream as detail page tab item';


DROP TABLE IF EXISTS `product_translation`;
CREATE TABLE `product_translation` (
  `additional_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keywords` mediumtext COLLATE utf8mb4_unicode_ci,
  `description` mediumtext COLLATE utf8mb4_unicode_ci,
  `description_long` mediumtext COLLATE utf8mb4_unicode_ci,
  `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pack_unit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  PRIMARY KEY (`product_id`,`language_id`),
  KEY `fk_product_trans.language_id` (`language_id`),
  CONSTRAINT `fk_product_trans.language_id` FOREIGN KEY (`language_id`) REFERENCES `shop` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_trans.product_id` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `schema_version`;
CREATE TABLE `schema_version` (
  `version` varchar(14) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` datetime NOT NULL,
  `complete_date` datetime DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `error_msg` longtext COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `seo_url`;
CREATE TABLE `seo_url` (
  `id` binary(16) NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `foreign_key` binary(16) NOT NULL,
  `path_info` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `seo_path_info` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_canonical` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `shop_id` binary(16) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_seo_url.shop_id` (`shop_id`),
  CONSTRAINT `fk_seo_url.shop_id` FOREIGN KEY (`shop_id`) REFERENCES `shop` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `session`;
CREATE TABLE `session` (
  `id` varchar(128) COLLATE utf8mb4_bin NOT NULL,
  `data` mediumblob NOT NULL,
  `modified` int(10) unsigned NOT NULL,
  `expiry` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sess_expiry` (`expiry`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;


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
  `type` int(11) unsigned NOT NULL,
  `active` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `position` int(11) NOT NULL DEFAULT '1',
  `calculation` int(1) unsigned NOT NULL DEFAULT '0',
  `surcharge_calculation` int(1) unsigned DEFAULT NULL,
  `tax_calculation` int(11) unsigned NOT NULL DEFAULT '0',
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
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `customer_group_id` binary(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_shipping_method.customer_group_id` (`customer_group_id`),
  CONSTRAINT `fk_shipping_method.customer_group_id` FOREIGN KEY (`customer_group_id`) REFERENCES `customer_group` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `shipping_method_price`;
CREATE TABLE `shipping_method_price` (
  `id` binary(16) NOT NULL,
  `quantity_from` decimal(10,3) unsigned NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `factor` decimal(10,2) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `shipping_method_id` binary(16) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shipping_method_uuid_quantity_from` (`quantity_from`),
  KEY `fk_shipping_method_price.shipping_method_id` (`shipping_method_id`),
  CONSTRAINT `fk_shipping_method_price.shipping_method_id` FOREIGN KEY (`shipping_method_id`) REFERENCES `shipping_method` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `shipping_method_translation`;
CREATE TABLE `shipping_method_translation` (
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` mediumtext COLLATE utf8mb4_unicode_ci,
  `comment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_method_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  PRIMARY KEY (`shipping_method_id`,`language_id`),
  KEY `language_id` (`language_id`),
  CONSTRAINT `shipping_method_translation_ibfk_1` FOREIGN KEY (`language_id`) REFERENCES `shop` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `shipping_method_translation_ibfk_2` FOREIGN KEY (`shipping_method_id`) REFERENCES `shipping_method` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `shop`;
CREATE TABLE `shop` (
  `id` binary(16) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` int(11) NOT NULL,
  `host` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `base_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `base_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hosts` text COLLATE utf8mb4_unicode_ci,
  `is_secure` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `customer_scope` tinyint(1) NOT NULL DEFAULT '0',
  `is_default` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `tax_calculation_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'vertical',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `parent_id` binary(16) DEFAULT NULL,
  `shop_template_id` binary(16) NOT NULL,
  `document_template_id` binary(16) NOT NULL,
  `category_id` binary(16) NOT NULL,
  `locale_id` binary(16) NOT NULL,
  `currency_id` binary(16) NOT NULL,
  `customer_group_id` binary(16) NOT NULL,
  `fallback_translation_id` binary(16) DEFAULT NULL,
  `payment_method_id` binary(16) NOT NULL,
  `shipping_method_id` binary(16) NOT NULL,
  `country_id` binary(16) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `host` (`host`),
  KEY `fk_shop.parent_id` (`parent_id`),
  KEY `fk_shop.shop_template_id` (`shop_template_id`),
  KEY `fk_shop.document_template_id` (`document_template_id`),
  KEY `fk_shop.category_id` (`category_id`),
  KEY `fk_shop.locale_id` (`locale_id`),
  KEY `fk_shop.currency_id` (`currency_id`),
  KEY `fk_shop.customer_group_id` (`customer_group_id`),
  KEY `fk_shop.fallback_translation_id` (`fallback_translation_id`),
  KEY `fk_shop.payment_method_id` (`payment_method_id`),
  KEY `fk_shop.shipping_method_id` (`shipping_method_id`),
  KEY `fk_shop.country_id` (`country_id`),
  CONSTRAINT `fk_shop.category_id` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_shop.country_id` FOREIGN KEY (`country_id`) REFERENCES `country` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_shop.currency_id` FOREIGN KEY (`currency_id`) REFERENCES `currency` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_shop.customer_group_id` FOREIGN KEY (`customer_group_id`) REFERENCES `customer_group` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_shop.document_template_id` FOREIGN KEY (`document_template_id`) REFERENCES `shop_template` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_shop.fallback_translation_id` FOREIGN KEY (`fallback_translation_id`) REFERENCES `shop` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_shop.locale_id` FOREIGN KEY (`locale_id`) REFERENCES `locale` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_shop.parent_id` FOREIGN KEY (`parent_id`) REFERENCES `shop` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_shop.payment_method_id` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_method` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_shop.shipping_method_id` FOREIGN KEY (`shipping_method_id`) REFERENCES `shipping_method` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_shop.shop_template_id` FOREIGN KEY (`shop_template_id`) REFERENCES `shop_template` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `shop_currency`;
CREATE TABLE `shop_currency` (
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `shop_id` binary(16) NOT NULL,
  `currency_id` binary(16) NOT NULL,
  PRIMARY KEY (`shop_id`,`currency_id`),
  KEY `fk_shop_currency.currency_id` (`currency_id`),
  CONSTRAINT `fk_shop_currency.currency_id` FOREIGN KEY (`currency_id`) REFERENCES `currency` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_shop_currency.shop_id` FOREIGN KEY (`shop_id`) REFERENCES `shop` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `shop_template`;
CREATE TABLE `shop_template` (
  `id` binary(16) NOT NULL,
  `template` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `author` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `esi` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `style_support` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `version` int(11) unsigned NOT NULL DEFAULT '0',
  `emotion` tinyint(1) unsigned NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `plugin_id` VARCHAR(250) DEFAULT NULL,
  `parent_id` binary(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `basename` (`template`),
  KEY `fk_shop_template.plugin_id` (`plugin_id`),
  KEY `fk_shop_template.parent_id` (`parent_id`),
  CONSTRAINT `fk_shop_template.parent_id` FOREIGN KEY (`parent_id`) REFERENCES `shop_template` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_shop_template.plugin_id` FOREIGN KEY (`plugin_id`) REFERENCES `plugin` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `shop_template_config_form`;
CREATE TABLE `shop_template_config_form` (
  `id` binary(16) NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `options` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `parent_id` binary(16) DEFAULT NULL,
  `shop_template_id` binary(16) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_shop_template_config_form.parent_id` (`parent_id`),
  KEY `fk_shop_template_config_form.shop_template_id` (`shop_template_id`),
  CONSTRAINT `fk_shop_template_config_form.parent_id` FOREIGN KEY (`parent_id`) REFERENCES `shop_template_config_form` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_shop_template_config_form.shop_template_id` FOREIGN KEY (`shop_template_id`) REFERENCES `shop_template` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `shop_template_config_form_field`;
CREATE TABLE `shop_template_config_form_field` (
  `id` binary(16) NOT NULL,
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
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `shop_template_id` binary(16) NOT NULL,
  `shop_template_config_form_id` binary(16) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `template_id_name` (`name`),
  KEY `fk_shop_template_config_form_field.shop_template_id` (`shop_template_id`),
  KEY `fk_shop_template_cff.shop_template_config_form_id` (`shop_template_config_form_id`),
  CONSTRAINT `fk_shop_template_cff.shop_template_config_form_id` FOREIGN KEY (`shop_template_config_form_id`) REFERENCES `shop_template_config_form` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_shop_template_config_form_field.shop_template_id` FOREIGN KEY (`shop_template_id`) REFERENCES `shop_template` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `shop_template_config_form_field_value`;
CREATE TABLE `shop_template_config_form_field_value` (
  `id` binary(16) NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `shop_template_config_form_field_id` binary(16) NOT NULL,
  `shop_id` binary(16) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_shop_template_cffv.shop_id` (`shop_id`),
  KEY `fk_shop_template_cffv.shop_template_config_form_field_id` (`shop_template_config_form_field_id`),
  CONSTRAINT `fk_shop_template_cffv.shop_id` FOREIGN KEY (`shop_id`) REFERENCES `shop` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_shop_template_cffv.shop_template_config_form_field_id` FOREIGN KEY (`shop_template_config_form_field_id`) REFERENCES `shop_template_config_form_field` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `shop_template_config_preset`;
CREATE TABLE `shop_template_config_preset` (
  `id` binary(16) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `element_values` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `shop_template_id` binary(16) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_shop_template_config_preset.shop_template_id` (`shop_template_id`),
  CONSTRAINT `fk_shop_template_config_preset.shop_template_id` FOREIGN KEY (`shop_template_id`) REFERENCES `shop_template` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `snippet`;
CREATE TABLE `snippet` (
  `id` binary(16) NOT NULL,
  `namespace` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `locale` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `dirty` tinyint(1) DEFAULT '0',
  `shop_id` binary(16) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_snippet.shop_id` (`shop_id`),
  CONSTRAINT `fk_snippet.shop_id` FOREIGN KEY (`shop_id`) REFERENCES `shop` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `tax`;
CREATE TABLE `tax` (
  `id` binary(16) NOT NULL,
  `tax_rate` decimal(10,2) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tax` (`tax_rate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `tax_area_rule`;
CREATE TABLE `tax_area_rule` (
  `id` binary(16) NOT NULL,
  `tax_rate` decimal(10,2) NOT NULL,
  `active` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `country_area_id` binary(16) DEFAULT NULL,
  `country_id` binary(16) DEFAULT NULL,
  `country_state_id` binary(16) DEFAULT NULL,
  `tax_id` binary(16) NOT NULL,
  `customer_group_id` binary(16) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_tax_area_rule.country_id` (`country_id`),
  KEY `fk_tax_area_rule.country_area_id` (`country_area_id`),
  KEY `fk_tax_area_rule.country_state_id` (`country_state_id`),
  KEY `fk_tax_area_rule.tax_id` (`tax_id`),
  KEY `fk_tax_area_rule.customer_group_id` (`customer_group_id`),
  CONSTRAINT `fk_tax_area_rule.country_area_id` FOREIGN KEY (`country_area_id`) REFERENCES `country_area` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tax_area_rule.country_id` FOREIGN KEY (`country_id`) REFERENCES `country` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tax_area_rule.country_state_id` FOREIGN KEY (`country_state_id`) REFERENCES `country_state` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tax_area_rule.customer_group_id` FOREIGN KEY (`customer_group_id`) REFERENCES `customer_group` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tax_area_rule.tax_id` FOREIGN KEY (`tax_id`) REFERENCES `tax` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `tax_area_rule_translation`;
CREATE TABLE `tax_area_rule_translation` (
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tax_area_rule_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  PRIMARY KEY (`tax_area_rule_id`,`language_id`),
  KEY `language_id` (`language_id`),
  CONSTRAINT `tax_area_rule_translation_ibfk_1` FOREIGN KEY (`language_id`) REFERENCES `shop` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `tax_area_rule_translation_ibfk_2` FOREIGN KEY (`tax_area_rule_id`) REFERENCES `tax_area_rule` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `unit`;
CREATE TABLE `unit` (
  `id` binary(16) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `unit_translation`;
CREATE TABLE `unit_translation` (
  `short_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  PRIMARY KEY (`unit_id`,`language_id`),
  KEY `language_id` (`language_id`),
  CONSTRAINT `unit_translation_ibfk_1` FOREIGN KEY (`language_id`) REFERENCES `shop` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `unit_translation_ibfk_2` FOREIGN KEY (`unit_id`) REFERENCES `unit` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `user`;
CREATE TABLE `user` (
  `id` binary(16) NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `encoder` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'LegacyBackendMd5',
  `api_key` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `session_id` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '0',
  `failed_logins` int(11) NOT NULL DEFAULT '0',
  `locked_until` datetime DEFAULT NULL,
  `extended_editor` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `disabled_cache` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `user_role_id` binary(16) NOT NULL,
  `locale_id` binary(16) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `fk_user.locale_id` (`locale_id`),
  CONSTRAINT `fk_user.locale_id` FOREIGN KEY (`locale_id`) REFERENCES `locale` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;