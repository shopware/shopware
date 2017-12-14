SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE `cart` (
  `token` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `container` json NOT NULL,
  `calculated` json NOT NULL,
  `currency_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipping_method_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_method_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shop_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` float NOT NULL,
  `line_item_count` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  UNIQUE KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `category` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `path` longtext COLLATE utf8mb4_unicode_ci,
  `position` int(11) unsigned NOT NULL DEFAULT '1',
  `level` int(11) unsigned NOT NULL DEFAULT '1',
  `template` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `is_blog` tinyint(1) NOT NULL DEFAULT '0',
  `external` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hide_filter` tinyint(1) NOT NULL DEFAULT '0',
  `hide_top` tinyint(1) NOT NULL DEFAULT '0',
  `media_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_box_layout` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_stream_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hide_sortings` tinyint(1) NOT NULL DEFAULT '0',
  `sorting_uuids` longtext COLLATE utf8mb4_unicode_ci,
  `facet_uuids` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`),
  KEY `position` (`position`),
  KEY `level` (`level`),
  KEY `active_query_builder` (`position`),
  KEY `media_uuid` (`media_uuid`),
  KEY `parent_uuid` (`parent_uuid`),
  CONSTRAINT `category_ibfk_1` FOREIGN KEY (`media_uuid`) REFERENCES `media` (`uuid`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `category_ibfk_2` FOREIGN KEY (`parent_uuid`) REFERENCES `category` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `category_translation` (
  `category_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `language_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `path_names` longtext COLLATE utf8mb4_unicode_ci,
  `meta_keywords` mediumtext COLLATE utf8mb4_unicode_ci,
  `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description` mediumtext COLLATE utf8mb4_unicode_ci,
  `cms_headline` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cms_description` mediumtext COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`category_uuid`,`language_uuid`),
  KEY `language_uuid` (`language_uuid`),
  CONSTRAINT `category_translation_ibfk_1` FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`),
  CONSTRAINT `category_translation_ibfk_2` FOREIGN KEY (`category_uuid`) REFERENCES `category` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `config_form` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int(11) NOT NULL DEFAULT '1',
  `plugin_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`),
  UNIQUE KEY `name` (`name`),
  KEY `fk_config_form.parent_uuid` (`parent_uuid`),
  KEY `fk_config_form.plugin_uuid` (`plugin_uuid`),
  CONSTRAINT `fk_config_form.parent_uuid` FOREIGN KEY (`parent_uuid`) REFERENCES `config_form` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_config_form.plugin_uuid` FOREIGN KEY (`plugin_uuid`) REFERENCES `plugin` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `config_form_field` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `config_form_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `required` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `position` int(11) NOT NULL DEFAULT '1',
  `scope` int(11) unsigned NOT NULL DEFAULT '0',
  `options` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`),
  UNIQUE KEY `form_id_2` (`name`),
  KEY `config_form_uuid` (`config_form_uuid`),
  CONSTRAINT `fk_config_form_field.config_form_uuid` FOREIGN KEY (`config_form_uuid`) REFERENCES `config_form` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `config_form_field_translation` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `config_form_field_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `locale_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`uuid`),
  KEY `config_form_field_uuid` (`config_form_field_uuid`),
  KEY `fk_config_form_field_translation.locale_uuid` (`locale_uuid`),
  CONSTRAINT `fk_config_form_field_translation.config_form_field_uuid` FOREIGN KEY (`config_form_field_uuid`) REFERENCES `config_form_field` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_config_form_field_translation.locale_uuid` FOREIGN KEY (`locale_uuid`) REFERENCES `locale` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `config_form_field_value` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `config_form_field_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shop_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`),
  KEY `fk_config_form_field_value.shop_uuid` (`shop_uuid`),
  CONSTRAINT `fk_config_form_field_value.shop_uuid` FOREIGN KEY (`shop_uuid`) REFERENCES `shop` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `config_form_translation` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `config_form_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `locale_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`uuid`),
  KEY `config_form_uuid` (`config_form_uuid`),
  KEY `fk_config_form_translation.locale_uuid` (`locale_uuid`),
  CONSTRAINT `fk_config_form_translation.config_form_uuid` FOREIGN KEY (`config_form_uuid`) REFERENCES `config_form` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_config_form_translation.locale_uuid` FOREIGN KEY (`locale_uuid`) REFERENCES `locale` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `country` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `iso` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_area_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
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
  PRIMARY KEY (`uuid`),
  KEY `fk_area_country.country_area_uuid` (`country_area_uuid`),
  CONSTRAINT `fk_area_country.country_area_uuid` FOREIGN KEY (`country_area_uuid`) REFERENCES `country_area` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `country_area` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `country_area_translation` (
  `country_area_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `language_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`country_area_uuid`,`language_uuid`),
  KEY `language_uuid` (`language_uuid`),
  CONSTRAINT `country_area_translation_ibfk_1` FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`),
  CONSTRAINT `country_area_translation_ibfk_2` FOREIGN KEY (`country_area_uuid`) REFERENCES `country_area` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `country_state` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `short_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int(11) NOT NULL DEFAULT '1',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`),
  KEY `fk_area_country_state.country_uuid` (`country_uuid`),
  CONSTRAINT `fk_area_country_state.country_uuid` FOREIGN KEY (`country_uuid`) REFERENCES `country` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `country_state_translation` (
  `country_state_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `language_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`country_state_uuid`,`language_uuid`),
  KEY `language_uuid` (`language_uuid`),
  CONSTRAINT `country_state_translation_ibfk_1` FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`),
  CONSTRAINT `country_state_translation_ibfk_2` FOREIGN KEY (`country_state_uuid`) REFERENCES `country_state` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `country_translation` (
  `country_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `language_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`country_uuid`,`language_uuid`),
  KEY `language_uuid` (`language_uuid`),
  CONSTRAINT `country_translation_ibfk_1` FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`),
  CONSTRAINT `country_translation_ibfk_2` FOREIGN KEY (`country_uuid`) REFERENCES `country` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `currency` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `factor` double NOT NULL,
  `symbol` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `symbol_position` int(11) unsigned NOT NULL DEFAULT '0',
  `position` int(11) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `currency_translation` (
  `currency_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `language_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `short_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`currency_uuid`,`language_uuid`),
  KEY `language_uuid` (`language_uuid`),
  CONSTRAINT `currency_translation_ibfk_1` FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`),
  CONSTRAINT `currency_translation_ibfk_2` FOREIGN KEY (`currency_uuid`) REFERENCES `currency` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `customer` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `salutation` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(1024) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(70) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_group_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `default_payment_method_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shop_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `main_shop_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `encoder` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'md5',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `account_mode` int(11) NOT NULL DEFAULT '0',
  `confirmation_key` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_payment_method_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `default_billing_address_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_shipping_address_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`),
  KEY `email` (`email`),
  KEY `sessionID` (`session_id`),
  KEY `firstlogin` (`first_login`),
  KEY `lastlogin` (`last_login`),
  KEY `validation` (`validation`),
  KEY `customer_group_uuid` (`customer_group_uuid`),
  KEY `fk_customer.last_payment_method_uuid` (`last_payment_method_uuid`),
  KEY `fk_customer.default_payment_method_uuid` (`default_payment_method_uuid`),
  KEY `fk_customer.shop_uuid` (`shop_uuid`),
  KEY `fk_customer.main_shop_uuid` (`main_shop_uuid`),
  KEY `fk_customer.default_billing_address_uuid` (`default_billing_address_uuid`),
  KEY `fk_customer.default_shipping_address_uuid` (`default_shipping_address_uuid`),
  CONSTRAINT `fk_customer.customer_group_uuid` FOREIGN KEY (`customer_group_uuid`) REFERENCES `customer_group` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_customer.default_billing_address_uuid` FOREIGN KEY (`default_billing_address_uuid`) REFERENCES `customer_address` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_customer.default_payment_method_uuid` FOREIGN KEY (`default_payment_method_uuid`) REFERENCES `payment_method` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_customer.default_shipping_address_uuid` FOREIGN KEY (`default_shipping_address_uuid`) REFERENCES `customer_address` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_customer.last_payment_method_uuid` FOREIGN KEY (`last_payment_method_uuid`) REFERENCES `payment_method` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_customer.main_shop_uuid` FOREIGN KEY (`main_shop_uuid`) REFERENCES `shop` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_customer.shop_uuid` FOREIGN KEY (`shop_uuid`) REFERENCES `shop` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `customer_address` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `company` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department` varchar(35) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `salutation` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `street` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zipcode` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `city` varchar(70) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_state_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vat_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_number` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `additional_address_line1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `additional_address_line2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`),
  KEY `customer_uuid` (`customer_uuid`),
  KEY `country_state_uuid` (`country_state_uuid`),
  KEY `country_uuid` (`country_uuid`),
  CONSTRAINT `fk_customer_address.country_state_uuid` FOREIGN KEY (`country_state_uuid`) REFERENCES `country_state` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_customer_address.country_uuid` FOREIGN KEY (`country_uuid`) REFERENCES `country` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_customer_address.customer_uuid` FOREIGN KEY (`customer_uuid`) REFERENCES `customer` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `customer_group` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_gross` tinyint(1) NOT NULL DEFAULT '1',
  `input_gross` tinyint(1) NOT NULL DEFAULT '1',
  `has_global_discount` tinyint(1) NOT NULL DEFAULT '0',
  `percentage_global_discount` double DEFAULT NULL,
  `minimum_order_amount` double DEFAULT NULL,
  `minimum_order_amount_surcharge` double DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `customer_group_discount` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_group_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `percentage_discount` double NOT NULL,
  `minimum_cart_amount` double NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`,`customer_group_uuid`),
  UNIQUE KEY `customer_group_uuid_minimum_cart_amount` (`customer_group_uuid`,`minimum_cart_amount`),
  CONSTRAINT `fk_customer_group_discount.customer_group_uuid` FOREIGN KEY (`customer_group_uuid`) REFERENCES `customer_group` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `customer_group_translation` (
  `customer_group_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `language_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`customer_group_uuid`,`language_uuid`),
  KEY `language_uuid` (`language_uuid`),
  CONSTRAINT `customer_group_translation_ibfk_1` FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`),
  CONSTRAINT `customer_group_translation_ibfk_2` FOREIGN KEY (`customer_group_uuid`) REFERENCES `customer_group` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `listing_facet` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `unique_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_in_categories` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `deletable` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `position` int(11) NOT NULL DEFAULT '1',
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`,`unique_key`),
  UNIQUE KEY `unique_identifier` (`unique_key`),
  KEY `sorting` (`display_in_categories`,`position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `listing_facet_translation` (
  `listing_facet_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `language_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`listing_facet_uuid`,`language_uuid`),
  KEY `language_uuid` (`language_uuid`),
  CONSTRAINT `listing_facet_translation_ibfk_1` FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`),
  CONSTRAINT `listing_facet_translation_ibfk_2` FOREIGN KEY (`listing_facet_uuid`) REFERENCES `listing_facet` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `listing_sorting` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `display_in_categories` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `position` int(11) NOT NULL DEFAULT '1',
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`),
  KEY `sorting` (`display_in_categories`,`position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `listing_sorting_translation` (
  `listing_sorting_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `language_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`listing_sorting_uuid`,`language_uuid`),
  KEY `language_uuid` (`language_uuid`),
  CONSTRAINT `listing_sorting_translation_ibfk_1` FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`),
  CONSTRAINT `listing_sorting_translation_ibfk_2` FOREIGN KEY (`listing_sorting_uuid`) REFERENCES `listing_sorting` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `locale` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`,`code`),
  UNIQUE KEY `locale` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `locale_translation` (
  `locale_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `language_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `territory` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`locale_uuid`,`language_uuid`),
  KEY `language_uuid` (`language_uuid`),
  CONSTRAINT `locale_translation_ibfk_1` FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`),
  CONSTRAINT `locale_translation_ibfk_2` FOREIGN KEY (`locale_uuid`) REFERENCES `locale` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `log` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
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
  PRIMARY KEY (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `mail` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_state_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_html` tinyint(1) NOT NULL,
  `attachment` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mail_type` int(11) NOT NULL DEFAULT '1',
  `context` longtext COLLATE utf8mb4_unicode_ci,
  `dirty` tinyint(1) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`,`name`),
  UNIQUE KEY `name` (`name`),
  KEY `fk_mail.order_state_uuid` (`order_state_uuid`),
  CONSTRAINT `fk_mail.order_state_uuid` FOREIGN KEY (`order_state_uuid`) REFERENCES `order_state` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `mail_attachment` (
  `uuid` varchar(42) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mail_uuid` varchar(42) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `media_uuid` varchar(42) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `shop_uuid` varchar(42) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`),
  UNIQUE KEY `mail_uuid` (`mail_uuid`),
  UNIQUE KEY `mail_uuid_media_uuid_shop_uuid` (`mail_uuid`,`media_uuid`,`shop_uuid`),
  KEY `media_uuid` (`media_uuid`),
  KEY `shop_uuid` (`shop_uuid`),
  CONSTRAINT `fk_mail_attachment.mail_uuid` FOREIGN KEY (`mail_uuid`) REFERENCES `mail` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mail_attachment.media_uuid` FOREIGN KEY (`media_uuid`) REFERENCES `media` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mail_attachment.shop_uuid` FOREIGN KEY (`shop_uuid`) REFERENCES `shop` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE `mail_translation` (
  `mail_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `language_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_mail` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `content_html` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`mail_uuid`,`language_uuid`),
  KEY `language_uuid` (`language_uuid`),
  CONSTRAINT `mail_translation_ibfk_1` FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`),
  CONSTRAINT `mail_translation_ibfk_2` FOREIGN KEY (`mail_uuid`) REFERENCES `mail` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `media` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `media_album_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int(10) unsigned NOT NULL,
  `meta_data` text COLLATE utf8mb4_unicode_ci,
  `user_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`),
  KEY `path` (`file_name`),
  KEY `media_album_uuid` (`media_album_uuid`),
  KEY `user_uuid` (`user_uuid`),
  CONSTRAINT `fk_media.media_album_uuid` FOREIGN KEY (`media_album_uuid`) REFERENCES `media_album` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_media.user_uuid` FOREIGN KEY (`user_uuid`) REFERENCES `user` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `media_album` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` int(11) NOT NULL DEFAULT '1',
  `create_thumbnails` tinyint(1) NOT NULL DEFAULT '0',
  `thumbnail_size` text COLLATE utf8mb4_unicode_ci,
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `thumbnail_high_dpi` tinyint(1) NOT NULL DEFAULT '1',
  `thumbnail_quality` int(11) DEFAULT NULL,
  `thumbnail_high_dpi_quality` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`),
  KEY `parent_uuid` (`parent_uuid`),
  CONSTRAINT `fk_album.parent_uuid` FOREIGN KEY (`parent_uuid`) REFERENCES `media_album` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `media_album_translation` (
  `media_album_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `language_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`media_album_uuid`,`language_uuid`),
  KEY `language_uuid` (`language_uuid`),
  CONSTRAINT `media_album_translation_ibfk_1` FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`),
  CONSTRAINT `media_album_translation_ibfk_2` FOREIGN KEY (`media_album_uuid`) REFERENCES `media_album` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `media_translation` (
  `media_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `language_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`media_uuid`,`language_uuid`),
  KEY `language_uuid` (`language_uuid`),
  CONSTRAINT `media_translation_ibfk_1` FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`),
  CONSTRAINT `media_translation_ibfk_2` FOREIGN KEY (`media_uuid`) REFERENCES `media` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `order` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_date` datetime NOT NULL,
  `customer_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount_total` double NOT NULL,
  `position_price` double NOT NULL,
  `shipping_total` double NOT NULL,
  `order_state_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_method_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_net` tinyint(1) NOT NULL,
  `is_tax_free` tinyint(1) NOT NULL,
  `currency_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shop_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `billing_address_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `context` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`),
  KEY `customer_uuid` (`customer_uuid`),
  KEY `order_state_uuid` (`order_state_uuid`),
  KEY `payment_method_uuid` (`payment_method_uuid`),
  KEY `currency_uuid` (`currency_uuid`),
  KEY `billing_address_uuid` (`billing_address_uuid`),
  KEY `shop_uuid` (`shop_uuid`),
  CONSTRAINT `order_ibfk_1` FOREIGN KEY (`customer_uuid`) REFERENCES `customer` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `order_ibfk_2` FOREIGN KEY (`order_state_uuid`) REFERENCES `order_state` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `order_ibfk_3` FOREIGN KEY (`payment_method_uuid`) REFERENCES `payment_method` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `order_ibfk_4` FOREIGN KEY (`currency_uuid`) REFERENCES `currency` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `order_ibfk_5` FOREIGN KEY (`billing_address_uuid`) REFERENCES `order_address` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `order_ibfk_6` FOREIGN KEY (`shop_uuid`) REFERENCES `shop` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `order_address` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `company` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department` varchar(35) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `salutation` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `street` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `zipcode` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `city` varchar(70) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_state_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vat_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_number` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `additional_address_line1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `additional_address_line2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`),
  KEY `country_state_uuid` (`country_state_uuid`),
  KEY `country_uuid` (`country_uuid`),
  CONSTRAINT `order_address_ibfk_1` FOREIGN KEY (`country_state_uuid`) REFERENCES `country_state` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `order_address_ibfk_2` FOREIGN KEY (`country_uuid`) REFERENCES `country` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `order_delivery` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipping_address_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_state_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tracking_code` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_method_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipping_date_earliest` date NOT NULL,
  `shipping_date_latest` date NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`),
  KEY `order_uuid` (`order_uuid`),
  KEY `shipping_address_uuid` (`shipping_address_uuid`),
  KEY `shipping_method_uuid` (`shipping_method_uuid`),
  KEY `order_state_uuid` (`order_state_uuid`),
  CONSTRAINT `order_delivery_ibfk_1` FOREIGN KEY (`order_uuid`) REFERENCES `order` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `order_delivery_ibfk_2` FOREIGN KEY (`shipping_address_uuid`) REFERENCES `order_address` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `order_delivery_ibfk_3` FOREIGN KEY (`shipping_method_uuid`) REFERENCES `shipping_method` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `order_delivery_ibfk_4` FOREIGN KEY (`order_state_uuid`) REFERENCES `order_state` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `order_delivery_position` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_delivery_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_line_item_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit_price` double NOT NULL,
  `total_price` double NOT NULL,
  `quantity` double NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`),
  KEY `order_delivery_uuid` (`order_delivery_uuid`),
  KEY `order_line_item_uuid` (`order_line_item_uuid`),
  CONSTRAINT `order_delivery_position_ibfk_1` FOREIGN KEY (`order_delivery_uuid`) REFERENCES `order_delivery` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `order_delivery_position_ibfk_2` FOREIGN KEY (`order_line_item_uuid`) REFERENCES `order_line_item` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `order_line_item` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `identifier` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` double NOT NULL,
  `total_price` double NOT NULL,
  `type` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`),
  KEY `order_uuid` (`order_uuid`),
  CONSTRAINT `order_line_item_ibfk_1` FOREIGN KEY (`order_uuid`) REFERENCES `order` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `order_state` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(55) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int(11) NOT NULL DEFAULT '1',
  `type` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL,
  `has_mail` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `order_state_translation` (
  `order_state_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `language_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`order_state_uuid`,`language_uuid`),
  KEY `language_uuid` (`language_uuid`),
  CONSTRAINT `order_state_translation_ibfk_1` FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`),
  CONSTRAINT `order_state_translation_ibfk_2` FOREIGN KEY (`order_state_uuid`) REFERENCES `order_state` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `payment_method` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `plugin_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` int(11) DEFAULT NULL,
  `mobile_inactive` tinyint(1) NOT NULL DEFAULT '0',
  `risk_rules` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`),
  UNIQUE KEY `name` (`technical_name`),
  KEY `fk_payment_method.plugin_uuid` (`plugin_uuid`),
  CONSTRAINT `fk_payment_method.plugin_uuid` FOREIGN KEY (`plugin_uuid`) REFERENCES `plugin` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `payment_method_translation` (
  `payment_method_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `language_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `additional_description` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`payment_method_uuid`,`language_uuid`),
  KEY `language_uuid` (`language_uuid`),
  CONSTRAINT `payment_method_translation_ibfk_1` FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`),
  CONSTRAINT `payment_method_translation_ibfk_2` FOREIGN KEY (`payment_method_uuid`) REFERENCES `payment_method` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `plugin` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
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
  PRIMARY KEY (`uuid`,`name`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `product` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `container_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_main` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `active` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `tax_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_manufacturer_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price_group_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `weight` decimal(10,3) unsigned DEFAULT NULL,
  `width` decimal(10,3) unsigned DEFAULT NULL,
  `height` decimal(10,3) unsigned DEFAULT NULL,
  `length` decimal(10,3) unsigned DEFAULT NULL,
  `template` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `allow_notification` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `release_date` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`),
  KEY `product_manufacturer_uuid` (`product_manufacturer_uuid`),
  KEY `tax_uuid` (`tax_uuid`),
  KEY `container_uuid` (`container_uuid`),
  KEY `unit_uuid` (`unit_uuid`),
  CONSTRAINT `product_ibfk_1` FOREIGN KEY (`product_manufacturer_uuid`) REFERENCES `product_manufacturer` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `product_ibfk_2` FOREIGN KEY (`tax_uuid`) REFERENCES `tax` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `product_ibfk_3` FOREIGN KEY (`unit_uuid`) REFERENCES `unit` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `product_category` (
  `product_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_uuid`,`category_uuid`),
  KEY `fk_product_category.category_uuid` (`category_uuid`),
  CONSTRAINT `fk_product_category.category_uuid` FOREIGN KEY (`category_uuid`) REFERENCES `category` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_category.product_uuid` FOREIGN KEY (`product_uuid`) REFERENCES `product` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `product_category_tree` (
  `product_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_uuid`,`category_uuid`),
  KEY `category_uuid` (`category_uuid`),
  CONSTRAINT `product_category_tree_ibfk_1` FOREIGN KEY (`product_uuid`) REFERENCES `product` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `product_category_tree_ibfk_2` FOREIGN KEY (`category_uuid`) REFERENCES `category` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `product_listing_price` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_group_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` float NOT NULL,
  `display_from_price` tinyint(1) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`),
  KEY `product_uuid` (`product_uuid`),
  KEY `customer_group_uuid` (`customer_group_uuid`),
  CONSTRAINT `product_listing_price_ibfk_1` FOREIGN KEY (`product_uuid`) REFERENCES `product` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `product_listing_price_ibfk_2` FOREIGN KEY (`customer_group_uuid`) REFERENCES `customer_group` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `product_manufacturer` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `link` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `media_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `product_manufacturer_translation` (
  `product_manufacturer_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `language_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_keywords` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`product_manufacturer_uuid`,`language_uuid`),
  KEY `language_uuid` (`language_uuid`),
  CONSTRAINT `product_manufacturer_translation_ibfk_1` FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`),
  CONSTRAINT `product_manufacturer_translation_ibfk_2` FOREIGN KEY (`product_manufacturer_uuid`) REFERENCES `product_manufacturer` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `product_media` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_cover` tinyint(1) NOT NULL,
  `position` int(11) NOT NULL DEFAULT '1',
  `media_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`),
  KEY `product_uuid` (`product_uuid`),
  KEY `media_uuid` (`media_uuid`),
  CONSTRAINT `fk_product_media.product_uuid` FOREIGN KEY (`product_uuid`) REFERENCES `product` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `product_media_ibfk_1` FOREIGN KEY (`media_uuid`) REFERENCES `media` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `product_price` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_group_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity_start` int(11) NOT NULL DEFAULT '0',
  `quantity_end` int(11) DEFAULT NULL,
  `product_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` double NOT NULL DEFAULT '0',
  `pseudo_price` double DEFAULT NULL,
  `base_price` double DEFAULT NULL,
  `percentage` decimal(10,2) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`),
  KEY `pricegroup_2` (`quantity_start`),
  KEY `pricegroup` (`quantity_end`),
  KEY `product_prices` (`quantity_start`),
  KEY `product_uuid` (`product_uuid`),
  KEY `customer_group_uuid` (`customer_group_uuid`),
  CONSTRAINT `product_price_ibfk_1` FOREIGN KEY (`product_uuid`) REFERENCES `product` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `product_price_ibfk_2` FOREIGN KEY (`customer_group_uuid`) REFERENCES `customer_group` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `product_seo_category` (
  `shop_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`shop_uuid`,`product_uuid`,`category_uuid`),
  KEY `shop_uuid_product_uuid` (`shop_uuid`,`product_uuid`),
  KEY `category_uuid` (`category_uuid`),
  KEY `fk_product_category_seo.product_uuid` (`product_uuid`),
  CONSTRAINT `fk_product_category_seo.category_uuid` FOREIGN KEY (`category_uuid`) REFERENCES `category` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_category_seo.product_uuid` FOREIGN KEY (`product_uuid`) REFERENCES `product` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_category_seo.shop_uuid` FOREIGN KEY (`shop_uuid`) REFERENCES `shop` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `product_stream` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `conditions` text COLLATE utf8mb4_unicode_ci,
  `type` int(11) DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `listing_sorting_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`),
  KEY `fk_product_stream.listing_sorting_uuid` (`listing_sorting_uuid`),
  CONSTRAINT `fk_product_stream.listing_sorting_uuid` FOREIGN KEY (`listing_sorting_uuid`) REFERENCES `listing_sorting` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `product_stream_assignment` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_stream_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`),
  KEY `fk_product_stream_assignment.product_stream_uuid` (`product_stream_uuid`),
  KEY `fk_product_stream_assignment.product_uuid` (`product_uuid`),
  CONSTRAINT `fk_product_stream_assignment.product_stream_uuid` FOREIGN KEY (`product_stream_uuid`) REFERENCES `product_stream` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_stream_assignment.product_uuid` FOREIGN KEY (`product_uuid`) REFERENCES `product` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Contains the manually assigned products of a stream';


