-- Table creations

CREATE TABLE `album_translation` (
  `album_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `name` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

CREATE TABLE `area_translation` (
  `area_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `name` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

CREATE TABLE `area_country_translation` (
  `area_country_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `name` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

CREATE TABLE `area_country_state_translation` (
  `area_country_state_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `name` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

CREATE TABLE `attribute_configuration_translation` (
  `attribute_configuration_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `help_text` TEXT NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
  `support_text` VARCHAR(500) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
  `label` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

CREATE TABLE `blog_translation` (
  `blog_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `title` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `short_description` MEDIUMTEXT NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `description` TEXT NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `meta_keywords` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
  `meta_description` VARCHAR(150) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
  `meta_title` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;


CREATE TABLE `category_translation` (
  `category_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `name` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `meta_keywords` MEDIUMTEXT NULL COLLATE 'utf8mb4_unicode_ci',
  `meta_title` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
  `meta_description` MEDIUMTEXT NULL COLLATE 'utf8mb4_unicode_ci',
  `cms_headline` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
  `cms_description` MEDIUMTEXT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

CREATE TABLE `currency_translation` (
  `currency_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `currency` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `name` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

CREATE TABLE `customer_group_translation` (
  `customer_group_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `description` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

CREATE TABLE `filter_translation` (
  `filter_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `name` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

CREATE TABLE `filter_option_translation` (
  `filter_option_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `name` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

CREATE TABLE `filter_value_translation` (
  `filter_value_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `value` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

CREATE TABLE `holiday_translation` (
  `holiday_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `name` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

CREATE TABLE `listing_facet_translation` (
  `listing_facet_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `name` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

CREATE TABLE `listing_sorting_translation` (
  `listing_sorting_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `label` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

CREATE TABLE `mail_translation` (
  `mail_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `from_mail` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `from_name` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `subject` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `content` MEDIUMTEXT NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `content_html` MEDIUMTEXT NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

CREATE TABLE `locale_translation` (
  `locale_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `territory` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

CREATE TABLE `media_translation` (
  `media_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `name` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `description` TEXT NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

CREATE TABLE `order_state_translation` (
  `order_state_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `description` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

CREATE TABLE `payment_method_translation` (
  `payment_method_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `description` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `additional_description` MEDIUMTEXT NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

CREATE TABLE `price_group_translation` (
  `price_group_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `description` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

CREATE TABLE `product_attachment_translation` (
  `product_attachment_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `description` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

CREATE TABLE `product_configurator_group_translation` (
  `product_configurator_group_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `name` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `description` TEXT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

CREATE TABLE `product_configurator_option_translation` (
  `product_configurator_option_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `name` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

CREATE TABLE `product_media_translation` (
  `product_media_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `description` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

CREATE table `product_translation` (
  `product_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `name` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `keywords` MEDIUMTEXT NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
  `description` MEDIUMTEXT NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
  `description_long` MEDIUMTEXT NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
  `meta_title` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

CREATE TABLE `sessions` (
  `sess_id` VARCHAR(128) NOT NULL PRIMARY KEY,
  `sess_data` BLOB NOT NULL,
  `sess_time` INTEGER UNSIGNED NOT NULL,
  `sess_lifetime` MEDIUMINT NOT NULL
)
COLLATE utf8mb4_unicode_ci,
ENGINE = InnoDB
;

CREATE TABLE `shipping_method_translation` (
  `shipping_method_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `name` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `description` MEDIUMTEXT NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `comment` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

CREATE TABLE `shop_form_translation` (
  `shop_form_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `name` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `text` MEDIUMTEXT NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `email` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `email_template` MEDIUMTEXT NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `email_subject` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `text2` MEDIUMTEXT NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `meta_title` VARCHAR(255) NULL COLLATE 'utf8mb4_unicode_ci',
  `meta_keywords` VARCHAR(255) NULL COLLATE 'utf8mb4_unicode_ci',
  `meta_description` TEXT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

CREATE TABLE `shop_form_field_translation` (
  `shop_form_field_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `name` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `note` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
  `label` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `value` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

CREATE TABLE `tax_area_rule_translation` (
  `tax_area_rule_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `name` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

CREATE TABLE `unit_translation` (
  `unit_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `unit` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `description` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;


CREATE TABLE `blog_tag_translation` (
  `blog_tag_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `name` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
  COLLATE='utf8mb4_unicode_ci'
  ENGINE=InnoDB
;
CREATE TABLE `product_detail_translation` (
  `product_detail_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `additional_text` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb4_unicode_ci',
  `pack_unit` VARCHAR(255) NULL DEFAULT NULL DEFAULT '' COLLATE 'utf8mb4_unicode_ci'
)
  COLLATE='utf8mb4_unicode_ci'
  ENGINE=InnoDB
;
CREATE TABLE `product_link_translation` (
  `product_link_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `description` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `link` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL
)
  COLLATE='utf8mb4_unicode_ci'
  ENGINE=InnoDB
;

CREATE TABLE `product_manufacturer_translation` (
  `product_manufacturer_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `name` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL,
  `description` LONGTEXT NULL COLLATE 'utf8mb4_unicode_ci',
  `meta_title` VARCHAR(255) NULL COLLATE 'utf8mb4_unicode_ci',
  `meta_description` VARCHAR(255) NULL COLLATE 'utf8mb4_unicode_ci',
  `meta_keywords` VARCHAR(255) NULL COLLATE 'utf8mb4_unicode_ci'
)
  COLLATE='utf8mb4_unicode_ci'
  ENGINE=InnoDB
;


ALTER TABLE `album_translation` ADD FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`);
ALTER TABLE `area_translation` ADD FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`);
ALTER TABLE `area_country_translation` ADD FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`);
ALTER TABLE `area_country_state_translation` ADD FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`);
ALTER TABLE `attribute_configuration_translation` ADD FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`);
ALTER TABLE `blog_translation` ADD FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`);
ALTER TABLE `blog_tag_translation` ADD FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`);
ALTER TABLE `category_translation` ADD FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`);
ALTER TABLE `currency_translation` ADD FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`);
ALTER TABLE `customer_group_translation` ADD FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`);
ALTER TABLE `filter_translation` ADD FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`);
ALTER TABLE `filter_option_translation` ADD FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`);
ALTER TABLE `filter_value_translation` ADD FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`);
ALTER TABLE `holiday_translation` ADD FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`);
ALTER TABLE `listing_facet_translation` ADD FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`);
ALTER TABLE `listing_sorting_translation` ADD FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`);
ALTER TABLE `locale_translation` ADD FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`);
ALTER TABLE `mail_translation` ADD FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`);
ALTER TABLE `media_translation` ADD FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`);
ALTER TABLE `order_state_translation` ADD FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`);
ALTER TABLE `payment_method_translation` ADD FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`);
ALTER TABLE `price_group_translation` ADD FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`);
ALTER TABLE `product_attachment_translation` ADD FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`);
ALTER TABLE `product_detail_translation` ADD FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`);
ALTER TABLE `product_link_translation` ADD FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`);
ALTER TABLE `product_manufacturer_translation` ADD FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`);
ALTER TABLE `product_media_translation` ADD FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`);
ALTER TABLE `shipping_method_translation` ADD FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`);
ALTER TABLE `shop_form_translation` ADD FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`);
ALTER TABLE `shop_form_field_translation` ADD FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`);
ALTER TABLE `tax_area_rule_translation` ADD FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`);
ALTER TABLE `unit_translation` ADD FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`);

ALTER TABLE `album_translation` ADD INDEX `language_uuid` (`language_uuid`);
ALTER TABLE `area_translation` ADD INDEX `language_uuid` (`language_uuid`);
ALTER TABLE `area_country_translation` ADD INDEX `language_uuid` (`language_uuid`);
ALTER TABLE `area_country_state_translation` ADD INDEX `language_uuid` (`language_uuid`);
ALTER TABLE `attribute_configuration_translation` ADD INDEX `language_uuid` (`language_uuid`);
ALTER TABLE `blog_translation` ADD INDEX `language_uuid` (`language_uuid`);
ALTER TABLE `blog_tag_translation` ADD INDEX `language_uuid` (`language_uuid`);
ALTER TABLE `category_translation` ADD INDEX `language_uuid` (`language_uuid`);
ALTER TABLE `currency_translation` ADD INDEX `language_uuid` (`language_uuid`);
ALTER TABLE `customer_group_translation` ADD INDEX `language_uuid` (`language_uuid`);
ALTER TABLE `filter_translation` ADD INDEX `language_uuid` (`language_uuid`);
ALTER TABLE `filter_option_translation` ADD INDEX `language_uuid` (`language_uuid`);
ALTER TABLE `filter_value_translation` ADD INDEX `language_uuid` (`language_uuid`);
ALTER TABLE `holiday_translation` ADD INDEX `language_uuid` (`language_uuid`);
ALTER TABLE `listing_facet_translation` ADD INDEX `language_uuid` (`language_uuid`);
ALTER TABLE `listing_sorting_translation` ADD INDEX `language_uuid` (`language_uuid`);
ALTER TABLE `locale_translation` ADD INDEX `language_uuid` (`language_uuid`);
ALTER TABLE `mail_translation` ADD INDEX `language_uuid` (`language_uuid`);
ALTER TABLE `media_translation` ADD INDEX `language_uuid` (`language_uuid`);
ALTER TABLE `order_state_translation` ADD INDEX `language_uuid` (`language_uuid`);
ALTER TABLE `payment_method_translation` ADD INDEX `language_uuid` (`language_uuid`);
ALTER TABLE `price_group_translation` ADD INDEX `language_uuid` (`language_uuid`);
ALTER TABLE `product_attachment_translation` ADD INDEX `language_uuid` (`language_uuid`);
ALTER TABLE `product_detail_translation` ADD INDEX `language_uuid` (`language_uuid`);
ALTER TABLE `product_link_translation` ADD INDEX `language_uuid` (`language_uuid`);
ALTER TABLE `product_manufacturer_translation` ADD INDEX `language_uuid` (`language_uuid`);
ALTER TABLE `product_media_translation` ADD INDEX `language_uuid` (`language_uuid`);
ALTER TABLE `shipping_method_translation` ADD INDEX `language_uuid` (`language_uuid`);
ALTER TABLE `shop_form_translation` ADD INDEX `language_uuid` (`language_uuid`);
ALTER TABLE `shop_form_field_translation` ADD INDEX `language_uuid` (`language_uuid`);
ALTER TABLE `tax_area_rule_translation` ADD INDEX `language_uuid` (`language_uuid`);
ALTER TABLE `unit_translation` ADD INDEX `language_uuid` (`language_uuid`);

ALTER TABLE `album_translation` ADD PRIMARY KEY `album_uuid_language_uuid` (`album_uuid`, `language_uuid`);
ALTER TABLE `area_translation` ADD PRIMARY KEY `area_uuid_language_uuid` (`area_uuid`, `language_uuid`);
ALTER TABLE `area_country_translation` ADD PRIMARY KEY `area_country_uuid_language_uuid` (`area_country_uuid`, `language_uuid`);
ALTER TABLE `area_country_state_translation` ADD PRIMARY KEY `area_country_state_uuid_language_uuid` (`area_country_state_uuid`, `language_uuid`);
ALTER TABLE `attribute_configuration_translation` ADD PRIMARY KEY `attribute_configuration_uuid_language_uuid` (`attribute_configuration_uuid`, `language_uuid`);
ALTER TABLE `blog_translation` ADD PRIMARY KEY `blog_uuid_language_uuid` (`blog_uuid`, `language_uuid`);
ALTER TABLE `blog_tag_translation` ADD PRIMARY KEY `blog_tag_uuid_language_uuid` (`blog_tag_uuid`, `language_uuid`);
ALTER TABLE `category_translation` ADD PRIMARY KEY `category_uuid_language_uuid` (`category_uuid`, `language_uuid`);
ALTER TABLE `currency_translation` ADD PRIMARY KEY `currency_uuid_language_uuid` (`currency_uuid`, `language_uuid`);
ALTER TABLE `customer_group_translation` ADD PRIMARY KEY `customer_group_uuid_language_uuid` (`customer_group_uuid`, `language_uuid`);
ALTER TABLE `filter_translation` ADD PRIMARY KEY `filter_uuid_language_uuid` (`filter_uuid`, `language_uuid`);
ALTER TABLE `filter_option_translation` ADD PRIMARY KEY `filter_option_uuid_language_uuid` (`filter_option_uuid`, `language_uuid`);
ALTER TABLE `filter_value_translation` ADD PRIMARY KEY `filter_value_uuid_language_uuid` (`filter_value_uuid`, `language_uuid`);
ALTER TABLE `holiday_translation` ADD PRIMARY KEY `holiday_uuid_language_uuid` (`holiday_uuid`, `language_uuid`);
ALTER TABLE `listing_facet_translation` ADD PRIMARY KEY `listing_facet_uuid_language_uuid` (`listing_facet_uuid`, `language_uuid`);
ALTER TABLE `listing_sorting_translation` ADD PRIMARY KEY `listing_sorting_uuid_language_uuid` (`listing_sorting_uuid`, `language_uuid`);
ALTER TABLE `locale_translation` ADD PRIMARY KEY `locale_uuid_language_uuid` (`locale_uuid`, `language_uuid`);
ALTER TABLE `mail_translation` ADD PRIMARY KEY `mail_uuid_language_uuid` (`mail_uuid`, `language_uuid`);
ALTER TABLE `media_translation` ADD PRIMARY KEY `media_uuid_language_uuid` (`media_uuid`, `language_uuid`);
ALTER TABLE `order_state_translation` ADD PRIMARY KEY `order_state_uuid_language_uuid` (`order_state_uuid`, `language_uuid`);
ALTER TABLE `payment_method_translation` ADD PRIMARY KEY `payment_method_uuid_language_uuid` (`payment_method_uuid`, `language_uuid`);
ALTER TABLE `price_group_translation` ADD PRIMARY KEY `price_group_uuid_language_uuid` (`price_group_uuid`, `language_uuid`);
ALTER TABLE `product_attachment_translation` ADD PRIMARY KEY `product_attachment_uuid_language_uuid` (`product_attachment_uuid`, `language_uuid`);
ALTER TABLE `product_detail_translation` ADD PRIMARY KEY `product_detail_uuid_language_uuid` (`product_detail_uuid`, `language_uuid`);
ALTER TABLE `product_link_translation` ADD PRIMARY KEY `product_link_uuid_language_uuid` (`product_link_uuid`, `language_uuid`);
ALTER TABLE `product_manufacturer_translation` ADD PRIMARY KEY `product_manufacturer_uuid_language_uuid` (`product_manufacturer_uuid`, `language_uuid`);
ALTER TABLE `product_media_translation` ADD PRIMARY KEY `product_media_uuid_language_uuid` (`product_media_uuid`, `language_uuid`);
ALTER TABLE `shipping_method_translation` ADD PRIMARY KEY `shipping_method_uuid_language_uuid` (`shipping_method_uuid`, `language_uuid`);
ALTER TABLE `shop_form_translation` ADD PRIMARY KEY `shop_form_uuid_language_uuid` (`shop_form_uuid`, `language_uuid`);
ALTER TABLE `shop_form_field_translation` ADD PRIMARY KEY `shop_form_field_uuid_language_uuid` (`shop_form_field_uuid`, `language_uuid`);
ALTER TABLE `tax_area_rule_translation` ADD PRIMARY KEY `tax_area_rule_uuid_language_uuid` (`tax_area_rule_uuid`, `language_uuid`);
ALTER TABLE `unit_translation` ADD PRIMARY KEY `unit_uuid_language_uuid` (`unit_uuid`, `language_uuid`);
ALTER TABLE `album_translation` ADD FOREIGN KEY (`album_uuid`) REFERENCES `album` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `area_translation` ADD FOREIGN KEY (`area_uuid`) REFERENCES `area` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `area_country_translation` ADD FOREIGN KEY (`area_country_uuid`) REFERENCES `area_country` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `area_country_state_translation` ADD FOREIGN KEY (`area_country_state_uuid`) REFERENCES `area_country_state` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `attribute_configuration_translation` ADD FOREIGN KEY (`attribute_configuration_uuid`) REFERENCES `attribute_configuration` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `blog_translation` ADD FOREIGN KEY (`blog_uuid`) REFERENCES `blog` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `blog_tag_translation` ADD FOREIGN KEY (`blog_tag_uuid`) REFERENCES `blog_tag` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `category_translation` ADD FOREIGN KEY (`category_uuid`) REFERENCES `category` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `currency_translation` ADD FOREIGN KEY (`currency_uuid`) REFERENCES `currency` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `customer_group_translation` ADD FOREIGN KEY (`customer_group_uuid`) REFERENCES `customer_group` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `filter_translation` ADD FOREIGN KEY (`filter_uuid`) REFERENCES `filter` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `filter_option_translation` ADD FOREIGN KEY (`filter_option_uuid`) REFERENCES `filter_option` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `filter_value_translation` ADD FOREIGN KEY (`filter_value_uuid`) REFERENCES `filter_value` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `holiday_translation` ADD FOREIGN KEY (`holiday_uuid`) REFERENCES `holiday` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `listing_facet_translation` ADD FOREIGN KEY (`listing_facet_uuid`) REFERENCES `listing_facet` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `listing_sorting_translation` ADD FOREIGN KEY (`listing_sorting_uuid`) REFERENCES `listing_sorting` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `locale_translation` ADD FOREIGN KEY (`locale_uuid`) REFERENCES `locale` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `mail_translation` ADD FOREIGN KEY (`mail_uuid`) REFERENCES `mail` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `media_translation` ADD FOREIGN KEY (`media_uuid`) REFERENCES `media` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `order_state_translation` ADD FOREIGN KEY (`order_state_uuid`) REFERENCES `order_state` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `payment_method_translation` ADD FOREIGN KEY (`payment_method_uuid`) REFERENCES `payment_method` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `price_group_translation` ADD FOREIGN KEY (`price_group_uuid`) REFERENCES `price_group` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `product_attachment_translation` ADD FOREIGN KEY (`product_attachment_uuid`) REFERENCES `product_attachment` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `product_detail_translation` ADD FOREIGN KEY (`product_detail_uuid`) REFERENCES `product_detail` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `product_link_translation` ADD FOREIGN KEY (`product_link_uuid`) REFERENCES `product_link` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `product_manufacturer_translation` ADD FOREIGN KEY (`product_manufacturer_uuid`) REFERENCES `product_manufacturer` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `product_media_translation` ADD FOREIGN KEY (`product_media_uuid`) REFERENCES `product_media` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `shipping_method_translation` ADD FOREIGN KEY (`shipping_method_uuid`) REFERENCES `shipping_method` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `shop_form_translation` ADD FOREIGN KEY (`shop_form_uuid`) REFERENCES `shop_form` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `shop_form_field_translation` ADD FOREIGN KEY (`shop_form_field_uuid`) REFERENCES `shop_form_field` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `tax_area_rule_translation` ADD FOREIGN KEY (`tax_area_rule_uuid`) REFERENCES `tax_area_rule` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `unit_translation` ADD FOREIGN KEY (`unit_uuid`) REFERENCES `unit` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;


-- TABLE INSERTS

INSERT INTO album_translation (language_uuid, album_uuid,  name)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            a.uuid                                    AS album_uuid,
            a.name                                    AS name
        FROM
            album a
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

INSERT INTO area_translation (language_uuid, area_uuid, name)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            a.uuid                                    AS area_uuid,
            a.name                                    AS name
        FROM
            area a
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

INSERT INTO area_country_translation (language_uuid, area_country_uuid,  name)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            a.uuid                                    AS area_country,
            a.name                                    AS name
        FROM
            area_country a
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

INSERT INTO area_country_state_translation (language_uuid, area_country_state_uuid,  name)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            a.uuid                                    AS area_country_state,
            a.name                                    AS name
        FROM
            area_country_state a
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

INSERT INTO attribute_configuration_translation (language_uuid, attribute_configuration_uuid,  help_text, support_text, label)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            a.uuid                                    AS attribute_configuration,
            a.help_text                               AS help_text,
            a.support_text                            AS support_text,
            a.label                                   AS label
        FROM
            attribute_configuration a
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

INSERT INTO blog_translation (language_uuid, blog_uuid, title, short_description, description, meta_keywords, meta_description, meta_title)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            b.uuid                                    AS blog_uuid,
            b.title                                   AS title,
            b.short_description                       AS short_description,
            b.description                             AS description,
            b.meta_keywords                           AS meta_keywords,
            b.meta_description                        AS meta_description,
            b.meta_title                              AS meta_title
        FROM
            blog b
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

INSERT INTO blog_tag_translation (language_uuid, blog_tag_uuid, name)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            b.uuid                                    AS blog_tag_uuid,
            b.name                                    AS name
        FROM
            blog_tag b
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

INSERT INTO category_translation (language_uuid, category_uuid, name, meta_keywords, meta_title, meta_description, cms_headline, cms_description)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            c.uuid                                    AS category_uuid,
            c.name                                    AS name,
            c.meta_keywords                           AS meta_keywords,
            c.meta_title                              AS meta_title,
            c.meta_description                        AS meta_description,
            c.cms_headline                            AS cms_headline,
            c.cms_description                         AS cms_description
        FROM
            category c
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

INSERT INTO currency_translation (language_uuid, currency_uuid, currency, name)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            c.uuid                                    AS category_uuid,
            c.short_name                              AS currency,
            c.name                                    AS name
        FROM
            currency c
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

INSERT INTO customer_group_translation (language_uuid, customer_group_uuid, description)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            c.uuid                                    AS customer_group_uuid,
            c.name                                    AS description
        FROM
            customer_group c
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

INSERT INTO filter_translation (language_uuid, filter_uuid, name)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            f.uuid                                    AS filter_uuid,
            f.name                                    AS name
        FROM
            filter f
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

INSERT INTO filter_option_translation (language_uuid, filter_option_uuid, name)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            f.uuid                                    AS filter_option_uuid,
            f.name                                    AS name
        FROM
            filter_option f
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

INSERT INTO filter_value_translation (language_uuid, filter_value_uuid, value)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            f.uuid                                    AS filter_value_uuid,
            f.value                                   AS value
        FROM
            filter_value f
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

INSERT INTO holiday_translation (language_uuid, holiday_uuid, name)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            h.uuid                                    AS holiday_uuid,
            h.name                                    AS name
        FROM
            holiday h
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

INSERT INTO listing_facet_translation (language_uuid, listing_facet_uuid, name)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            l.uuid                                    AS listing_facet_uuid,
            l.name                                    AS name
        FROM
            listing_facet l
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

INSERT INTO listing_sorting_translation (language_uuid, listing_sorting_uuid, label)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            l.uuid                                    AS listing_sorting_uuid,
            l.label                                   AS label
        FROM
            listing_sorting l
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

INSERT INTO locale_translation (language_uuid, locale_uuid, language, territory)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            l.uuid                                    AS locale_uuid,
            l.language                                AS language,
            l.territory                               AS territory
        FROM
            locale l
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

INSERT INTO mail_translation (language_uuid, mail_uuid, from_mail, from_name, subject, content, content_html)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            m.uuid                                    AS mail_uuid,
            m.from_mail                               AS from_mail,
            m.from_name                               AS from_name,
            m.subject                                 AS subject,
            m.content                                 AS content,
            m.content_html                            AS content_html
        FROM
            mail m
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

INSERT INTO media_translation (language_uuid, media_uuid, name, description)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            m.uuid                                    AS media_uuid,
            m.name                                    AS name,
            m.description                             AS description
        FROM
            media m
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

INSERT INTO order_state_translation (language_uuid, order_state_uuid, description)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            o.uuid                                    AS order_state_uuid,
            o.description                             AS description
        FROM
            order_state o
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

INSERT INTO payment_method_translation (language_uuid, payment_method_uuid, description, additional_description)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            p.uuid                                    AS payment_method_uuid,
            p.name                                    AS description,
            p.additional_description                  AS additional_description
        FROM
            payment_method p
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

INSERT INTO price_group_translation (language_uuid, price_group_uuid, description)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            p.uuid                                    AS price_group_uuid,
            p.name                             AS description
        FROM
            price_group p
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

INSERT INTO product_attachment_translation (language_uuid, product_attachment_uuid, description)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            p.uuid                                    AS product_attachment_uuid,
            p.description                             AS description
        FROM
            product_attachment p
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

INSERT INTO product_configurator_option_translation (language_uuid, product_configurator_option_uuid, name)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            p.uuid                                    AS product_configurator_option_uuid,
            p.name                             AS name
        FROM
            product_configurator_option p
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );


INSERT INTO product_detail_translation (language_uuid, product_detail_uuid,  additional_text, pack_unit)
    (
        SELECT
            CONCAT('SWAG-SHOP-UUID-', s.id)          AS language_uuid,
            p.uuid                                          AS product_detail_uuid,
            IFNULL(p.additional_text, '')                   AS additional_text,
            IFNULL(p.pack_unit, '')                         AS pack_unit
        FROM
            product_detail p
        JOIN
            shop s ON s.fallback_id IS NULL
    );


INSERT INTO product_link_translation (language_uuid, product_link_uuid, description, link)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            p.uuid                                    AS product_link_uuid,
            p.description                             AS description,
            p.link                                    as link
        FROM
            product_link p
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );



INSERT INTO product_manufacturer_translation (language_uuid, product_manufacturer_uuid, name, description, meta_title, meta_description, meta_keywords)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            p.uuid                                    AS product_link_uuid,
            p.name                                    AS name,
            p.description                             AS description,
            p.meta_title                              AS meta_title,
            p.meta_description                        AS meta_description,
            p.meta_keywords                           AS meta_keywords
        FROM
            product_manufacturer p
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

INSERT INTO product_media_translation (language_uuid, product_media_uuid, description)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            p.uuid                                    AS product_media_uuid,
            p.description                             AS description
        FROM
            product_media p
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

INSERT INTO product_translation (product_uuid, language_uuid, name, keywords, description, description_long, meta_title)
  (
    SELECT
      p.uuid                                            AS product_uuid,
      CONCAT('SWAG-SHOP-UUID-1')                        AS language_uuid,
      p.name                                            AS name,
      p.keywords                                        AS keywords,
      p.description                                     AS description,
      p.description_long                                AS description_long,
      p.meta_title                                      AS meta_title
    FROM
      product p
  )
;

INSERT INTO shipping_method_translation (language_uuid, shipping_method_uuid, name, description, comment)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            m.uuid                                    AS shipping_method_uuid,
            m.name                                    AS name,
            m.description                             AS description,
            m.comment                                 AS comment
        FROM
            shipping_method m
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

INSERT INTO shop_form_translation (language_uuid, shop_form_uuid, name, text, email, email_template, email_subject, text2, meta_title, meta_keywords, meta_description)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            f.uuid                                    AS shop_form_uuid,
            f.name                                    AS name,
            f.text                                    AS text,
            f.email                                   AS email,
            f.email_template                          AS email_template,
            f.email_subject                           AS email_subject,
            f.text2                                   AS text2,
            f.meta_title                              AS meta_title,
            f.meta_keywords                           AS meta_keywords,
            f.meta_description                        AS meta_description
        FROM
            shop_form f
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

INSERT INTO shop_form_field_translation (language_uuid, shop_form_field_uuid, name, note, label, value)
    (
        SELECT
            s.uuid                                   AS language_uuid,
            f.uuid                                   AS shop_form_field_uuid,
            f.name                                   AS name,
            f.note                                   AS note,
            f.label                                  AS label,
            f.value                                  AS value
        FROM
            shop_form_field f
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

INSERT INTO tax_area_rule_translation (language_uuid, tax_area_rule_uuid, name)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            t.uuid                                    AS tax_area_rule_uuid,
            t.name                                    AS name
        FROM
            tax_area_rule t
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

INSERT INTO unit_translation (language_uuid, unit_uuid, unit, description)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            u.uuid                                    AS unit_uuid,
            u.short_code                              AS unit,
            u.name                                    AS description
        FROM
            unit u
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );


-- Constraints

ALTER TABLE `product_translation`
    ADD PRIMARY KEY `product_uuid_language_uuid` (`product_uuid`, `language_uuid`),
    ADD FOREIGN KEY (`product_uuid`) REFERENCES `product` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
    ADD FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE `album_translation`
  CHANGE `name` `name` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `language_uuid`;

ALTER TABLE `area_country_state_translation`
  CHANGE `name` `name` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `language_uuid`;

ALTER TABLE `area_country_translation`
  CHANGE `name` `name` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `language_uuid`;

ALTER TABLE `area_translation`
  CHANGE `name` `name` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `language_uuid`;

ALTER TABLE `currency_translation`
  CHANGE `currency` `short_name` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `language_uuid`;

ALTER TABLE `customer_group_translation`
  CHANGE `description` `name` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `language_uuid`;

ALTER TABLE `filter_value_translation`
  CHANGE `value` `value` longtext COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `language_uuid`;

ALTER TABLE `media_translation`
  CHANGE `description` `description` text COLLATE 'utf8mb4_unicode_ci' NULL AFTER `name`;

ALTER TABLE `payment_method_translation`
  CHANGE `description` `name` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `language_uuid`;

ALTER TABLE `price_group_translation`
  CHANGE `description` `name` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `language_uuid`;

ALTER TABLE `product_detail_translation`
  CHANGE `additional_text` `additional_text` varchar(255) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `language_uuid`;

ALTER TABLE `shipping_method_translation`
  CHANGE `description` `description` mediumtext COLLATE 'utf8mb4_unicode_ci' NULL AFTER `name`,
  CHANGE `comment` `comment` varchar(255) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `description`;

ALTER TABLE `unit_translation`
  CHANGE `unit` `short_code` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `language_uuid`,
  CHANGE `description` `name` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `short_code`;


# Create order and delivery tables #
CREATE TABLE `order` (
  `uuid` VARCHAR(42) NOT NULL,
  `order_date` DATETIME NOT NULL,
  `customer_uuid` VARCHAR(42) NOT NULL,
  `amount_total` DOUBLE NOT NULL,
  `position_price` DOUBLE NOT NULL,
  `shipping_total` DOUBLE NOT NULL,
  `order_state_uuid` VARCHAR(42) NOT NULL,
  `payment_method_uuid` VARCHAR(42) NOT NULL,
  `is_net` TINYINT(1) NOT NULL,
  `is_tax_free` TINYINT(1) NOT NULL,
  `currency_uuid` VARCHAR(42) NOT NULL,
  `shop_uuid` VARCHAR(42) NOT NULL,
  `billing_address_uuid` VARCHAR(42) NOT NULL,
  `context` LONGTEXT NOT NULL,
  `payload` LONGTEXT NOT NULL,
  PRIMARY KEY (`uuid`)
) COLLATE = 'utf8mb4_unicode_ci' ENGINE = InnoDB;

CREATE TABLE `order_line_item` (
  `uuid` VARCHAR(42) NOT NULL,
  `order_uuid` VARCHAR(42) NOT NULL,
  `identifier` VARCHAR(255) NOT NULL,
  `quantity` INT(11) NOT NULL,
  `unit_price` DOUBLE NOT NULL,
  `total_price` DOUBLE NOT NULL,
  `type` VARCHAR(42),
  `payload` LONGTEXT NOT NULL,
  PRIMARY KEY (`uuid`)
) COLLATE = 'utf8mb4_unicode_ci' ENGINE = InnoDB;


CREATE TABLE `order_address` (
  `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `company` varchar(255) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `department` varchar(35) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `salutation` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(100) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `first_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `street` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `zipcode` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `city` varchar(70) COLLATE utf8mb4_unicode_ci NOT NULL,
  `area_country_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
  `area_country_state_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `vat_id` varchar(50) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `phone_number` varchar(40) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `additional_address_line1` varchar(255) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `additional_address_line2` varchar(255) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  UNIQUE KEY `ui_order_address.uuid` (`uuid`),
  KEY `area_country_state_uuid` (`area_country_state_uuid`),
  KEY `area_country_uuid` (`area_country_uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `order_delivery` (
  `uuid` VARCHAR(42) NOT NULL,
  `order_uuid` VARCHAR(42) NOT NULL,
  `shipping_address_uuid` VARCHAR(42) NOT NULL,
  `order_state_uuid` VARCHAR(42) NOT NULL,
  `tracking_code` VARCHAR(200) NULL DEFAULT NULL,
  `shipping_method_uuid` VARCHAR(42) NOT NULL,
  `shipping_date_earliest` DATE NOT NULL,
  `shipping_date_latest` DATE NOT NULL,
  `payload` LONGTEXT NOT NULL,
  PRIMARY KEY (`uuid`)
) COLLATE = 'utf8mb4_unicode_ci' ENGINE = InnoDB;

CREATE TABLE `order_delivery_position` (
  `uuid` VARCHAR(42) NOT NULL,
  `order_delivery_uuid` VARCHAR(42) NOT NULL,
  `order_line_item_uuid` VARCHAR(42) NOT NULL,
  `unit_price` DOUBLE NOT NULL,
  `total_price` DOUBLE NOT NULL,
  `quantity` DOUBLE NOT NULL,
  `payload` LONGTEXT NOT NULL,
  PRIMARY KEY (`uuid`)
) COLLATE = 'utf8mb4_unicode_ci' ENGINE = InnoDB;

ALTER TABLE `order` ADD FOREIGN KEY (`customer_uuid`) REFERENCES `customer` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `order` ADD FOREIGN KEY (`order_state_uuid`) REFERENCES `order_state` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `order` ADD FOREIGN KEY (`payment_method_uuid`) REFERENCES `payment_method` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `order` ADD FOREIGN KEY (`currency_uuid`) REFERENCES `currency` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `order` ADD FOREIGN KEY (`billing_address_uuid`) REFERENCES `order_address` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `order` ADD FOREIGN KEY (`shop_uuid`) REFERENCES `shop` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `order_delivery` ADD FOREIGN KEY (`order_uuid`) REFERENCES `order` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `order_delivery` ADD FOREIGN KEY (`shipping_address_uuid`) REFERENCES `order_address` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `order_delivery` ADD FOREIGN KEY (`shipping_method_uuid`) REFERENCES `shipping_method` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `order_address` ADD FOREIGN KEY (`area_country_state_uuid`) REFERENCES `area_country_state` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `order_address` ADD FOREIGN KEY (`area_country_uuid`) REFERENCES `area_country` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `order_line_item` ADD FOREIGN KEY (`order_uuid`) REFERENCES `order` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `order_delivery_position` ADD FOREIGN KEY (`order_delivery_uuid`) REFERENCES `order_delivery` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `order_delivery_position` ADD FOREIGN KEY (`order_line_item_uuid`) REFERENCES `order_line_item` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