CREATE TABLE `product_stream_tab` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_stream_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`),
  KEY `fk_product_stream_tab.product_stream_uuid` (`product_stream_uuid`),
  KEY `fk_product_stream_tab.product_uuid` (`product_uuid`),
  CONSTRAINT `fk_product_stream_tab.product_stream_uuid` FOREIGN KEY (`product_stream_uuid`) REFERENCES `product_stream` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_product_stream_tab.product_uuid` FOREIGN KEY (`product_uuid`) REFERENCES `product` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='used to assign stream as detail page tab item';


CREATE TABLE `product_translation` (
  `product_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `language_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `additional_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keywords` mediumtext COLLATE utf8mb4_unicode_ci,
  `description` mediumtext COLLATE utf8mb4_unicode_ci,
  `description_long` mediumtext COLLATE utf8mb4_unicode_ci,
  `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pack_unit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`product_uuid`,`language_uuid`),
  KEY `language_uuid` (`language_uuid`),
  CONSTRAINT `product_translation_ibfk_1` FOREIGN KEY (`product_uuid`) REFERENCES `product` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `product_translation_ibfk_2` FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `schema_version` (
  `version` varchar(14) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` datetime NOT NULL,
  `complete_date` datetime DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `error_msg` longtext COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `seo_url` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shop_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `foreign_key` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `path_info` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `seo_path_info` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_canonical` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `session` (
  `id` varchar(128) COLLATE utf8mb4_bin NOT NULL,
  `data` mediumblob NOT NULL,
  `modified` int(10) unsigned NOT NULL,
  `expiry` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sess_expiry` (`expiry`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;


CREATE TABLE `sessions` (
  `sess_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sess_data` blob NOT NULL,
  `sess_time` int(10) unsigned NOT NULL,
  `sess_lifetime` mediumint(9) NOT NULL,
  PRIMARY KEY (`sess_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `shipping_method` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` int(11) unsigned NOT NULL,
  `active` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `position` int(11) NOT NULL DEFAULT '1',
  `calculation` int(1) unsigned NOT NULL DEFAULT '0',
  `surcharge_calculation` int(1) unsigned DEFAULT NULL,
  `tax_calculation` int(11) unsigned NOT NULL DEFAULT '0',
  `shipping_free` decimal(10,2) unsigned DEFAULT NULL,
  `customer_group_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  PRIMARY KEY (`uuid`),
  KEY `fk_shipping_method.customer_group_uuid` (`customer_group_uuid`),
  CONSTRAINT `fk_shipping_method.customer_group_uuid` FOREIGN KEY (`customer_group_uuid`) REFERENCES `customer_group` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `shipping_method_price` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipping_method_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity_from` decimal(10,3) unsigned NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `factor` decimal(10,2) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`),
  UNIQUE KEY `shipping_method_uuid_quantity_from` (`shipping_method_uuid`,`quantity_from`),
  CONSTRAINT `fk_shipping_method_price.shipping_method_uuid` FOREIGN KEY (`shipping_method_uuid`) REFERENCES `shipping_method` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `shipping_method_translation` (
  `shipping_method_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `language_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` mediumtext COLLATE utf8mb4_unicode_ci,
  `comment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`shipping_method_uuid`,`language_uuid`),
  KEY `language_uuid` (`language_uuid`),
  CONSTRAINT `shipping_method_translation_ibfk_1` FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`),
  CONSTRAINT `shipping_method_translation_ibfk_2` FOREIGN KEY (`shipping_method_uuid`) REFERENCES `shipping_method` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `shop` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `parent_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shop_template_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_template_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `locale_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `currency_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_group_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fallback_translation_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_method_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_method_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`),
  UNIQUE KEY `uuid` (`uuid`),
  KEY `host` (`host`),
  KEY `parent_uuid` (`parent_uuid`),
  KEY `fk_shop.shop_template_uuid` (`shop_template_uuid`),
  KEY `fk_shop.document_template_uuid` (`document_template_uuid`),
  KEY `fk_shop.category_uuid` (`category_uuid`),
  KEY `fk_shop.locale_uuid` (`locale_uuid`),
  KEY `fk_shop.currency_uuid` (`currency_uuid`),
  KEY `fk_shop.customer_group_uuid` (`customer_group_uuid`),
  KEY `fk_shop.fallback_translation_uuid` (`fallback_translation_uuid`),
  KEY `fk_shop.payment_method_uuid` (`payment_method_uuid`),
  KEY `fk_shop.shipping_method_uuid` (`shipping_method_uuid`),
  KEY `fk_shop.country_uuid` (`country_uuid`),
  CONSTRAINT `fk_shop.category_uuid` FOREIGN KEY (`category_uuid`) REFERENCES `category` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_shop.country_uuid` FOREIGN KEY (`country_uuid`) REFERENCES `country` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_shop.currency_uuid` FOREIGN KEY (`currency_uuid`) REFERENCES `currency` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_shop.customer_group_uuid` FOREIGN KEY (`customer_group_uuid`) REFERENCES `customer_group` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_shop.document_template_uuid` FOREIGN KEY (`document_template_uuid`) REFERENCES `shop_template` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_shop.fallback_translation_uuid` FOREIGN KEY (`fallback_translation_uuid`) REFERENCES `shop` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_shop.locale_uuid` FOREIGN KEY (`locale_uuid`) REFERENCES `locale` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_shop.parent_uuid` FOREIGN KEY (`parent_uuid`) REFERENCES `shop` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_shop.payment_method_uuid` FOREIGN KEY (`payment_method_uuid`) REFERENCES `payment_method` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_shop.shipping_method_uuid` FOREIGN KEY (`shipping_method_uuid`) REFERENCES `shipping_method` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_shop.shop_template_uuid` FOREIGN KEY (`shop_template_uuid`) REFERENCES `shop_template` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `shop_currency` (
  `shop_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `currency_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`shop_uuid`,`currency_uuid`),
  KEY `fk_shop_currency.currency_uuid` (`currency_uuid`),
  CONSTRAINT `fk_shop_currency.currency_uuid` FOREIGN KEY (`currency_uuid`) REFERENCES `currency` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_shop_currency.shop_uuid` FOREIGN KEY (`shop_uuid`) REFERENCES `shop` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `shop_template` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `template` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `author` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `esi` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `style_support` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `version` int(11) unsigned NOT NULL DEFAULT '0',
  `emotion` tinyint(1) unsigned NOT NULL,
  `plugin_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`),
  UNIQUE KEY `basename` (`template`),
  KEY `parent_uuid` (`parent_uuid`),
  KEY `fk_shop_template.plugin_uuid` (`plugin_uuid`),
  CONSTRAINT `fk_shop_template.parent_uuid` FOREIGN KEY (`parent_uuid`) REFERENCES `shop_template` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_shop_template.plugin_uuid` FOREIGN KEY (`plugin_uuid`) REFERENCES `plugin` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `shop_template_config_form` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shop_template_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `options` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`),
  KEY `parent_uuid` (`parent_uuid`),
  KEY `fk_shop_template_config_form.shop_template_uuid` (`shop_template_uuid`),
  CONSTRAINT `fk_shop_template_config_form.parent_uuid` FOREIGN KEY (`parent_uuid`) REFERENCES `shop_template_config_form` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_shop_template_config_form.shop_template_uuid` FOREIGN KEY (`shop_template_uuid`) REFERENCES `shop_template` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `shop_template_config_form_field` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shop_template_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int(11) NOT NULL DEFAULT '0',
  `default_value` text COLLATE utf8mb4_unicode_ci,
  `selection` text COLLATE utf8mb4_unicode_ci,
  `field_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `support_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `allow_blank` tinyint(1) NOT NULL DEFAULT '1',
  `shop_template_config_form_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attributes` text COLLATE utf8mb4_unicode_ci,
  `less_compatible` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`),
  UNIQUE KEY `template_id_name` (`name`),
  KEY `fk_shop_template_config_form_field.shop_template_uuid` (`shop_template_uuid`),
  KEY `fk_shop_template_cff.shop_template_config_form_uuid` (`shop_template_config_form_uuid`),
  CONSTRAINT `fk_shop_template_cff.shop_template_config_form_uuid` FOREIGN KEY (`shop_template_config_form_uuid`) REFERENCES `shop_template_config_form` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_shop_template_config_form_field.shop_template_uuid` FOREIGN KEY (`shop_template_uuid`) REFERENCES `shop_template` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `shop_template_config_form_field_value` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shop_template_config_form_field_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shop_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`),
  KEY `fk_shop_template_cffv.shop_uuid` (`shop_uuid`),
  KEY `fk_shop_template_cffv.shop_template_config_form_field_uuid` (`shop_template_config_form_field_uuid`),
  CONSTRAINT `fk_shop_template_cffv.shop_template_config_form_field_uuid` FOREIGN KEY (`shop_template_config_form_field_uuid`) REFERENCES `shop_template_config_form_field` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_shop_template_cffv.shop_uuid` FOREIGN KEY (`shop_uuid`) REFERENCES `shop` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `shop_template_config_preset` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shop_template_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `element_values` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`),
  KEY `fk_shop_template_config_preset.shop_template_uuid` (`shop_template_uuid`),
  CONSTRAINT `fk_shop_template_config_preset.shop_template_uuid` FOREIGN KEY (`shop_template_uuid`) REFERENCES `shop_template` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `snippet` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `namespace` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shop_id` int(11) unsigned NOT NULL,
  `shop_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `locale` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `dirty` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`uuid`),
  KEY `fk_snippet.shop_uuid` (`shop_uuid`),
  CONSTRAINT `fk_snippet.shop_uuid` FOREIGN KEY (`shop_uuid`) REFERENCES `shop` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `tax` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tax_rate` decimal(10,2) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`),
  KEY `tax` (`tax_rate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `tax_area_rule` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_area_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_state_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_group_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tax_rate` decimal(10,2) NOT NULL,
  `active` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`),
  KEY `fk_tax_area_rule.country_uuid` (`country_uuid`),
  KEY `fk_tax_area_rule.country_area_uuid` (`country_area_uuid`),
  KEY `fk_tax_area_rule.country_state_uuid` (`country_state_uuid`),
  KEY `fk_tax_area_rule.tax_uuid` (`tax_uuid`),
  KEY `fk_tax_area_rule.customer_group_uuid` (`customer_group_uuid`),
  CONSTRAINT `fk_tax_area_rule.country_area_uuid` FOREIGN KEY (`country_area_uuid`) REFERENCES `country_area` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tax_area_rule.country_state_uuid` FOREIGN KEY (`country_state_uuid`) REFERENCES `country_state` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tax_area_rule.country_uuid` FOREIGN KEY (`country_uuid`) REFERENCES `country` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tax_area_rule.customer_group_uuid` FOREIGN KEY (`customer_group_uuid`) REFERENCES `customer_group` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tax_area_rule.tax_uuid` FOREIGN KEY (`tax_uuid`) REFERENCES `tax` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `tax_area_rule_translation` (
  `tax_area_rule_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `language_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`tax_area_rule_uuid`,`language_uuid`),
  KEY `language_uuid` (`language_uuid`),
  CONSTRAINT `tax_area_rule_translation_ibfk_1` FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`),
  CONSTRAINT `tax_area_rule_translation_ibfk_2` FOREIGN KEY (`tax_area_rule_uuid`) REFERENCES `tax_area_rule` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `unit` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `unit_translation` (
  `unit_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `language_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `short_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`unit_uuid`,`language_uuid`),
  KEY `language_uuid` (`language_uuid`),
  CONSTRAINT `unit_translation_ibfk_1` FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`),
  CONSTRAINT `unit_translation_ibfk_2` FOREIGN KEY (`unit_uuid`) REFERENCES `unit` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `user` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_role_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `encoder` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'LegacyBackendMd5',
  `api_key` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `locale_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
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
  PRIMARY KEY (`uuid`),
  UNIQUE KEY `username` (`username`),
  KEY `fk_user.locale_uuid` (`locale_uuid`),
  CONSTRAINT `fk_user.locale_uuid` FOREIGN KEY (`locale_uuid`) REFERENCES `locale` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;