-- changes needed prior to schema changes

UPDATE s_articles_prices SET `to` = 0 WHERE `to` = 'beliebig';
















-- change structure


ALTER TABLE s_articles
    RENAME TO product,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE COLUMN supplierID manufacturer_id INT(11) unsigned,
    CHANGE COLUMN shippingtime shipping_time VARCHAR(11),
    CHANGE COLUMN datum created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CHANGE COLUMN taxID tax_id INT(11) unsigned,
    CHANGE COLUMN pseudosales pseudo_sales INT(11) NOT NULL DEFAULT '0',
    CHANGE COLUMN metaTitle meta_title VARCHAR(255),
    CHANGE COLUMN changetime updated_at datetime NULL ON UPDATE CURRENT_TIMESTAMP,
    CHANGE COLUMN pricegroupID price_group_id INT(11) unsigned,
    CHANGE COLUMN filtergroupID filter_group_id INT(11) unsigned,
    CHANGE COLUMN laststock last_stock tinyint NOT NULL,
    DROP pricegroupActive,
    ADD COLUMN main_detail_uuid VARCHAR(42) NOT NULL AFTER tax_id,
    ADD COLUMN tax_uuid VARCHAR(42) NOT NULL AFTER tax_id,
    ADD product_manufacturer_uuid VARCHAR(42) NOT NULL AFTER manufacturer_id,
    ADD filter_group_uuid VARCHAR(42) AFTER filter_group_id,
    CHANGE `topseller` `topseller` tinyint NOT NULL DEFAULT '0' AFTER `pseudo_sales`,
    DROP `crossbundlelook`,
    CHANGE `notification` `notification` tinyint(1) NOT NULL COMMENT 'send notification' AFTER `last_stock`,
    CHANGE `active` `active` tinyint NOT NULL DEFAULT '0' AFTER `created_at`
;


ALTER TABLE s_article_configurator_dependencies
    RENAME TO product_configurator_dependency,
    ADD uuid VARCHAR(42) NOT NULL AFTER id
;

ALTER TABLE s_article_configurator_groups
    RENAME TO product_configurator_group,
    ADD uuid VARCHAR(42) NOT NULL AFTER id
;

ALTER TABLE s_article_configurator_groups_attributes
    RENAME TO product_configurator_group_attribute,
    ADD uuid VARCHAR(42) NOT NULL,
    CHANGE COLUMN groupID group_id INT(11) unsigned NOT NULL AFTER uuid,
    ADD product_configurator_group_uuid VARCHAR(42) NOT NULL AFTER group_id
;

ALTER TABLE s_article_configurator_option_relations
    RENAME TO product_configurator_option_relation,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE article_id product_id INT(11) unsigned NOT NULL
;

ALTER TABLE s_article_configurator_options
    RENAME TO product_configurator_option,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    ADD product_configurator_group_uuid VARCHAR(42) NOT NULL AFTER group_id
;

ALTER TABLE s_article_configurator_options_attributes
    RENAME TO product_configurator_option_attribute,
    ADD uuid VARCHAR(42) NOT NULL,
    MODIFY COLUMN optionID INT(11) unsigned NOT NULL AFTER uuid,
    ADD product_configurator_option_uuid VARCHAR(42) NOT NULL AFTER optionID
;

ALTER TABLE s_article_configurator_price_variations
    RENAME TO product_configurator_price_variation,
    ADD uuid VARCHAR(42) NOT NULL AFTER id
;

ALTER TABLE s_article_configurator_set_group_relations
    RENAME TO product_configurator_set_group_relation,
    ADD uuid VARCHAR(42) NOT NULL,
    MODIFY COLUMN  group_id INT(11) unsigned NOT NULL DEFAULT '0' AFTER uuid
;

ALTER TABLE s_article_configurator_set_option_relations
    RENAME TO product_configurator_set_option_relation
;

ALTER TABLE s_article_configurator_sets
    RENAME TO product_configurator_set,
    ADD uuid VARCHAR(42) NOT NULL AFTER id
;

ALTER TABLE s_article_configurator_template_prices
    RENAME TO product_configurator_template_price,
    ADD uuid VARCHAR(42) NOT NULL;

ALTER TABLE s_article_configurator_template_prices_attributes
    RENAME TO product_configurator_template_price_attribute,
    ADD uuid VARCHAR(42) NOT NULL AFTER id
;

ALTER TABLE s_article_configurator_templates
    RENAME TO product_configurator_template,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE article_id product_id INT(11) unsigned NOT NULL DEFAULT '0'
;


ALTER TABLE s_article_configurator_templates_attributes
    RENAME TO product_configurator_template_attribute,
    ADD uuid VARCHAR(42) NOT NULL AFTER id
;

ALTER TABLE s_articles_also_bought_ro
    RENAME TO product_also_bought_ro,
    CHANGE article_id product_id INT(11) unsigned NOT NULL,
    CHANGE related_article_id related_product_id INT(11) NOT NULL,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    ADD related_product_uuid VARCHAR(42) NOT NULL AFTER related_product_id,
    ADD product_uuid VARCHAR(42) NOT NULL AFTER product_id
;


ALTER TABLE s_articles_attributes
    RENAME TO product_attribute,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE articledetailsID product_details_id INT(11) unsigned,
    ADD product_detail_uuid VARCHAR(42) NOT NULL AFTER product_details_id
;


ALTER TABLE s_articles_avoid_customergroups
    RENAME TO product_avoid_customer_group,
    CHANGE articleID product_id INT(11) NOT NULL,
    CHANGE customergroupID customer_group_id INT(11) NOT NULL,
    ADD customer_group_uuid VARCHAR(42) NOT NULL AFTER customer_group_id,
    ADD product_uuid VARCHAR(42) NOT NULL AFTER product_id
;

ALTER TABLE s_articles_categories
    RENAME TO product_category,
    CHANGE articleID product_id INT(11) unsigned NOT NULL,
    CHANGE categoryID category_id INT(11) unsigned NOT NULL,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    ADD product_uuid VARCHAR(42) NOT NULL AFTER product_id,
    ADD category_uuid VARCHAR(42) NOT NULL AFTER category_id
;

ALTER TABLE s_articles_categories_ro
    RENAME TO product_category_ro,
    CHANGE articleID product_id INT(11) unsigned NOT NULL,
    CHANGE categoryID category_id INT(11) unsigned NOT NULL,
    CHANGE parentCategoryID parent_category_id INT(11) unsigned NOT NULL,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    ADD product_uuid VARCHAR(42) NOT NULL AFTER product_id,
    ADD category_uuid VARCHAR(42) NOT NULL AFTER category_id
;

ALTER TABLE s_articles_categories_seo
    RENAME TO product_category_seo,
    CHANGE article_id product_id INT(11) NOT NULL,
    ADD shop_uuid VARCHAR(42) NOT NULL AFTER shop_id,
    ADD product_uuid VARCHAR(42) NOT NULL AFTER product_id,
    ADD category_uuid VARCHAR(42) NOT NULL AFTER category_id
;

UPDATE s_articles_details SET kind = 0 WHERE kind != 1;

ALTER TABLE s_articles_details
    RENAME TO product_detail,
    CHANGE articleID product_id INT(11) unsigned NOT NULL DEFAULT '0',
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    ADD product_uuid VARCHAR(42) NOT NULL AFTER product_id,
    CHANGE ordernumber order_number VARCHAR(255) NOT NULL,
    CHANGE suppliernumber supplier_number VARCHAR(255),
    CHANGE additionaltext additional_text VARCHAR(255),
    CHANGE instock stock INT(11),
    CHANGE unitID unit_id INT(11),
    CHANGE purchasesteps purchase_steps INT(11) unsigned,
    CHANGE maxpurchase max_purchase INT(11) unsigned,
    CHANGE minpurchase min_purchase INT(11) unsigned NOT NULL DEFAULT '1',
    CHANGE purchaseunit purchase_unit DECIMAL(11,4) unsigned,
    CHANGE referenceunit reference_unit DECIMAL(10,3) unsigned,
    CHANGE packunit pack_unit VARCHAR(255),
    CHANGE releasedate release_date DATETIME,
    CHANGE shippingfree shipping_free tinyint NOT NULL DEFAULT '0',
    CHANGE shippingtime shipping_time VARCHAR(11),
    CHANGE purchaseprice purchase_price DOUBLE NOT NULL DEFAULT '0',
    CHANGE `active` `active` tinyint(1) NOT NULL DEFAULT '1' AFTER `sales`,
    CHANGE `kind` `is_main` tinyint(1) NOT NULL DEFAULT '0' AFTER `supplier_number`,
    ADD `unit_uuid` varchar(42) NULL AFTER `unit_id`
;


ALTER TABLE s_articles_downloads
    RENAME TO product_attachment,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE articleID product_id INT(11) unsigned NOT NULL,
    ADD product_uuid VARCHAR(42) NOT NULL AFTER product_id,
    CHANGE filename file_name VARCHAR(255) NOT NULL
;


ALTER TABLE s_articles_downloads_attributes
    RENAME TO product_attachment_attribute,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE downloadID product_attachment INT(11) unsigned AFTER uuid,
    ADD product_attachment_uuid VARCHAR(42) NOT NULL AFTER product_attachment
;

ALTER TABLE s_articles_esd
    RENAME TO product_esd,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE articledetailsID product_detail_id INT(11) NOT NULL DEFAULT '0',
    CHANGE articleID product_id INT(11) NOT NULL DEFAULT '0',
    CHANGE maxdownloads max_downloads INT(11) NOT NULL DEFAULT '0',
    CHANGE datum created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN product_uuid VARCHAR(42) NOT NULL AFTER product_id,
    ADD COLUMN product_detail_uuid VARCHAR(42) NOT NULL AFTER product_detail_id
;

ALTER TABLE s_articles_esd_attributes
    RENAME TO product_esd_attribute,
    CHANGE esdID esd_id INT(11),
    ADD COLUMN uuid VARCHAR(42) NOT NULL AFTER id,
    ADD COLUMN product_esd_uuid VARCHAR(42) NOT NULL AFTER esd_id
;

ALTER TABLE s_articles_esd_serials
    RENAME TO product_esd_serial,
    CHANGE COLUMN esdID esd_id INT(11) NOT NULL DEFAULT '0' AFTER id,
    CHANGE COLUMN serialnumber serial_number VARCHAR(255) NOT NULL,
    ADD COLUMN uuid VARCHAR(42) NOT NULL AFTER id,
    ADD COLUMN product_esd_uuid VARCHAR(42) NOT NULL AFTER esd_id
;


ALTER TABLE s_articles_img
    RENAME TO product_media,
    ADD COLUMN uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE COLUMN articleID product_id INT(11),
    CHANGE COLUMN article_detail_id product_detail_id INT(10) unsigned,
    ADD COLUMN product_uuid VARCHAR(42) NOT NULL AFTER product_id,
    ADD COLUMN product_detail_uuid VARCHAR(42) NULL AFTER product_detail_id
;


ALTER TABLE s_articles_img_attributes
    RENAME TO product_media_attribute,
    ADD COLUMN uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE COLUMN imageID image_id INT(11),
    ADD COLUMN product_media_uuid VARCHAR(42) NOT NULL AFTER image_id
;


ALTER TABLE s_article_img_mappings
    RENAME TO product_media_mapping,
    ADD COLUMN uuid VARCHAR(42) NOT NULL AFTER id,
    ADD COLUMN product_media_uuid VARCHAR(42) NOT NULL AFTER image_id
;

ALTER TABLE s_article_img_mapping_rules
    RENAME TO product_media_mapping_rule,
    ADD COLUMN uuid VARCHAR(42) NOT NULL AFTER id,
    ADD COLUMN product_media_mapping_uuid VARCHAR(42) NOT NULL AFTER mapping_id
;


ALTER TABLE s_articles_information
    RENAME TO product_link,
    ADD COLUMN uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE COLUMN articleID product_id INT(11) NOT NULL DEFAULT '0',
    ADD COLUMN product_uuid VARCHAR(42) NOT NULL AFTER product_id
;

ALTER TABLE s_articles_information_attributes
    RENAME TO product_link_attribute,
    CHANGE COLUMN informationID information_id INT(11),
    ADD COLUMN uuid VARCHAR(42) NOT NULL AFTER id,
    ADD COLUMN product_link_uuid VARCHAR(42) NOT NULL AFTER information_id
;


ALTER TABLE s_articles_notification
    RENAME TO product_notification,
    ADD COLUMN uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE COLUMN ordernumber order_number VARCHAR(255) NOT NULL,
    CHANGE COLUMN `date` created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CHANGE COLUMN shopLink shop_link VARCHAR(255) NOT NULL
;


ALTER TABLE s_articles_prices CHANGE COLUMN `to` `to` VARCHAR(50) NULL DEFAULT NULL;


ALTER TABLE s_articles_prices
    RENAME TO product_price,
    ADD COLUMN uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE COLUMN articledetailsID product_detail_id INT(11) NOT NULL DEFAULT '0',
    CHANGE COLUMN articleID product_id INT(11) NOT NULL DEFAULT '0',
    ADD COLUMN product_uuid VARCHAR(42) NOT NULL AFTER product_id,
    ADD COLUMN product_detail_uuid VARCHAR(42) NOT NULL AFTER product_detail_id,
    CHANGE `to` `to` INT(11) NULL DEFAULT NULL,
    CHANGE `from` `from` INT(11) NOT NULL DEFAULT 0
;


ALTER TABLE s_articles_prices_attributes
    RENAME TO product_price_attribute,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE priceID price_id INT(11) unsigned,
    ADD product_price_uuid VARCHAR(42) NOT NULL AFTER price_id
;


ALTER TABLE s_articles_relationships
    RENAME TO product_accessory,
    ADD uuid VARCHAR(42) NOT NULL,
    CHANGE relatedarticle related_product VARCHAR(30) NOT NULL,
    CHANGE articleID product_id INT(30) NOT NULL,
    ADD product_uuid VARCHAR(42) NOT NULL AFTER product_id,
    ADD related_product_uuid VARCHAR(42) NOT NULL AFTER related_product
;


ALTER TABLE s_articles_similar
    RENAME TO product_similar,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE relatedarticle related_product VARCHAR(255) NOT NULL,
    CHANGE articleID product_id INT(30) NOT NULL,
    ADD product_uuid VARCHAR(42) NOT NULL AFTER product_id,
    ADD related_product_uuid VARCHAR(42) NOT NULL AFTER related_product
;


ALTER TABLE s_articles_similar_shown_ro
    RENAME TO product_similar_shown_ro,
    CHANGE related_article_id related_product_id INT(11) NOT NULL,
    CHANGE article_id product_id INT(11) unsigned NOT NULL,
    CHANGE init_date created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    ADD product_uuid VARCHAR(42) NOT NULL AFTER product_id,
    ADD related_product_uuid VARCHAR(42) NOT NULL AFTER related_product_id
;


ALTER TABLE s_articles_supplier
    RENAME TO product_manufacturer,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE `changed` updated_at datetime NULL ON UPDATE CURRENT_TIMESTAMP
;


ALTER TABLE s_articles_supplier_attributes
    RENAME TO product_manufacturer_attribute,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE supplierID manufacturer_id INT(11) AFTER uuid,
    ADD product_manufacturer_uuid VARCHAR(42) NOT NULL AFTER manufacturer_id
;


ALTER TABLE s_articles_top_seller_ro
    RENAME TO product_top_seller_ro,
    CHANGE article_id product_id INT(11) unsigned NOT NULL,
    CHANGE last_cleared cleared_at DATETIME,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    ADD product_uuid VARCHAR(42) NOT NULL AFTER product_id
;

ALTER TABLE s_articles_vote
    RENAME TO product_vote,
    CHANGE articleID product_id INT(11) NOT NULL,
    CHANGE datum created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CHANGE answer_date answer_at DATETIME,
    ADD uuid VARCHAR(42) NOT NULL,
    ADD product_uuid VARCHAR(42) NOT NULL,
    ADD shop_uuid VARCHAR(42) NULL
;


ALTER TABlE s_core_shops
    ADD COLUMN uuid VARCHAR(42) NOT NULL AFTER id
;

ALTER TABlE s_core_tax
    ADD COLUMN uuid VARCHAR(42) NOT NULL AFTER id
;

ALTER TABLE s_categories RENAME TO category;
ALTER TABLE category CHANGE `description` `name` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL;
ALTER TABLE category ADD uuid VARCHAR(42) NOT NULL AFTER id;
ALTER TABLE category DROP `left`;
ALTER TABLE category DROP `right`;
ALTER TABLE category CHANGE mediaID media_id INT(11) unsigned;
ALTER TABLE category ADD media_uuid VARCHAR(42) NULL AFTER media_id;
ALTER TABLE category CHANGE cmstext cms_description MEDIUMTEXT;
ALTER TABLE category CHANGE cmsheadline cms_headline VARCHAR(255);
ALTER TABLE category CHANGE metadescription meta_description MEDIUMTEXT;
ALTER TABLE category CHANGE metakeywords meta_keywords MEDIUMTEXT;
ALTER TABLE category CHANGE changed changed_at DATETIME NOT NULL;
ALTER TABLE category MODIFY COLUMN meta_title VARCHAR(255) AFTER meta_keywords;
ALTER TABLE category CHANGE `parent` `parent_id` int(11) unsigned NULL AFTER `uuid`;
ALTER TABLE category ADD `parent_uuid` varchar(42) NULL AFTER `parent_id`;
ALTER TABLE category CHANGE `active` `active` tinyint NOT NULL AFTER `template`;
ALTER TABLE category CHANGE `hidefilter` `hide_filter` tinyint NOT NULL AFTER `external`;
ALTER TABLE category CHANGE `hidetop` `hide_top` tinyint NOT NULL AFTER `hide_filter`;
ALTER TABLE category CHANGE `hide_sortings` `hide_sortings` tinyint NOT NULL DEFAULT '0';
ALTER TABLE category CHANGE `blog` `is_blog` tinyint NOT NULL  AFTER `active`;
ALTER TABLE category CHANGE `stream_id` `product_stream_id` int unsigned NULL AFTER `product_box_layout`;
ALTER TABLE category ADD `product_stream_uuid` varchar(42) NULL AFTER `product_stream_id`;
ALTER TABLE `category` CHANGE `path` `path` longtext COLLATE 'utf8mb4_unicode_ci' NULL AFTER `parent_uuid`;


ALTER TABLE s_categories_attributes
    RENAME TO category_attribute,
    CHANGE categoryID category_id INT(11) unsigned,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    ADD category_uuid VARCHAR(42) NOT NULL AFTER category_id
;


ALTER TABLE s_categories_avoid_customergroups
    RENAME TO category_avoid_customer_group,
    CHANGE categoryID category_id INT(11),
    CHANGE customergroupID customer_group_id INT(11),
    ADD category_uuid VARCHAR(42) NOT NULL AFTER category_id,
    ADD customer_group_uuid VARCHAR(42) NOT NULL AFTER customer_group_id
;


ALTER TABLE s_filter
    RENAME filter,
    ADD COLUMN uuid VARCHAR(42) NOT NULL after id
;


ALTER TABLE s_filter_attributes
    RENAME filter_attribute,
    ADD COLUMN uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE filterID filter_id INT(11),
    ADD COLUMN filter_uuid VARCHAR(42) NOT NULL AFTER filter_id
;


ALTER TABLE s_filter_values
    RENAME filter_value,
    ADD uuid VARCHAR(42) NOT NULL after id,
    CHANGE optionID option_id INT(11) NOT NULL,
    ADD option_uuid VARCHAR(42) NOT NULL AFTER option_id,
    ADD media_uuid VARCHAR(42) AFTER media_id
;


ALTER TABLE s_filter_values_attributes
    RENAME filter_value_attribute,
    ADD COLUMN uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE valueID value_id INT(11),
    ADD COLUMN filter_value_uuid VARCHAR(42) NOT NULL AFTER value_id
;


ALTER TABLE s_filter_options
    RENAME filter_option,
    ADD uuid VARCHAR(42) NOT NULL after id
;


ALTER TABLE s_filter_options_attributes
    RENAME filter_option_attribute,
    ADD COLUMN uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE optionID option_id INT(11),
    ADD COLUMN filter_option_uuid VARCHAR(42) NOT NULL AFTER option_id
;


ALTER TABLE s_filter_articles
    RENAME filter_product,
    CHANGE articleID product_id INT(10) unsigned NOT NULL,
    CHANGE valueID value_id INT(10) unsigned NOT NULL,
    ADD product_uuid VARCHAR(42) NOT NULL AFTER product_id,
    ADD filter_value_uuid VARCHAR(42) NOT NULL AFTER value_id
;


ALTER TABLE s_filter_relations
    RENAME filter_relation,
    CHANGE groupID group_id INT(11) NOT NULL,
    CHANGE optionID option_id INT(11) NOT NULL,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    ADD filter_group_uuid VARCHAR(42) NOT NULL AFTER group_id,
    ADD filter_option_uuid VARCHAR(42) NOT NULL AFTER option_id
;

ALTER TABLE product_attribute
    DROP FOREIGN KEY product_attribute_ibfk_1,
    DROP FOREIGN KEY product_attribute_ibfk_2
;

ALTER TABLE `product`
    CHANGE COLUMN `name` `name` VARCHAR(255) NOT NULL AFTER `product_manufacturer_uuid`
;


ALTER TABLE `s_media_attributes`
    RENAME TO `media_attribute`,
    ADD `uuid` varchar(42) NOT NULL FIRST,
    ADD `media_uuid` varchar(42) NOT NULL AFTER `uuid`,
    CHANGE `mediaID` `media_id` int NULL AFTER `id`
;

ALTER TABLE `s_media`
    RENAME TO `media`,
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    ADD `album_uuid` varchar(42) NOT NULL AFTER `uuid`,
    ADD `user_uuid` varchar(42) NULL AFTER `album_uuid`,
    CHANGE `name` `name` varchar(255) NOT NULL AFTER `album_uuid`,
    CHANGE `description` `description` text NOT NULL AFTER `name`,
    CHANGE `path` `file_name` varchar(255) NOT NULL AFTER `description`,
    CHANGE `type` `mime_type` varchar(50) NOT NULL AFTER `file_name`,
    CHANGE `file_size` `file_size` int(10) unsigned NOT NULL AFTER `mime_type`,
    ADD `meta_data` TEXT NULL DEFAULT NULL AFTER `file_size`,
    CHANGE `created` `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    ADD `updated_at` datetime NULL ON UPDATE CURRENT_TIMESTAMP,
    CHANGE `albumID` `album_id` int NOT NULL AFTER `user_uuid`,
    CHANGE `userID` `user_id` int(11) NOT NULL AFTER `height`
;

ALTER TABLE `s_media_album`
    RENAME TO `album`,
    ADD `uuid` varchar(42) NOT NULL FIRST,
    ADD `parent_uuid` varchar(42) NULL AFTER `name`,
    ADD `create_thumbnails` int(11) NOT NULL,
    ADD `thumbnail_size` text COLLATE utf8mb4_unicode_ci NOT NULL,
    ADD `icon` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
    ADD `thumbnail_high_dpi` tinyint(1) NOT NULL DEFAULT 1,
    ADD `thumbnail_quality` int(11) DEFAULT NULL,
    ADD `thumbnail_high_dpi_quality` int(11) DEFAULT NULL,
    CHANGE `parentID` `parent_id` int(11) NULL AFTER `parent_uuid`
;



ALTER TABLE s_core_shops
    RENAME TO shop,
    ADD COLUMN parent_uuid VARCHAR(42) NULL DEFAULT NULL,
    ADD COLUMN shop_template_uuid VARCHAR(42) NOT NULL,
    ADD COLUMN document_template_uuid VARCHAR(42) NOT NULL,
    ADD COLUMN category_uuid VARCHAR(42) NOT NULL,
    ADD COLUMN locale_uuid VARCHAR(42) NOT NULL,
    ADD COLUMN currency_uuid VARCHAR(42) NOT NULL,
    ADD COLUMN customer_group_uuid VARCHAR(42) NOT NULL,
    ADD COLUMN fallback_locale_uuid VARCHAR(42) NULL DEFAULT NULL,
    ADD COLUMN payment_method_uuid VARCHAR(42) NOT NULL,
    ADD COLUMN shipping_method_uuid VARCHAR(42) NOT NULL,
    ADD COLUMN area_country_uuid VARCHAR(42) NOT NULL,
    CHANGE `template_id` `shop_template_id` int(11) unsigned NULL AFTER `secure`,
    CHANGE `payment_id` `payment_method_id` int(11) NOT NULL AFTER `active`,
    CHANGE `dispatch_id` `shipping_method_id` int(11) NOT NULL AFTER `payment_method_id`,
    CHANGE `country_id` `area_country_id` int(11) NOT NULL AFTER `shipping_method_id`,
    CHANGE `secure` `secure` tinyint NOT NULL AFTER `hosts`,
    CHANGE `customer_scope` `customer_scope` tinyint NOT NULL AFTER `fallback_id`,
    CHANGE `default` `is_default` tinyint NOT NULL AFTER `customer_scope`,
    CHANGE `active` `active` tinyint NOT NULL AFTER `is_default`
;

UPDATE shop s, shop m
SET
    s.base_path = m.base_path,
    s.base_url = m.base_url,
    s.host = m.host,
    s.payment_method_id = m.payment_method_id,
    s.shipping_method_id = m.shipping_method_id,
    s.area_country_id = m.area_country_id,
    s.shop_template_id = m.shop_template_id,
    s.document_template_id = m.document_template_id
WHERE s.main_id = m.id
AND s.main_id IS NOT NULL;

UPDATE shop SET base_path = '' WHERE base_path IS NULL;
UPDATE shop SET base_url = '' WHERE base_url IS NULL;
UPDATE shop SET `host` = '' WHERE `host` IS NULL;

ALTER TABLE `shop`
    CHANGE `base_path` `base_path` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `host`,
    CHANGE `base_url` `base_url` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `base_path`,
    CHANGE `host` `host` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `position`;

ALTER TABLE s_core_countries
    RENAME TO area_country,
    ADD COLUMN uuid VARCHAR(42) NOT NULL AFTER `id`,
    CHANGE `countryname` `name` varchar(255) COLLATE 'utf8mb4_unicode_ci' NULL,
    CHANGE `countryiso` `iso` varchar(255) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `name`,
    CHANGE `areaID` `area_id` int(11) NULL AFTER `iso`,
    CHANGE `countryen` `en` varchar(255) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `area_id`,
    CHANGE `shippingfree` `shipping_free` tinyint NULL AFTER `notice`,
    CHANGE `taxfree` `tax_free` tinyint NULL AFTER `shipping_free`,
    CHANGE `taxfree_ustid` `taxfree_for_vat_id` tinyint NULL AFTER `tax_free`,
    CHANGE `taxfree_ustid_checked` `taxfree_vatid_checked` tinyint NULL AFTER `taxfree_for_vat_id`,
    CHANGE `active` `active` tinyint NULL AFTER `taxfree_vatid_checked`,
    CHANGE `display_state_in_registration` `display_state_in_registration` tinyint NOT NULL AFTER `iso3`,
    CHANGE `force_state_in_registration` `force_state_in_registration` tinyint NOT NULL AFTER `display_state_in_registration`,
    ADD `area_uuid` varchar(42) NOT NULL AFTER `area_id`
;



ALTER TABLE `s_library_component`
    RENAME TO shopping_world_component,
    CHANGE `pluginID` `plugin_id` int(11) NULL AFTER `cls`,
    ADD `plugin_uuid` varchar(42) COLLATE 'utf8mb4_unicode_ci' NULL,
    ADD `uuid` varchar(42) NULL;


ALTER TABLE `s_library_component_field`
    ADD `uuid` varchar(42) NULL AFTER `id`,
    CHANGE `componentID` `shopping_world_component_id` int(11) NOT NULL AFTER `uuid`,
    ADD `shopping_world_component_uuid` varchar(42) NOT NULL AFTER `shopping_world_component_id`,
    CHANGE `allow_blank` `allow_blank` tinyint(1) NOT NULL AFTER `default_value`,
    CHANGE `translatable` `translatable` tinyint(1) NOT NULL DEFAULT '0' AFTER `allow_blank`,
    RENAME TO `shopping_world_component_field`;


ALTER TABLE `s_premium_dispatch`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `active` `active` tinyint NOT NULL AFTER `comment`,
    CHANGE `shippingfree` `shipping_free` decimal(10,2) unsigned NULL AFTER `tax_calculation`,
    CHANGE `multishopID` `shop_id` int(11) unsigned NULL AFTER `shipping_free`,
    ADD `shop_uuid` varchar(42) NULL AFTER `shop_id`,
    CHANGE `customergroupID` `customer_group_id` int(11) unsigned NULL AFTER `shop_uuid`,
    ADD `customer_group_uuid` varchar(42) NULL AFTER `customer_group_id`,
    RENAME TO `shipping_method`;



ALTER TABLE `s_premium_dispatch_attributes`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `dispatchID` `shipping_method_id` int NULL AFTER `uuid`,
    ADD `shipping_method_uuid` varchar(42) NOT NULL,
    RENAME TO `shipping_method_attribute`;



ALTER TABLE `s_premium_dispatch_categories`
    CHANGE `dispatchID` `shipping_method_id` int unsigned NOT NULL FIRST,
    ADD `shipping_method_uuid` varchar(42) NOT NULL AFTER `shipping_method_id`,
    CHANGE `categoryID` `category_id` int unsigned NOT NULL AFTER `shipping_method_uuid`,
    ADD `category_uuid` varchar(42) NOT NULL,
    RENAME TO `shipping_method_category`;



ALTER TABLE `s_premium_dispatch_countries`
    CHANGE `dispatchID` `shipping_method_id` int NOT NULL FIRST,
    ADD `shipping_method_uuid` varchar(42) NOT NULL AFTER `shipping_method_id`,
    CHANGE `countryID` `area_country_id` int NOT NULL AFTER `shipping_method_uuid`,
    ADD `area_country_uuid` varchar(42) NOT NULL,
    RENAME TO `shipping_method_country`;


ALTER TABLE `s_premium_dispatch_holidays`
    CHANGE `dispatchID` `shipping_method_id` int unsigned NOT NULL FIRST,
    ADD `shipping_method_uuid` varchar(42) NOT NULL AFTER `shipping_method_id`,
    CHANGE `holidayID` `holiday_id` int(11) unsigned NOT NULL AFTER `shipping_method_uuid`,
    ADD `holiday_uuid` varchar(42) NOT NULL,
    RENAME TO `shipping_method_holiday`;



ALTER TABLE `s_premium_dispatch_paymentmeans`
    CHANGE `dispatchID` `shipping_method_id` int NOT NULL FIRST,
    ADD `shipping_method_uuid` varchar(42) NOT NULL AFTER `shipping_method_id`,
    CHANGE `paymentID` `payment_method_id` int(11) NOT NULL AFTER `shipping_method_uuid`,
    ADD `payment_method_uuid` varchar(42) NOT NULL,
    RENAME TO `shipping_method_payment_method`;


ALTER TABLE `s_premium_holidays`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    RENAME TO `holiday`;



ALTER TABLE `s_premium_shippingcosts`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `dispatchID` `shipping_method_id` int unsigned NOT NULL AFTER `factor`,
    ADD `shipping_method_uuid` varchar(42) NOT NULL,
    RENAME TO `shipping_method_price`;




ALTER TABLE `s_product_streams`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `sorting_id` `listing_sorting_id` int(11) NULL AFTER `description`,
    ADD `listing_sorting_uuid` varchar(42) NULL,
    RENAME TO `product_stream`;



ALTER TABLE `s_product_streams_articles`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `stream_id` `product_stream_id` int unsigned NOT NULL AFTER `uuid`,
    ADD `product_stream_uuid` varchar(42) NOT NULL AFTER `product_stream_id`,
    CHANGE `article_id` `product_id` int unsigned NOT NULL AFTER `product_stream_uuid`,
    ADD `product_uuid` varchar(42) NOT NULL,
    RENAME TO `product_stream_tab`;



ALTER TABLE `s_product_streams_attributes`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `streamID` `product_stream_id` int unsigned NOT NULL AFTER `uuid`,
    ADD `product_stream_uuid` varchar(42) NOT NULL,
    RENAME TO `product_stream_attribute`;



ALTER TABLE `s_product_streams_selection`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `stream_id` `product_stream_id` int unsigned NOT NULL AFTER `uuid`,
    ADD `product_stream_uuid` varchar(42) NOT NULL AFTER `product_stream_id`,
    CHANGE `article_id` `product_id` int unsigned NOT NULL AFTER `product_stream_uuid`,
    ADD `product_uuid` varchar(42) NOT NULL,
    RENAME TO `product_stream_assignment`;



ALTER TABLE `product_stream_tab`
    COMMENT='used to assign stream as detail page tab item';

ALTER TABLE `product_stream_assignment`
    COMMENT='Contains the manually assigned products of a stream';

ALTER TABLE `s_schema_version`
    RENAME TO `schema_version`,
    CHANGE `error_msg` `error_msg` LONGTEXT COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT NULL
;

ALTER TABLE `s_search_custom_facet`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `active` `active` tinyint NOT NULL AFTER `uuid`,
    CHANGE `display_in_categories` `display_in_categories` tinyint NOT NULL AFTER `unique_key`,
    CHANGE `deletable` `deletable` tinyint NOT NULL AFTER `display_in_categories`,
    CHANGE `facet` `payload` longtext COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `name`,
    RENAME TO `listing_facet`;


ALTER TABLE `s_search_custom_sorting`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `active` `active` tinyint NOT NULL AFTER `label`,
    CHANGE `display_in_categories` `display_in_categories` tinyint NOT NULL AFTER `active`,
    CHANGE `sortings` `payload` longtext COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `position`,
    RENAME TO `listing_sorting`;


ALTER TABLE `s_statistics_article_impression`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `articleId` `product_id` int unsigned NOT NULL AFTER `uuid`,
    ADD `product_uuid` varchar(42) NOT NULL AFTER `product_id`,
    CHANGE `shopId` `shop_id` int unsigned NOT NULL AFTER `product_uuid`,
    ADD `shop_uuid` varchar(42) NOT NULL AFTER `shop_id`,
    CHANGE `date` `impression_date` date NULL DEFAULT NULL,
    CHANGE `deviceType` `device_type` varchar(50) COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT 'desktop' AFTER `impressions`,
    RENAME TO `statistic_product_impression`;



ALTER TABLE `s_statistics_currentusers`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `remoteaddr` `remote_address` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `uuid`,
    CHANGE `time` `tracking_time` datetime NULL,
    CHANGE `userID` `customer_id` int(11) NOT NULL DEFAULT '0' AFTER `tracking_time`,
    ADD `customer_uuid` varchar(42) NOT NULL AFTER `customer_id`,
    CHANGE `deviceType` `device_type` varchar(50) COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT 'desktop' AFTER `customer_uuid`,
    RENAME TO `statistic_current_customer`;



ALTER TABLE `s_statistics_pool`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `remoteaddr` `remote_address` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `uuid`,
    CHANGE `datum` `create_date` date NULL DEFAULT NULL,
    RENAME TO `statistic_address_pool`;



ALTER TABLE `s_statistics_referer`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `datum` `create_date` date NULL DEFAULT NULL,
    RENAME TO `statistic_referer`;



ALTER TABLE `s_statistics_search`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `datum` `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CHANGE `searchterm` `term` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `created_at`,
    CHANGE `results` `result_count` int(11) NOT NULL AFTER `term`,
    ADD `shop_uuid` varchar(42) NULL DEFAULT NULL,
    RENAME TO `statistic_search`;


ALTER TABLE `s_statistics_visitors`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `shopID` `shop_id` int NOT NULL AFTER `uuid`,
    ADD `shop_uuid` varchar(42) NOT NULL AFTER `shop_id`,
    CHANGE `datum` `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CHANGE `pageimpressions` `page_impressions` int(11) NOT NULL DEFAULT '0' AFTER `created_at`,
    CHANGE `uniquevisits` `unique_visits` int(11) NOT NULL DEFAULT '0' AFTER `page_impressions`,
    CHANGE `deviceType` `device_type` varchar(50) COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT 'desktop' AFTER `unique_visits`,
    RENAME TO `statistic_visitor`;


ALTER TABLE `s_user` CHANGE `firstlogin` `first_login` date NULL DEFAULT NULL,
    CHANGE `lastlogin` `last_login` datetime NULL DEFAULT NULL;

ALTER TABLE `s_user` ADD `uuid` varchar(42) NOT NULL AFTER `id`;
ALTER TABLE `s_user` CHANGE `active` `active` tinyint NOT NULL DEFAULT '0' AFTER `email`;
ALTER TABLE `s_user` CHANGE `accountmode` `account_mode` int(11) NOT NULL AFTER `active`;
ALTER TABLE `s_user` CHANGE `confirmationkey` `confirmation_key` varchar(100) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `account_mode`;
ALTER TABLE `s_user` CHANGE `paymentID` `last_payment_method_id` int(11) NOT NULL DEFAULT '0' AFTER `confirmation_key`;
ALTER TABLE `s_user` ADD `last_payment_method_uuid` varchar(42) NOT NULL AFTER `last_payment_method_id`;
ALTER TABLE `s_user` CHANGE `sessionID` `session_id` varchar(128) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `last_login`;
ALTER TABLE `s_user` CHANGE `newsletter` `newsletter` tinyint NOT NULL DEFAULT '0' AFTER `session_id`;
ALTER TABLE `s_user` CHANGE `customergroup` `customer_group_key` varchar(15) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `affiliate`;
ALTER TABLE `s_user` ADD `customer_group_uuid` varchar(42) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `customer_group_key`;
ALTER TABLE `s_user` CHANGE `paymentpreset` `default_payment_method_id` int(11) NOT NULL AFTER `customer_group_uuid`;
ALTER TABLE `s_user` ADD `default_payment_method_uuid` varchar(42) NOT NULL AFTER `default_payment_method_id`;
ALTER TABLE `s_user` CHANGE `language` `shop_id` int(11) NOT NULL AFTER `default_payment_method_uuid`;
ALTER TABLE `s_user` ADD `shop_uuid` varchar(42) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `shop_id`;
ALTER TABLE `s_user` CHANGE `subshopID` `main_shop_id` int(11) NOT NULL AFTER `shop_uuid`;
ALTER TABLE `s_user` ADD `main_shop_uuid` varchar(42) NOT NULL AFTER `main_shop_id`;
ALTER TABLE `s_user` CHANGE `pricegroupID` `price_group_id` int(11) unsigned NULL AFTER `referer`;
ALTER TABLE `s_user` ADD `price_group_uuid` varchar(42) NULL AFTER `price_group_id`;
ALTER TABLE `s_user` CHANGE `internalcomment` `internal_comment` mediumtext COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `price_group_uuid`;
ALTER TABLE `s_user` CHANGE `failedlogins` `failed_logins` int(11) NOT NULL AFTER `internal_comment`;
ALTER TABLE `s_user` CHANGE `lockeduntil` `locked_until` datetime NULL;
ALTER TABLE `s_user` ADD `default_billing_address_uuid` varchar(42) NULL AFTER `default_billing_address_id`;
ALTER TABLE `s_user` ADD `default_shipping_address_uuid` varchar(42) NULL AFTER `default_shipping_address_id`;
ALTER TABLE `s_user` CHANGE `firstname` `first_name` varchar(255) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `salutation`;
ALTER TABLE `s_user` CHANGE `lastname` `last_name` varchar(255) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `first_name`;
ALTER TABLE `s_user` CHANGE `customernumber` `customer_number` varchar(30) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `birthday`;
ALTER TABLE `s_user` RENAME TO `customer`;

ALTER TABLE `s_user_addresses` ADD `uuid` varchar(42) NOT NULL AFTER `id`;
ALTER TABLE `s_user_addresses` CHANGE `user_id` `customer_id` int NOT NULL AFTER `uuid`;
ALTER TABLE `s_user_addresses` ADD `customer_uuid` varchar(42) NOT NULL AFTER `customer_id`;
ALTER TABLE `s_user_addresses` CHANGE `firstname` `first_name` varchar(50) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `title`;
ALTER TABLE `s_user_addresses` CHANGE `lastname` `last_name` varchar(60) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `first_name`;
ALTER TABLE `s_user_addresses` CHANGE `country_id` `area_country_id` int(11) NOT NULL AFTER `city`;
ALTER TABLE `s_user_addresses` ADD `area_country_uuid` varchar(42) NOT NULL AFTER `area_country_id`;
ALTER TABLE `s_user_addresses` CHANGE `state_id` `area_country_state_id` int(11) NULL AFTER `area_country_uuid`;
ALTER TABLE `s_user_addresses` ADD `area_country_state_uuid` varchar(42) NULL AFTER `area_country_state_id`;
ALTER TABLE `s_user_addresses` CHANGE `ustid` `vat_id` varchar(50) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `area_country_state_uuid`;
ALTER TABLE `s_user_addresses` CHANGE `phone` `phone_number` varchar(40) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `vat_id`;
ALTER TABLE `s_user_addresses` RENAME TO `customer_address`;

ALTER TABLE `s_user_addresses_attributes`
    RENAME TO `customer_address_attribute`,
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    ADD `customer_address_uuid` varchar(42) NOT NULL AFTER `address_id`
;

ALTER TABLE `s_user_attributes`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `userID` `customer_id` int NULL AFTER `uuid`,
    ADD `customer_uuid` varchar(42) NOT NULL,
    RENAME TO `customer_attribute`;

ALTER TABLE `s_addon_premiums`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `startprice` `amount` double NOT NULL DEFAULT '0' AFTER `uuid`,
    CHANGE `ordernumber` `product_order_number` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '0' AFTER `amount`,
    ADD `product_uuid` varchar(42) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `product_order_number`,
    CHANGE `ordernumber_export` `premium_order_number` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `product_uuid`,
    CHANGE `subshopID` `shop_id` int NOT NULL AFTER `premium_order_number`,
    ADD `shop_uuid` varchar(42) NOT NULL,
    RENAME TO `premium_product`;

ALTER TABLE `s_attribute_configuration`
    RENAME TO `attribute_configuration`,
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `translatable` `translatable` tinyint(1) NOT NULL AFTER `position`,
    CHANGE `display_in_backend` `display_in_backend` tinyint(1) NOT NULL AFTER `translatable`,
    CHANGE `custom` `custom` tinyint(1) NOT NULL AFTER `display_in_backend`;

ALTER TABLE `s_blog`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `author_id` `user_id` int(11) NULL AFTER `title`,
    ADD `user_uuid` varchar(42) NULL AFTER `user_id`,
    CHANGE `active` `active` tinyint(1) NOT NULL AFTER `user_uuid`,
    ADD `category_uuid` varchar(42) NULL AFTER `category_id`,
    RENAME TO `blog`;

ALTER TABLE `s_blog_assigned_articles`
    ADD `blog_uuid` varchar(42) NOT NULL AFTER `blog_id`,
    CHANGE `article_id` `product_id` int unsigned NOT NULL AFTER `blog_uuid`,
    ADD `product_uuid` varchar(42) NOT NULL,
    RENAME TO `blog_product`;

ALTER TABLE `s_blog_attributes`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    ADD `blog_uuid` varchar(42) NULL AFTER `blog_id`,
    RENAME TO `blog_attribute`;

ALTER TABLE `s_blog_comments`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    ADD `blog_uuid` varchar(42) NULL AFTER `blog_id`,
    CHANGE `creation_date` `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CHANGE `active` `active` tinyint(1) NOT NULL AFTER `created_at`,
    RENAME TO `blog_comment`;

ALTER TABLE `s_blog_media`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    ADD `blog_uuid` varchar(42) NOT NULL AFTER `blog_id`,
    ADD `media_uuid` varchar(42)  NOT NULL AFTER `media_id`,
    CHANGE `preview` `preview` tinyint(1) NOT NULL AFTER `media_uuid`,
    RENAME TO `blog_media`;

ALTER TABLE `s_blog_tags`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    ADD `blog_uuid` varchar(42) NOT NULL AFTER `blog_id`,
    RENAME TO `blog_tag`;

ALTER TABLE `s_cms_static`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `tpl1variable` `variable_1` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `uuid`,
    CHANGE `tpl1path` `path_1` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `variable_1`,
    CHANGE `tpl2variable` `variable_2` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `path_1`,
    CHANGE `tpl2path` `path_2` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `variable_2`,
    CHANGE `tpl3variable` `variable_3` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `path_2`,
    CHANGE `tpl3path` `path_3` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `variable_3`,
    CHANGE `target` `link_target` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `link`,
    CHANGE `parentID` `parent_id` int(11) NOT NULL DEFAULT '0' AFTER `link_target`,
    ADD `parent_uuid` varchar(42) NULL AFTER `parent_id`,
    ADD `shop_uuids` longtext COLLATE 'utf8mb4_unicode_ci' NULL,
    RENAME TO `shop_page`;

ALTER TABLE `s_cms_static_attributes`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `cmsStaticID` `shop_page_id` int NULL AFTER `uuid`,
    ADD `shop_page_uuid` varchar(42) NULL,
    RENAME TO `shop_page_attribute`;

ALTER TABLE `s_cms_static_groups`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `active` `active` tinyint(1) NOT NULL AFTER `key`,
    RENAME TO `shop_page_group`;

ALTER TABLE `s_cms_support`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `ticket_typeID` `ticket_type_id` int(10) NOT NULL AFTER `meta_description`,
    ADD `shop_uuids` longtext COLLATE 'utf8mb4_unicode_ci' NULL,
    RENAME TO `shop_form`;

ALTER TABLE `s_cms_support_attributes`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `cmsSupportID` `shop_form_id` int NULL AFTER `uuid`,
    ADD `shop_form_uuid` varchar(42) NULL,
    RENAME TO `shop_form_attribute`;


ALTER TABLE `s_cms_support_fields`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `supportID` `shop_form_id` int NOT NULL AFTER `uuid`,
    ADD `shop_form_uuid` varchar(42) NOT NULL AFTER `shop_form_id`,
    CHANGE `error_msg` `error_msg` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `shop_form_uuid`,
    CHANGE `name` `name` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `error_msg`,
    CHANGE `note` `note` varchar(255) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `name`,
    CHANGE `typ` `type` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `note`,
    CHANGE `required` `required` tinyint(1) NOT NULL AFTER `type`,
    CHANGE `added` `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    RENAME TO `shop_form_field`;

ALTER TABLE `s_core_auth`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `roleID` `user_role_id` int(11) NOT NULL AFTER `uuid`,
    ADD `user_role_uuid` varchar(42) NOT NULL AFTER `user_role_id`,
    CHANGE `username` `user_name` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `user_role_uuid`,
    CHANGE `apiKey` `api_key` varchar(40) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `encoder`,
    CHANGE `localeID` `locale_id` int(11) NOT NULL AFTER `api_key`,
    ADD `locale_uuid` varchar(42) NOT NULL AFTER `locale_id`,
    CHANGE `sessionID` `session_id` varchar(128) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `locale_uuid`,
    CHANGE `lastlogin` `last_login` datetime NOT NULL,
    CHANGE `active` `active` tinyint(1) NOT NULL DEFAULT '0' AFTER `email`,
    CHANGE `failedlogins` `failed_logins` int(11) NOT NULL AFTER `active`,
    CHANGE `lockeduntil` `locked_until` datetime NULL,
    RENAME TO `user`;

ALTER TABLE `s_core_auth_attributes`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `authID` `user_id` int NULL AFTER `uuid`,
    ADD `user_uuid` varchar(42) NULL,
    RENAME TO `user_attribute`;

ALTER TABLE `s_core_config_elements`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `form_id` `config_form_id` int(11) unsigned NOT NULL AFTER `uuid`,
    ADD `config_form_uuid` varchar(42) NULL AFTER `config_form_id`,
    CHANGE `required` `required` tinyint NOT NULL AFTER `type`,
    RENAME TO `config_form_field`;

ALTER TABLE `s_core_config_element_translations`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `element_id` `config_form_field_id` int unsigned NOT NULL AFTER `uuid`,
    ADD `config_form_field_uuid` varchar(42) NOT NULL AFTER `config_form_field_id`,
    ADD `locale_uuid` varchar(42) NOT NULL AFTER `locale_id`,
    RENAME TO `config_form_field_translation`;

ALTER TABLE `s_core_config_forms`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    ADD `parent_uuid` varchar(42) NULL AFTER `parent_id`,
    ADD `plugin_uuid` varchar(42) NULL,
    RENAME TO `config_form`;

ALTER TABLE `s_core_config_form_translations`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `form_id` `config_form_id` int unsigned NOT NULL AFTER `uuid`,
    ADD `config_form_uuid` varchar(42) NOT NULL AFTER `config_form_id`,
    ADD `locale_uuid` varchar(42) NOT NULL AFTER `locale_id`,
    RENAME TO `config_form_translation`;

ALTER TABLE `s_core_config_mails` CHANGE `stateId` `order_state_id` int(11) NULL AFTER `id`;
ALTER TABLE `s_core_config_mails` CHANGE `frommail` `from_mail` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `name`;
ALTER TABLE `s_core_config_mails` CHANGE `fromname` `from_name` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `from_mail`;
ALTER TABLE `s_core_config_mails` CHANGE `contentHTML` `content_html` mediumtext COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `content`;
ALTER TABLE `s_core_config_mails` CHANGE `ishtml` `is_html` tinyint(1) NOT NULL AFTER `content_html`;
ALTER TABLE `s_core_config_mails` CHANGE `mailtype` `mail_type` int(11) NOT NULL DEFAULT '1' AFTER `attachment`;
ALTER TABLE `s_core_config_mails` CHANGE `dirty` `dirty` tinyint(1) NULL AFTER `context`;
ALTER TABLE `s_core_config_mails` ADD `uuid` varchar(42) NOT NULL AFTER `id`;
ALTER TABLE `s_core_config_mails` ADD `order_state_uuid` varchar(42) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `order_state_id`;
ALTER TABLE `s_core_config_mails` RENAME TO `mail`;

ALTER TABLE `s_core_config_mails_attachments`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `mailID` `mail_id` int NOT NULL AFTER `uuid`,
    ADD `mail_uuid` varchar(42) NOT NULL AFTER `mail_id`,
    CHANGE `mediaID` `media_id` int NOT NULL AFTER `mail_uuid`,
    ADD `media_uuid` varchar(42) NOT NULL AFTER `media_id`,
    CHANGE `shopID` `shop_id` int NULL DEFAULT '0' AFTER `media_uuid`,
    ADD `shop_uuid` varchar(42) NULL,
    RENAME TO `mail_attachment`;

ALTER TABLE `s_core_config_mails_attributes`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `mailID` `mail_id` int NULL AFTER `uuid`,
    ADD `mail_uuid` varchar(42) NULL,
    RENAME TO `mail_attribute`;

ALTER TABLE `s_core_config_values`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `element_id` `config_form_field_id` int unsigned NOT NULL AFTER `uuid`,
    ADD `config_form_field_uuid` varchar(42) NOT NULL AFTER `config_form_field_id`,
    ADD `shop_uuid` varchar(42) NULL AFTER `shop_id`,
    RENAME TO `config_form_field_value`;

ALTER TABLE `s_core_countries_areas`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `active` `active` tinyint NULL AFTER `name`,
    RENAME TO `area`;

ALTER TABLE `s_core_countries_attributes`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `countryID` `area_country_id` int NULL AFTER `uuid`,
    ADD `area_country_uuid` varchar(42) NULL,
    RENAME TO `area_country_attribute`;

ALTER TABLE `s_core_countries_states`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `countryID` `area_country_id` int NULL AFTER `uuid`,
    ADD `area_country_uuid` varchar(42)COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `uuid`,
    CHANGE `shortcode` `short_code` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `name`,
    CHANGE `active` `active` tinyint NULL AFTER `position`,
    RENAME TO `area_country_state`;

ALTER TABLE `s_core_countries_states_attributes`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `stateID` `area_country_state_id` int NULL AFTER `uuid`,
    ADD `area_country_state_uuid` varchar(42) NULL,
    RENAME TO `area_country_state_attribute`;

ALTER TABLE `s_core_currencies`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `standard` `standard` tinyint NOT NULL AFTER `name`,
    CHANGE `templatechar` `template_char` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `factor`,
    RENAME TO `currency`;

ALTER TABLE `s_core_customergroups`
    ADD COLUMN uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE `groupkey` `group_key` varchar(5) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `uuid`,
    CHANGE `tax` `display_gross_prices` tinyint NOT NULL DEFAULT '0' AFTER `description`,
    CHANGE `taxinput` `input_gross_prices` tinyint NOT NULL AFTER `display_gross_prices`,
    CHANGE `minimumorder` `minimum_order_amount` double NOT NULL AFTER `discount`,
    CHANGE `minimumordersurcharge` `minimum_order_amount_surcharge` double NOT NULL AFTER `minimum_order_amount`,
    CHANGE `description` `description` VARCHAR(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL,
    RENAME TO `customer_group`;

ALTER TABLE `s_core_customergroups_attributes`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `customerGroupID` `customer_group_id` int NULL AFTER `uuid`,
    ADD `customer_group_uuid` varchar(42) NULL,
    RENAME TO `customer_group_attribute`;

ALTER TABLE `s_core_customergroups_discounts`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `groupID` `customer_group_id` int NOT NULL AFTER `uuid`,
    ADD `customer_group_uuid` varchar(42) NOT NULL AFTER `customer_group_id`,
    CHANGE `basketdiscount` `discount` double NOT NULL AFTER `customer_group_uuid`,
    CHANGE `basketdiscountstart` `discount_start` double NOT NULL AFTER `discount`,
    RENAME TO `customer_group_discount`;

ALTER TABLE `s_core_locales`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    RENAME TO `locale`;

ALTER TABLE `s_core_log`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    RENAME TO `log`;

ALTER TABLE `s_core_paymentmeans`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `hide` `hide` tinyint NOT NULL AFTER `table`,
    CHANGE `additionaldescription` `additional_description` mediumtext COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `hide`,
    CHANGE `surchargestring` `surcharge_string` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `surcharge`,
    CHANGE `active` `active` tinyint(1) NOT NULL DEFAULT '0' AFTER `position`,
    CHANGE `esdactive` `allow_esd` tinyint NOT NULL AFTER `active`,
    CHANGE `embediframe` `used_iframe` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `allow_esd`,
    CHANGE `hideprospect` `hide_prospect` tinyint NOT NULL AFTER `used_iframe`,
    CHANGE `pluginID` `plugin_id` int(11) unsigned NULL AFTER `action`,
    ADD `plugin_uuid` varchar(42) NULL AFTER `plugin_id`,
    CHANGE `mobile_inactive` `mobile_inactive` tinyint(1) NOT NULL DEFAULT '0' AFTER `source`,
    RENAME TO `payment_method`;

ALTER TABLE `s_core_paymentmeans_attributes`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `paymentmeanID` `payment_method_id` int NULL AFTER `uuid`,
    ADD `payment_method_uuid` varchar(42) NULL,
    RENAME TO `payment_method_attribute`;

ALTER TABLE `s_core_paymentmeans_countries`
    CHANGE `paymentID` `payment_method_id` int unsigned NOT NULL FIRST,
    ADD `payment_method_uuid` varchar(42) NOT NULL AFTER `payment_method_id`,
    CHANGE `countryID` `area_country_id` int unsigned NOT NULL AFTER `payment_method_uuid`,
    ADD `area_country_uuid` varchar(42) NOT NULL,
    RENAME TO `payment_method_country`;

ALTER TABLE `s_core_paymentmeans_subshops`
    CHANGE `paymentID` `payment_method_id` int unsigned NOT NULL FIRST,
    ADD `payment_method_uuid` varchar(42) NOT NULL AFTER `payment_method_id`,
    CHANGE `subshopID` `shop_id` int(11) unsigned NOT NULL AFTER `payment_method_uuid`,
    ADD `shop_uuid` varchar(42) NOT NULL,
    RENAME TO `payment_method_shop`;


ALTER TABLE `s_core_plugins`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `active` `active` tinyint(1) NOT NULL AFTER `description_long`,
    CHANGE `added` `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CHANGE `capability_update` `capability_update` tinyint(1) NOT NULL,
    CHANGE `capability_install` `capability_install` tinyint(1) NOT NULL,
    CHANGE `capability_enable` `capability_enable` tinyint(1) NOT NULL,
    CHANGE `capability_secure_uninstall` `capability_secure_uninstall` tinyint(1) NOT NULL,
    RENAME TO `plugin`;

ALTER TABLE `s_core_plugin_categories`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    ADD `parent_uuid` varchar(42) NULL AFTER `parent_id`,
    RENAME TO `plugin_category`;

ALTER TABLE `s_core_pricegroups`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE COLUMN `description` `description` VARCHAR(255),
    RENAME TO `price_group`;

ALTER TABLE `s_core_pricegroups_discounts`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `groupID` `price_group_id` int NOT NULL AFTER `uuid`,
    ADD `price_group_uuid` varchar(42) NOT NULL AFTER `price_group_id`,
    CHANGE `customergroupID` `customer_group_id` int NOT NULL AFTER `price_group_uuid`,
    ADD `customer_group_uuid` varchar(42) NOT NULL AFTER `customer_group_id`,
    CHANGE `discountstart` `discount_start` double NOT NULL AFTER `discount`,
    RENAME TO `price_group_discount`;

ALTER TABLE `s_core_sessions`
    RENAME TO `session`;

ALTER TABLE `s_core_shop_currencies`
    ADD `shop_uuid` varchar(42) NOT NULL AFTER `shop_id`,
    ADD `currency_uuid` varchar(42) NOT NULL,
    RENAME TO `shop_currency`;

ALTER TABLE `s_core_shop_pages`
    ADD `shop_uuid` varchar(42) NOT NULL AFTER `shop_id`,
    CHANGE `group_id` `shop_page_group_id` int unsigned NOT NULL AFTER `shop_uuid`,
    ADD `shop_page_group_uuid` varchar(42) NOT NULL,
    RENAME TO `shop_page_group_mapping`;

ALTER TABLE `s_core_snippets`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `dirty` `dirty` tinyint(1) NULL DEFAULT '0' AFTER `updated_at`,
    CHANGE `shopID` `shop_id` int(11) unsigned NOT NULL AFTER `namespace`,
    ADD `shop_uuid` varchar(42) NOT NULL AFTER `shop_id`,
    CHANGE `localeID` `locale_id` int(11) unsigned NOT NULL AFTER `shop_uuid`,
    ADD `locale` varchar(5) NOT NULL AFTER `locale_id`,
    CHANGE `created` `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CHANGE `updated` `updated_at` datetime NULL ON UPDATE CURRENT_TIMESTAMP,
    RENAME TO `snippet`;

ALTER TABLE `s_core_states`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `mail` `mail` tinyint NOT NULL AFTER `group`,
    RENAME TO `order_state`;

ALTER TABLE `s_core_tax`
    CHANGE `tax` `tax_rate` decimal(10,2) NOT NULL AFTER `uuid`,
    RENAME TO `tax`;

ALTER TABLE `s_core_tax_rules`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `areaID` `area_id` int unsigned NULL AFTER `uuid`,
    CHANGE `countryID` `area_country_id` int unsigned NULL AFTER `area_id`,
    CHANGE `stateID` `area_country_state_id` int unsigned NULL AFTER `area_country_id`,
    CHANGE `groupID` `tax_id` int unsigned NOT NULL AFTER `area_country_state_id`,
    CHANGE `customer_groupID` `customer_group_id` int unsigned NOT NULL AFTER `tax_id`,
    CHANGE `tax` `tax_rate` decimal(10,2) NOT NULL AFTER `customer_group_id`,
    CHANGE `active` `active` tinyint NOT NULL AFTER `name`,
    ADD `area_uuid` varchar(42) NULL AFTER `area_id`,
    ADD `area_country_uuid` varchar(42) NULL AFTER `area_country_id`,
    ADD `area_country_state_uuid` varchar(42) NULL AFTER `area_country_state_id`,
    ADD `tax_uuid` varchar(42) NOT NULL AFTER `tax_id`,
    ADD `customer_group_uuid` varchar(42) NOT NULL AFTER `customer_group_id`,
    RENAME TO `tax_area_rule`;

ALTER TABLE `s_core_templates`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    ADD `plugin_uuid` varchar(42) NULL AFTER `plugin_id`,
    ADD `parent_uuid` varchar(42) NULL,
    RENAME TO `shop_template`;

ALTER TABLE `s_core_templates_config_set`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `template_id` `shop_template_id` int NOT NULL AFTER `uuid`,
    ADD `shop_template_uuid` varchar(42) NOT NULL AFTER `shop_template_id`,
    CHANGE `element_values` `element_values` longtext COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `description`,
    RENAME TO `shop_template_config_preset`;

ALTER TABLE `s_core_templates_config_elements`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `template_id` `shop_template_id` int NOT NULL AFTER `uuid`,
    ADD `shop_template_uuid` varchar(42) NOT NULL  AFTER `shop_template_id`,
    CHANGE `allow_blank` `allow_blank` tinyint(1) NOT NULL DEFAULT '1' AFTER `support_text`,
    CHANGE `less_compatible` `less_compatible` tinyint(1) NOT NULL DEFAULT '1' AFTER `attributes`,
    CHANGE `container_id` `shop_template_config_form_id` int NOT NULL AFTER `allow_blank`,
    ADD `shop_template_config_form_uuid` varchar(42) NOT NULL  AFTER `shop_template_config_form_id`,
    RENAME TO `shop_template_config_form_field`;

ALTER TABLE `s_core_templates_config_layout`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    ADD `parent_uuid` varchar(42) NULL AFTER `parent_id`,
    CHANGE `template_id` `shop_template_id` int NOT NULL AFTER `parent_uuid`,
    ADD `shop_template_uuid` varchar(42) NOT NULL AFTER `shop_template_id`,
    RENAME TO `shop_template_config_form`;

ALTER TABLE `s_core_templates_config_values`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    CHANGE `element_id` `shop_template_config_form_field_id` int NOT NULL AFTER `uuid`,
    ADD `shop_template_config_form_field_uuid` varchar(42) NOT NULL AFTER `shop_template_config_form_field_id`,
    ADD `shop_uuid` varchar(42) NOT NULL AFTER `shop_id`,
    RENAME TO `shop_template_config_form_field_value`;

ALTER TABLE `s_core_units`
    ADD `uuid` varchar(42) NOT NULL AFTER `id`,
    RENAME TO `unit`;


ALTER TABLE `statistic_search`
    CHANGE `shop_uuid` `shop_uuid` varchar(42) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `shop_id`;

ALTER TABLE `mail_attachment`
    CHANGE `uuid` `uuid` varchar(42) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `id`,
    CHANGE `mail_uuid` `mail_uuid` varchar(42) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `mail_id`,
    CHANGE `media_uuid` `media_uuid` varchar(42) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `media_id`,
    CHANGE `shop_uuid` `shop_uuid` varchar(42) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `shop_id`;

ALTER TABLE `product` CHANGE COLUMN `name` `name` VARCHAR(255) NOT NULL AFTER `product_manufacturer_uuid`;

ALTER TABLE `album`
    CHANGE `position` `position` int(11) NOT NULL DEFAULT '1' AFTER `parent_uuid`,
    CHANGE `create_thumbnails` `create_thumbnails` tinyint(1) NOT NULL DEFAULT '0' AFTER `position`,
    CHANGE `thumbnail_size` `thumbnail_size` text COLLATE 'utf8mb4_unicode_ci' NULL AFTER `create_thumbnails`,
    CHANGE `icon` `icon` varchar(50) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `thumbnail_size`;


ALTER TABLE `area` CHANGE `active` `active` tinyint(1) NOT NULL DEFAULT '1' AFTER `uuid`;

ALTER TABLE `area_country`
    CHANGE `position` `position` int(11) NOT NULL DEFAULT '1' AFTER `area_uuid`,
    CHANGE `shipping_free` `shipping_free` tinyint(1) NOT NULL DEFAULT '0' AFTER `notice`,
    CHANGE `tax_free` `tax_free` tinyint(1) NOT NULL DEFAULT '0' AFTER `shipping_free`,
    CHANGE `taxfree_for_vat_id` `taxfree_for_vat_id` tinyint(1) NOT NULL DEFAULT '0' AFTER `tax_free`,
    CHANGE `taxfree_vatid_checked` `taxfree_vatid_checked` tinyint(1) NOT NULL DEFAULT '0' AFTER `taxfree_for_vat_id`,
    CHANGE `active` `active` tinyint(1) NOT NULL DEFAULT '1' AFTER `taxfree_vatid_checked`,
    CHANGE `display_state_in_registration` `display_state_in_registration` tinyint(1) NOT NULL DEFAULT '0' AFTER `iso3`,
    CHANGE `force_state_in_registration` `force_state_in_registration` tinyint(1) NOT NULL DEFAULT '0' AFTER `display_state_in_registration`;

ALTER TABLE `area_country_state`
    CHANGE `position` `position` int(11) NOT NULL DEFAULT '1' AFTER `short_code`,
    CHANGE `active` `active` tinyint(1) NOT NULL DEFAULT '1' AFTER `position`;

ALTER TABLE `category`
    CHANGE `position` `position` int(11) unsigned NOT NULL DEFAULT '1' AFTER `name`,
    CHANGE `level` `level` int(11) unsigned NOT NULL DEFAULT '1' AFTER `position`,
    CHANGE `meta_keywords` `meta_keywords` mediumtext COLLATE 'utf8mb4_unicode_ci' NULL AFTER `level`,
    CHANGE `meta_title` `meta_title` varchar(255) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `meta_keywords`,
    CHANGE `meta_description` `meta_description` mediumtext COLLATE 'utf8mb4_unicode_ci' NULL AFTER `meta_title`,
    CHANGE `cms_headline` `cms_headline` varchar(255) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `meta_description`,
    CHANGE `cms_description` `cms_description` mediumtext COLLATE 'utf8mb4_unicode_ci' NULL AFTER `cms_headline`,
    CHANGE `template` `template` varchar(255) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `cms_description`,
    CHANGE `active` `active` tinyint(1) NOT NULL DEFAULT '1' AFTER `template`,
    CHANGE `is_blog` `is_blog` tinyint(1) NOT NULL DEFAULT '0' AFTER `active`,
    CHANGE `external` `external` varchar(255) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `is_blog`,
    CHANGE `hide_filter` `hide_filter` tinyint(1) NOT NULL DEFAULT '0' AFTER `external`,
    CHANGE `hide_top` `hide_top` tinyint(1) NOT NULL DEFAULT '0' AFTER `hide_filter`,
    CHANGE `media_uuid` `media_uuid` varchar(42) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `hide_top`,
    CHANGE `product_box_layout` `product_box_layout` varchar(50) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `media_uuid`,
    CHANGE `product_stream_uuid` `product_stream_uuid` varchar(42) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `product_box_layout`,
    CHANGE `hide_sortings` `hide_sortings` tinyint(1) NOT NULL DEFAULT '0' AFTER `product_stream_uuid`,
    CHANGE `sorting_ids` `sorting_uuids` longtext COLLATE 'utf8mb4_unicode_ci' NULL AFTER `hide_sortings`,
    CHANGE `facet_ids` `facet_uuids` longtext COLLATE 'utf8mb4_unicode_ci' NULL AFTER `sorting_uuids`,
    CHANGE `added` `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CHANGE `changed_at` `updated_at` datetime NULL ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE `config_form`
    CHANGE `label` `label` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `name`,
    CHANGE `position` `position` int(11) NOT NULL DEFAULT '1' AFTER `description`;

ALTER TABLE `config_form_field`
    CHANGE `label` `label` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `value`,
    CHANGE `required` `required` tinyint(1) unsigned NOT NULL DEFAULT '0' AFTER `type`,
    CHANGE `position` `position` int(11) NOT NULL DEFAULT '1' AFTER `required`,
    CHANGE `scope` `scope` int(11) unsigned NOT NULL DEFAULT '0' AFTER `position`;

ALTER TABLE `currency`
    CHANGE `currency` `short_name` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `uuid`,
    CHANGE `standard` `is_default` tinyint(1) NOT NULL DEFAULT '0' AFTER `name`,
    CHANGE `template_char` `symbol` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `factor`,
    CHANGE `symbol_position` `symbol_position` int(11) unsigned NOT NULL DEFAULT '0' AFTER `symbol`,
    CHANGE `position` `position` int(11) NOT NULL DEFAULT '1' AFTER `symbol_position`;


ALTER TABLE `customer`
    CHANGE `active` `active` tinyint(1) NOT NULL DEFAULT '1' AFTER `email`,
    CHANGE `account_mode` `account_mode` int(11) NOT NULL DEFAULT '0' AFTER `active`,
    CHANGE `confirmation_key` `confirmation_key` varchar(100) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `account_mode`,
    CHANGE `last_payment_method_uuid` `last_payment_method_uuid` varchar(42) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `confirmation_key`,
    CHANGE `validation` `validation` varchar(255) COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT '' AFTER `newsletter`,
    CHANGE `affiliate` `affiliate` tinyint NULL AFTER `validation`,
    CHANGE `referer` `referer` varchar(255) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `main_shop_uuid`,
    CHANGE `internal_comment` `internal_comment` mediumtext COLLATE 'utf8mb4_unicode_ci' NULL AFTER `price_group_uuid`,
    CHANGE `failed_logins` `failed_logins` int(11) NOT NULL DEFAULT '0' AFTER `internal_comment`,
    CHANGE `salutation` `salutation` varchar(30) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `title`,
    CHANGE `first_name` `first_name` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `salutation`,
    CHANGE `last_name` `last_name` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `first_name`,
    CHANGE `customer_number` `customer_number` varchar(30) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `birthday`;

ALTER TABLE `customer`
    CHANGE `customer_number` `customer_number` varchar(30) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `uuid`,
    CHANGE `salutation` `salutation` varchar(30) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `customer_number`,
    CHANGE `first_name` `first_name` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `salutation`,
    CHANGE `last_name` `last_name` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `first_name`,
    CHANGE `password` `password` varchar(1024) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `last_name`,
    CHANGE `email` `email` varchar(70) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `password`,
    CHANGE `customer_group_uuid` `customer_group_uuid` varchar(42) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `email`,
    CHANGE `default_payment_method_uuid` `default_payment_method_uuid` varchar(42) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `customer_group_uuid`,
    CHANGE `shop_uuid` `shop_uuid` varchar(42) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `default_payment_method_uuid`,
    CHANGE `main_shop_uuid` `main_shop_uuid` varchar(42) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `shop_uuid`,
    CHANGE `title` `title` varchar(100) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `main_shop_uuid`,
    CHANGE `encoder` `encoder` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT 'md5' AFTER `title`,
    CHANGE `active` `active` tinyint(1) NOT NULL DEFAULT '1' AFTER `encoder`,
    CHANGE `account_mode` `account_mode` int(11) NOT NULL DEFAULT '0' AFTER `active`,
    CHANGE `confirmation_key` `confirmation_key` varchar(100) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `account_mode`,
    CHANGE `last_payment_method_uuid` `last_payment_method_uuid` varchar(42) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `confirmation_key`,
    CHANGE `first_login` `first_login` date NULL AFTER `last_payment_method_uuid`,
    CHANGE `last_login` `last_login` datetime NULL AFTER `first_login`,
    CHANGE `session_id` `session_id` varchar(128) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `last_login`,
    CHANGE `newsletter` `newsletter` tinyint(1) NOT NULL DEFAULT '0' AFTER `session_id`,
    CHANGE `validation` `validation` varchar(255) COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT '' AFTER `newsletter`,
    CHANGE `affiliate` `affiliate` tinyint(1) NULL AFTER `validation`,
    CHANGE `customer_group_key` `customer_group_key` varchar(15) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `affiliate`,
    CHANGE `referer` `referer` varchar(255) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `customer_group_key`,
    CHANGE `price_group_uuid` `price_group_uuid` varchar(42) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `referer`,
    CHANGE `internal_comment` `internal_comment` mediumtext COLLATE 'utf8mb4_unicode_ci' NULL AFTER `price_group_uuid`,
    CHANGE `failed_logins` `failed_logins` int(11) NOT NULL DEFAULT '0' AFTER `internal_comment`,
    CHANGE `locked_until` `locked_until` datetime NULL AFTER `failed_logins`,
    CHANGE `default_billing_address_uuid` `default_billing_address_uuid` varchar(42) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `locked_until`,
    CHANGE `default_shipping_address_uuid` `default_shipping_address_uuid` varchar(42) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `default_billing_address_uuid`,
    CHANGE `birthday` `birthday` date NULL AFTER `default_shipping_address_uuid`;


ALTER TABLE `customer_group`
    CHANGE `description` `name` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `group_key`,
    CHANGE `display_gross_prices` `display_gross` tinyint(1) NOT NULL DEFAULT '1' AFTER `name`,
    CHANGE `input_gross_prices` `input_gross` tinyint(1) NOT NULL DEFAULT '1' AFTER `display_gross`,
    CHANGE `mode` `has_global_discount` tinyint(1) NOT NULL DEFAULT '0' AFTER `input_gross`,
    CHANGE `discount` `percentage_global_discount` double NULL AFTER `has_global_discount`,
    CHANGE `minimum_order_amount` `minimum_order_amount` double NULL AFTER `percentage_global_discount`,
    CHANGE `minimum_order_amount_surcharge` `minimum_order_amount_surcharge` double NULL AFTER `minimum_order_amount`;

ALTER TABLE `customer_group_discount`
    CHANGE `discount` `percentage_discount` double NOT NULL AFTER `customer_group_uuid`,
    CHANGE `discount_start` `minimum_cart_amount` double NOT NULL AFTER `percentage_discount`;

ALTER TABLE `filter`
    CHANGE `position` `position` int(11) NOT NULL DEFAULT '1' AFTER `name`,
    CHANGE `comparable` `comparable` tinyint(1) NOT NULL DEFAULT '1' AFTER `position`,
    CHANGE `sortmode` `sortmode` int(1) NOT NULL DEFAULT '0' AFTER `comparable`;

ALTER TABLE `filter_option`
    CHANGE `filterable` `filterable` tinyint(1) NOT NULL DEFAULT '1' AFTER `name`;

ALTER TABLE `filter_relation`
    CHANGE `position` `position` int(11) NOT NULL DEFAULT '1' AFTER `filter_option_uuid`;

ALTER TABLE `filter_value`
    CHANGE `position` `position` int(11) NOT NULL DEFAULT '1' AFTER `value`;

ALTER TABLE `holiday`
    CHANGE `date` `event_date` date NOT NULL AFTER `calculation`;

ALTER TABLE `listing_facet`
    CHANGE `name` `name` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `uuid`,
    CHANGE `active` `active` tinyint(1) unsigned NOT NULL DEFAULT '1' AFTER `name`,
    CHANGE `unique_key` `unique_key` varchar(100) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `active`,
    CHANGE `display_in_categories` `display_in_categories` tinyint(1) unsigned NOT NULL DEFAULT '1' AFTER `unique_key`,
    CHANGE `deletable` `deletable` tinyint(1) unsigned NOT NULL DEFAULT '1' AFTER `display_in_categories`,
    CHANGE `position` `position` int(11) NOT NULL DEFAULT '1' AFTER `deletable`;

ALTER TABLE `listing_sorting`
    CHANGE `active` `active` tinyint(1) unsigned NOT NULL DEFAULT '1' AFTER `label`,
    CHANGE `display_in_categories` `display_in_categories` tinyint(1) unsigned NOT NULL DEFAULT '1' AFTER `active`,
    CHANGE `position` `position` int(11) NOT NULL DEFAULT '1' AFTER `display_in_categories`;

ALTER TABLE `locale`
    CHANGE `locale` `code` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `uuid`;

ALTER TABLE `media`
    CHANGE `description` `description` text COLLATE 'utf8mb4_unicode_ci' NULL AFTER `name`;

ALTER TABLE `order_state`
    CHANGE `name` `name` varchar(55) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `uuid`,
    CHANGE `position` `position` int(11) NOT NULL DEFAULT '1' AFTER `description`,
    CHANGE `mail` `has_mail` tinyint(1) NOT NULL DEFAULT '0' AFTER `group`;

ALTER TABLE `order_state`
    CHANGE `group` `type` varchar(25) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `position`;

ALTER TABLE `payment_method`
    CHANGE `description` `description` varchar(255) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `name`,
    CHANGE `template` `template` varchar(255) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `description`,
    CHANGE `class` `class` varchar(255) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `template`,
    CHANGE `table` `table` varchar(70) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `class`,
    CHANGE `hide` `hide` tinyint(1) NOT NULL DEFAULT '0' AFTER `table`,
    CHANGE `additional_description` `additional_description` mediumtext COLLATE 'utf8mb4_unicode_ci' NULL AFTER `hide`,
    CHANGE `debit_percent` `percentage_surcharge` double NULL DEFAULT NULL AFTER `hide`,
    CHANGE `surcharge` `absolute_surcharge` double NULL DEFAULT NULL AFTER `percentage_surcharge`,
    CHANGE `surcharge_string` `surcharge_string` varchar(255) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `absolute_surcharge`,
    CHANGE `position` `position` int(11) NOT NULL DEFAULT '1' AFTER `surcharge_string`,
    CHANGE `allow_esd` `allow_esd` tinyint(1) NOT NULL DEFAULT '0' AFTER `active`,
    CHANGE `used_iframe` `used_iframe` varchar(255) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `allow_esd`,
    CHANGE `hide_prospect` `hide_prospect` tinyint(1) NOT NULL DEFAULT '1' AFTER `used_iframe`;

ALTER TABLE `payment_method`
    CHANGE `name` `technical_name` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `uuid`,
    CHANGE `description` `name` varchar(255) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `technical_name`;

ALTER TABLE `price_group`
    CHANGE `description` `name` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `uuid`;

ALTER TABLE `price_group_discount`
    CHANGE `discount` `percentage_discount` double NOT NULL AFTER `customer_group_uuid`,
    CHANGE `discount_start` `product_count` double NOT NULL AFTER `percentage_discount`;

ALTER TABLE `product`
    CHANGE `tax_uuid` `tax_uuid` varchar(42) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `uuid`,
    CHANGE `shipping_time` `shipping_time` varchar(11) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `product_manufacturer_uuid`,
    CHANGE `active` `active` tinyint(1) unsigned NOT NULL DEFAULT '1' AFTER `shipping_time`,
    CHANGE `pseudo_sales` `pseudo_sales` int(11) NOT NULL DEFAULT '0' AFTER `active`,
    CHANGE `topseller` `topseller` tinyint(1) unsigned NOT NULL DEFAULT '0' AFTER `pseudo_sales`,
    CHANGE `price_group_id` `price_group_id` int(11) unsigned NULL AFTER `topseller`,
    ADD `price_group_uuid` varchar(42) NULL AFTER `price_group_id`,
    CHANGE `filter_group_uuid` `filter_group_uuid` varchar(42) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `price_group_uuid`,
    CHANGE `last_stock` `is_closeout` tinyint(1) NOT NULL AFTER `filter_group_uuid`,
    CHANGE `notification` `allow_notification` tinyint(1) unsigned NOT NULL DEFAULT '0' AFTER `is_closeout`,
    CHANGE `template` `template` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `allow_notification`,
    CHANGE `available_from` `available_from` datetime NULL AFTER `template`,
    CHANGE `available_to` `available_to` datetime NULL AFTER `available_from`,
    CHANGE `configurator_set_id` `configurator_set_id` int(11) unsigned NULL AFTER `available_to`,
    CHANGE `created_at` `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CHANGE `updated_at` `updated_at` datetime NULL ON UPDATE CURRENT_TIMESTAMP
;


ALTER TABLE `product`
    CHANGE `topseller` `mark_as_topseller` tinyint(1) unsigned NOT NULL DEFAULT '0' AFTER `pseudo_sales`;

ALTER TABLE `product`
    CHANGE `is_closeout` `is_closeout` tinyint(1) NOT NULL DEFAULT '0' AFTER `filter_group_uuid`,
    CHANGE `template` `template` varchar(255) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `allow_notification`;


ALTER TABLE `product_accessory`
    CHANGE `uuid` `uuid` varchar(42) COLLATE 'utf8mb4_unicode_ci' NOT NULL FIRST,
    CHANGE `product_uuid` `product_uuid` varchar(42) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `uuid`,
    CHANGE `related_product_uuid` `related_product_uuid` varchar(42) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `product_uuid`;

UPDATE `product_detail` SET stock = 0 WHERE stock IS NULL;
ALTER TABLE `product_detail` CHANGE `stock` `stock` int(11) NOT NULL DEFAULT '0' AFTER `active`;

ALTER TABLE `product_detail`
    CHANGE `stockmin` `min_stock` int(11) unsigned NULL AFTER `stock`,
    CHANGE `position` `position` int(11) unsigned NOT NULL DEFAULT '1' AFTER `weight`,
    CHANGE `shipping_free` `shipping_free` tinyint(1) NOT NULL DEFAULT '0' AFTER `release_date`;

ALTER TABLE `product_esd`
    CHANGE `serials` `has_serials` tinyint(1) NOT NULL DEFAULT '0' AFTER `file`,
    CHANGE `notification` `allow_notification` tinyint(1) NOT NULL DEFAULT '0' AFTER `has_serials`;

ALTER TABLE `product_link`
    CHANGE `target` `target` varchar(30) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `link`;

ALTER TABLE `product_manufacturer`
    CHANGE `link` `link` varchar(100) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `name`,
    CHANGE `img` `img` varchar(255) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `link`,
    ADD `media_uuid` varchar(42) NULL AFTER `meta_description`;

ALTER TABLE `product_manufacturer`
    CHANGE `name` `name` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `uuid`,
    CHANGE `link` `link` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `name`;

ALTER TABLE `product_media`
    CHANGE `main` `is_cover` tinyint(1) NOT NULL AFTER `img`,
    CHANGE `description` `description` varchar(255) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `is_cover`,
    CHANGE `position` `position` int(11) NOT NULL DEFAULT '1' AFTER `description`,
    ADD `media_uuid` varchar(42) NOT NULL,
    ADD `parent_uuid` varchar(42) NULL AFTER `media_uuid`;

ALTER TABLE `product_price`
    ADD `customer_group_uuid` varchar(42) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `pricegroup`,
    CHANGE `from` `quantity_start` int(11) NOT NULL DEFAULT '0' AFTER `customer_group_uuid`,
    CHANGE `to` `quantity_end` int(11) NULL AFTER `quantity_start`,
    CHANGE `pseudoprice` `pseudo_price` double NULL AFTER `price`,
    CHANGE `baseprice` `base_price` double NULL AFTER `pseudo_price`,
    CHANGE `percent` `percentage` decimal(10,2) NULL AFTER `base_price`;


ALTER TABLE `product_vote`
    CHANGE `uuid` `uuid` varchar(42) COLLATE 'utf8mb4_unicode_ci' NOT NULL FIRST,
    CHANGE `name` `name` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `uuid`,
    CHANGE `product_uuid` `product_uuid` varchar(42) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `name`,
    CHANGE `headline` `headline` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `product_uuid`,
    CHANGE `comment` `comment` mediumtext COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `headline`,
    CHANGE `points` `points` double NOT NULL AFTER `comment`,
    CHANGE `active` `active` int(11) NOT NULL DEFAULT '0' AFTER `points`,
    CHANGE `email` `email` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `active`,
    CHANGE `answer` `answer` text COLLATE 'utf8mb4_unicode_ci' NULL AFTER `email`,
    CHANGE `answer_at` `answered_at` datetime NULL AFTER `answer`,
    CHANGE `shop_uuid` `shop_uuid` varchar(42) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `answered_at`,
    CHANGE `created_at` `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP;


ALTER TABLE `seo_url`
    CHANGE `is_canonical` `is_canonical` tinyint(1) NOT NULL DEFAULT '0' AFTER `seo_path_info`;

ALTER TABLE `shipping_method`
    CHANGE `active` `active` tinyint(1) unsigned NOT NULL DEFAULT '1' AFTER `comment`,
    CHANGE `position` `position` int(11) NOT NULL DEFAULT '1' AFTER `active`,
    CHANGE `calculation` `calculation` int(1) unsigned NOT NULL DEFAULT '0' AFTER `position`,
    CHANGE `surcharge_calculation` `surcharge_calculation` int(1) unsigned NULL AFTER `calculation`,
    CHANGE `tax_calculation` `tax_calculation` int(11) unsigned NOT NULL DEFAULT '0' AFTER `surcharge_calculation`,
    CHANGE `bind_instock` `bind_instock` tinyint(1) NULL AFTER `bind_time_to`,
    CHANGE `bind_laststock` `bind_laststock` tinyint(1) NOT NULL AFTER `bind_instock`,
    CHANGE `bind_shippingfree` `bind_shippingfree` tinyint(1) NOT NULL AFTER `customer_group_uuid`
;

ALTER TABLE `shipping_method_price`
    CHANGE `shipping_method_uuid` `shipping_method_uuid` varchar(42) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `uuid`,
    CHANGE `from` `quantity_from` decimal(10,3) unsigned NOT NULL AFTER `shipping_method_uuid`,
    CHANGE `value` `price` decimal(10,2) NOT NULL AFTER `quantity_from`,
    CHANGE `factor` `factor` decimal(10,2) NOT NULL AFTER `price`,
    CHANGE `shipping_method_id` `shipping_method_id` int(10) unsigned NOT NULL AFTER `factor`;

ALTER TABLE `shipping_method`
    CHANGE `description` `description` mediumtext COLLATE 'utf8mb4_unicode_ci' NULL AFTER `type`,
    CHANGE `comment` `comment` varchar(255) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `description`;


ALTER TABLE `shop`
    CHANGE `hosts` `hosts` text COLLATE 'utf8mb4_unicode_ci' NULL AFTER `base_url`,
    CHANGE `secure` `is_secure` tinyint(1) unsigned NOT NULL DEFAULT '1' AFTER `hosts`,
    CHANGE `customer_scope` `customer_scope` tinyint(1) NOT NULL DEFAULT '0' AFTER `fallback_id`,
    CHANGE `is_default` `is_default` tinyint(1) unsigned NOT NULL DEFAULT '0' AFTER `customer_scope`,
    CHANGE `active` `active` tinyint(1) NOT NULL DEFAULT '1' AFTER `is_default`,
    CHANGE `payment_method_id` `payment_method_id` int(11) NULL AFTER `active`,
    CHANGE `shipping_method_id` `shipping_method_id` int(11) NULL AFTER `payment_method_id`,
    CHANGE `area_country_id` `area_country_id` int(11) NULL AFTER `shipping_method_id`,
    CHANGE `payment_method_uuid` `payment_method_uuid` varchar(42) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `fallback_locale_uuid`,
    CHANGE `shipping_method_uuid` `shipping_method_uuid` varchar(42) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `payment_method_uuid`,
    CHANGE `area_country_uuid` `area_country_uuid` varchar(42) COLLATE 'utf8mb4_unicode_ci' NULL AFTER `shipping_method_uuid`;

ALTER TABLE `shop_template`
    CHANGE `esi` `esi` tinyint(1) unsigned NOT NULL DEFAULT '1' AFTER `license`,
    CHANGE `style_support` `style_support` tinyint(1) unsigned NOT NULL DEFAULT '1' AFTER `esi`,
    CHANGE `version` `version` int(11) unsigned NOT NULL DEFAULT '0' AFTER `style_support`;

ALTER TABLE `tax`
    CHANGE `description` `name` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `tax_rate`;

ALTER TABLE `tax_area_rule`
    CHANGE `active` `active` tinyint(1) unsigned NOT NULL DEFAULT '1' AFTER `name`;

ALTER TABLE `unit`
    CHANGE `unit` `short_code` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `uuid`,
    CHANGE `description` `name` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `short_code`;

ALTER TABLE `filter`
    CHANGE `sortmode` `sort_mode` int(1) NOT NULL DEFAULT '0' AFTER `comparable`;










-- remove old indexes

DROP INDEX articles_by_category_sort_release ON product;
DROP INDEX articles_by_category_sort_name ON product;
DROP INDEX get_similar_articles ON product_detail;
DROP INDEX articles_by_category_sort_popularity ON product_detail;
DROP INDEX articleID ON product_detail;
DROP INDEX article_images_query ON product_media;
DROP INDEX article_detail_id ON product_media;
DROP INDEX article_cover_image_query ON product_media;

-- cleanup inconsistent data

UPDATE product_price SET `quantity_end` = NULL WHERE `quantity_end` = 0;

INSERT IGNORE INTO `shop_template` (`id`, `template`, `name`, `description`, `author`, `license`, `esi`, `style_support`, `emotion`, `version`, `plugin_id`, `parent_id`) VALUES
    (11,    'Responsive',    '__theme_name__',    '__theme_description__',    '__author__',    '__license__',    1,    1,    1,    3,    NULL,    NULL);

DELETE FROM product_attribute WHERE articleID IS NULL OR product_details_id IS NULL;
DELETE FROM product_media WHERE product_id IS NULL;
DELETE FROM product_esd_serial WHERE esd_id = 1;

UPDATE product_media SET `is_cover` = 0 WHERE `is_cover` = 2;

-- create uuid data

UPDATE product_avoid_customer_group pac SET
    pac.customer_group_uuid = CONCAT('SWAG-CUSTOMER-GROUP-UUID-', pac.customer_group_id)
;

UPDATE product_category pc SET
    pc.uuid          = CONCAT('SWAG-PRODUCT-CATEGORY-UUID-', pc.id),
    pc.category_uuid = CONCAT('SWAG-CATEGORY-UUID-', pc.category_id)
;

UPDATE product_category_ro pcr SET
    pcr.uuid = CONCAT('SWAG-PRODUCT-CATEGORY-RO-UUID-', pcr.id),
    pcr.category_uuid = CONCAT('SWAG-CATEGORY-UUID-', pcr.category_id)
;

UPDATE product_category_seo pcs SET
    pcs.shop_uuid     = CONCAT('SWAG-SHOP-UUID-', pcs.shop_id),
    pcs.category_uuid = CONCAT('SWAG-CATEGORY-UUID-', pcs.product_id)
;

UPDATE product_detail pd SET
    pd.uuid = pd.order_number
;
UPDATE product_detail SET unit_uuid = CONCAT('SWAG-UNIT-UUID-', unit_id) WHERE unit_id IS NOT NULL;

UPDATE product_attachment pd SET
    pd.uuid         = CONCAT('SWAG-PRODUCT-DOWNLOAD-UUID-', pd.id)
;

UPDATE product_attachment_attribute pda SET
    pda.uuid          = CONCAT('SWAG-PRODUCT-DOWNLOAD-ATTRIBUTE-UUID-', pda.id),
    pda.product_attachment_uuid = CONCAT('SWAG-PRODUCT-DOWNLOAD-UUID-', pda.product_attachment)
;

UPDATE product_esd pe SET
    pe.uuid                = CONCAT('SWAG-PRODUCT-ES-UUID-', pe.id)
;

UPDATE product_esd_attribute pea SET
    pea.uuid = CONCAT('SWAG-PRODUCT-ES-ATTRIBUTE-UUID-', pea.id),
    pea.product_esd_uuid = CONCAT('SWAG-PRODUCT-ES-UUID-', pea.esd_id)
;

UPDATE product_esd_serial pes SET
    pes.uuid = CONCAT('SWAG-PRODUCT-ES-SERIAL-UUID-', pes.id),
    pes.product_esd_uuid = CONCAT('SWAG-PRODUCT-ES-UUID-', pes.esd_id)
;

UPDATE product_media p SET
    p.uuid                = CONCAT('SWAG-PRODUCT-IMAGE-UUID-', p.id)
;

UPDATE product_media_attribute p SET
    p.uuid = CONCAT('SWAG-PRODUCT-IMAGE-ATTRIBUTE-UUID-', p.id),
    p.product_media_uuid = CONCAT('SWAG-PRODUCT-IMAGE-UUID-', p.image_id)
;

UPDATE product_media_mapping p SET
    p.uuid = CONCAT('SWAG-PRODUCT-IMAGE-MAPPING-UUID-', p.id),
    p.product_media_uuid = CONCAT('SWAG-PRODUCT-IMAGE-UUID-', p.image_id)
;

UPDATE product_media_mapping_rule p SET
    p.uuid = CONCAT('SWAG-PRODUCT-IMAGE-MAPPING-RULE-UUID-', p.id),
    p.product_media_mapping_uuid = CONCAT('SWAG-PRODUCT-IMAGE-MAPPING-UUID-', p.mapping_id)
;

UPDATE product_link p SET
    p.uuid = CONCAT('SWAG-PRODUCT-INFORMATION-UUID-', p.id)
;

UPDATE product_link_attribute p SET
    p.uuid             = CONCAT('SWAG-PRODUCT-INFORMATION-ATTRIBUTE-UUID-', p.id),
    p.product_link_uuid = CONCAT('SWAG-PRODUCT-INFORMATION-UUID-', p.information_id)
;

UPDATE product_notification p SET
    p.uuid = CONCAT('SWAG-PRODUCT-NOTIFICATION-UUID-', p.id)
;

UPDATE product_price p SET
    p.uuid = CONCAT('SWAG-PRODUCT-PRICE-UUID-', p.id)
;

UPDATE product_price_attribute p SET
    p.uuid       = CONCAT('SWAG-PRODUCT-PRICE-ATTRIBUTE-UUID-', p.id),
    p.product_price_uuid = CONCAT('SWAG-PRODUCT-PRICE-UUID-', p.price_id)
;

UPDATE product_accessory p SET
    p.uuid                 = CONCAT('SWAG-PRODUCT-RELATIONSHIP-UUID-', p.id)
;

UPDATE product_similar p SET
    p.uuid                 = CONCAT('SWAG-PRODUCT-RELATIONSHIP-UUID-', p.id)
;

UPDATE product_similar_shown_ro p SET
    p.uuid                 = CONCAT('SWAG-PRODUCT-RELATIONSHIP-UUID-', p.id)
;

UPDATE product_manufacturer p SET
    p.uuid = CONCAT('SWAG-PRODUCT-MANUFACTURER-UUID-', p.id)
;

UPDATE product_manufacturer_attribute p SET
    p.uuid             = CONCAT('SWAG-PRODUCT-MANUFACTURER-ATTRIBUTE-UUID-', p.id),
    p.product_manufacturer_uuid = CONCAT('SWAG-PRODUCT-MANUFACTURER-UUID-', p.manufacturer_id)
;

UPDATE product_top_seller_ro p SET
    p.uuid         = CONCAT('SWAG-PRODUCT-TOP-SELLER-RO-UUID-', p.id)
;

UPDATE customer_group s SET s.uuid = CONCAT('SWAG-CUSTOMER-GROUP-UUID-', s.id);

UPDATE category c SET
    c.uuid = CONCAT('SWAG-CATEGORY-UUID-', c.id),
    c.parent_uuid = CONCAT('SWAG-CATEGORY-UUID-', c.parent_id)
;

UPDATE category c SET c.parent_uuid = CONCAT('SWAG-CATEGORY-UUID-', c.parent_id) WHERE c.parent_id IS NOT NULL;

UPDATE category_attribute c SET
    c.uuid = CONCAT('SWAG-CATEGORY-ATTRIBUTE-UUID-', c.id),
    c.category_uuid = CONCAT('SWAG-CATEGORY-UUID-', c.category_id)
;

UPDATE category_avoid_customer_group c SET
    c.customer_group_uuid = CONCAT('SWAG-CUSTOMER-GROUP-UUID-', c.customer_group_id),
    c.category_uuid = CONCAT('SWAG-CATEGORY-UUID-', c.category_id)
;

UPDATE filter f SET
    f.uuid = CONCAT('SWAG-FILTER-UUID-', f.id)
;

UPDATE filter_attribute f SET
    f.uuid = CONCAT('SWAG-FILTER-ATTRIBUTE-UUID-', f.id),
    f.filter_uuid = CONCAT('SWAG-FILTER-UUID-', f.filter_id)
;

UPDATE filter_value f SET
    f.uuid = CONCAT('SWAG-FILTER-VALUE-UUID-', f.id),
    f.option_uuid = CONCAT('SWAG-FILTER-OPTION-UUID-', f.option_id),
    f.media_uuid = CONCAT('SWAG-MEDIA-UUID-', f.media_id)
;

UPDATE filter_value_attribute f SET
    f.uuid = CONCAT('SWAG-FILTER-VALUE-ATTRIBUTE-UUID-', f.id),
    f.filter_value_uuid = CONCAT('SWAG-FILTER-VALUE-UUID-', f.value_id)
;

UPDATE filter_option f SET
    f.uuid = CONCAT('SWAG-FILTER-OPTION-UUID-', f.id)
;

UPDATE filter_option_attribute f SET
    f.uuid = CONCAT('SWAG-FILTER-OPTION-ATTRIBUTE-UUID-', f.id),
    f.filter_option_uuid = CONCAT('SWAG-FILTER-OPTION-UUID-', f.option_id)
;

UPDATE filter_product f SET
    f.filter_value_uuid = CONCAT('SWAG-FILTER-VALUE-UUID-', f.value_id)
;

UPDATE filter_relation f SET
    f.uuid = CONCAT('SWAG-FILTER-RELATION-UUID-', f.id),
    f.filter_group_uuid = CONCAT('SWAG-FILTER-UUID-', f.group_id),
    f.filter_option_uuid = CONCAT('SWAG-FILTER-OPTION-UUID-', f.option_id)
;

UPDATE product_manufacturer p, media m
    SET p.media_uuid = CONCAT('SWAG-MEDIA-UUID-', m.id)
WHERE p.img = m.file_name;

UPDATE album a, s_media_album_settings s
    SET a.create_thumbnails = s.create_thumbnails,
        a.thumbnail_size = s.thumbnail_size,
        a.icon = s.icon,
        a.thumbnail_high_dpi = s.thumbnail_high_dpi,
        a.thumbnail_quality = s.thumbnail_quality,
        a.thumbnail_high_dpi_quality = s.thumbnail_high_dpi_quality
    WHERE s.albumID = a.id;

UPDATE media
SET uuid = CONCAT('SWAG-MEDIA-UUID-', uuid),
    album_uuid = CONCAT('SWAG-ALBUM-UUID-', album_id),
    file_name = REPLACE(file_name, 'media/image/', ''),
    file_name = REPLACE(file_name, 'media/video/', ''),
    file_name = REPLACE(file_name, 'media/archive/', ''),
    file_name = REPLACE(file_name, 'media/unknown/', ''),
    file_name = REPLACE(file_name, 'media/pdf/', ''),
    file_name = REPLACE(file_name, 'media/music/', ''),
    mime_type = CONCAT('image/', substring(file_name, -3))
;

UPDATE media SET album_uuid = 'SWAG-ALBUM-UUID-1' WHERE album_uuid = 'SWAG-ALBUM-UUID-2';

UPDATE album SET
    uuid = CONCAT('SWAG-ALBUM-UUID-', uuid),
    parent_uuid = CONCAT('SWAG-ALBUM-UUID-', parent_uuid);

UPDATE `snippet` snippets
    INNER JOIN locale locale ON locale.id = snippets.locale_id
    SET
        snippets.shop_uuid = concat('SWAG-SHOP-UUID-', snippets.shop_id);


UPDATE premium_product SET shop_id = (SELECT id FROM shop LIMIT 1), shop_uuid = concat('SWAG-SHOP-UUID-', shop_id);
UPDATE product_media SET media_uuid = CONCAT('SWAG-MEDIA-UUID-', media_id) WHERE media_id IS NOT NULL;
UPDATE shop SET uuid = CONCAT('SWAG-SHOP-UUID-', id);
UPDATE shop SET parent_uuid = CONCAT('SWAG-SHOP-UUID-', main_id) WHERE main_id IS NOT NULL;
UPDATE shop SET shop_template_uuid  = CONCAT('SWAG-SHOP-TEMPLATE-UUID-', shop_template_id) WHERE shop_template_id  IS NOT NULL;
UPDATE shop SET document_template_uuid  = CONCAT('SWAG-SHOP-TEMPLATE-UUID-', document_template_id) WHERE document_template_id  IS NOT NULL;
UPDATE shop SET category_uuid  = CONCAT('SWAG-CATEGORY-UUID-', category_id) WHERE category_id IS NOT NULL;
UPDATE shop SET locale_uuid  = CONCAT('SWAG-LOCALE-UUID-', locale_id) WHERE locale_id  IS NOT NULL;
UPDATE shop SET currency_uuid  = CONCAT('SWAG-CURRENCY-UUID-', currency_id) WHERE currency_id  IS NOT NULL;
UPDATE shop SET customer_group_uuid  = CONCAT('SWAG-CUSTOMER-GROUP-UUID-', customer_group_id) WHERE customer_group_id  IS NOT NULL;
UPDATE shop SET fallback_locale_uuid  = CONCAT('SWAG-LOCALE-UUID-', fallback_id) WHERE fallback_id  IS NOT NULL;
UPDATE shop SET payment_method_uuid  = CONCAT('SWAG-PAYMENT-METHOD-UUID-', payment_method_id) WHERE payment_method_id  IS NOT NULL;
UPDATE shop SET shipping_method_uuid  = CONCAT('SWAG-SHIPPING-METHOD-UUID-', shipping_method_id) WHERE shipping_method_id  IS NOT NULL;
UPDATE shop SET area_country_uuid  = CONCAT('SWAG-AREA-COUNTRY-UUID-', area_country_id) WHERE area_country_id  IS NOT NULL;
UPDATE area_country SET uuid = CONCAT('SWAG-AREA-COUNTRY-UUID-', id);
UPDATE area_country SET area_uuid = CONCAT('SWAG-AREA-UUID-', area_id) WHERE area_id IS NOT NULL;
UPDATE shopping_world_component SET uuid = CONCAT('SWAG-SWCF-UUID-', id);
UPDATE shopping_world_component SET plugin_uuid = CONCAT('SWAG-PLUGIN-UUID-', plugin_id) WHERE plugin_uuid IS NOT NULL;
UPDATE shopping_world_component_field SET uuid = CONCAT('SWAG-SWCF-UUID-', id);
UPDATE shopping_world_component_field SET shopping_world_component_uuid = CONCAT('SWAG-SWCF-UUID-', shopping_world_component_id);
UPDATE shipping_method SET uuid = CONCAT('SWAG-SHIPPING-METHOD-UUID-', id) WHERE id IS NOT NULL;
UPDATE shipping_method SET shop_uuid = CONCAT('SWAG-SHOP-UUID-', shop_id) WHERE shop_id IS NOT NULL;
UPDATE shipping_method SET customer_group_uuid = CONCAT('SWAG-CUSTOMER-GROUP-UUID-', customer_group_id) WHERE customer_group_id IS NOT NULL;
UPDATE shipping_method_attribute SET shipping_method_uuid = CONCAT('SWAG-SHIPPING-METHOD-UUID-', shipping_method_id) WHERE shipping_method_id IS NOT NULL;
UPDATE shipping_method_attribute SET uuid = CONCAT('SWAG-SHIPPING-METHOD-ATTRIBUTE-UUID-', id) WHERE id IS NOT NULL;
UPDATE shipping_method_category SET shipping_method_uuid = CONCAT('SWAG-SHIPPING-METHOD-UUID-', shipping_method_id) WHERE shipping_method_id IS NOT NULL;
UPDATE shipping_method_category SET category_uuid = CONCAT('SWAG-CATEGORY-UUID-', category_id) WHERE category_id IS NOT NULL;
UPDATE shipping_method_country SET shipping_method_uuid = CONCAT('SWAG-SHIPPING-METHOD-UUID-', shipping_method_id) WHERE shipping_method_id IS NOT NULL;
UPDATE shipping_method_country SET area_country_uuid = CONCAT('SWAG-AREA-COUNTRY-UUID-', area_country_id) WHERE area_country_id IS NOT NULL;
UPDATE shipping_method_holiday SET shipping_method_uuid = CONCAT('SWAG-SHIPPING-METHOD-UUID-', shipping_method_id) WHERE shipping_method_id IS NOT NULL;
UPDATE shipping_method_holiday SET holiday_uuid = CONCAT('SWAG-HOLIDAY-UUID-', holiday_id) WHERE holiday_id IS NOT NULL;
UPDATE shipping_method_payment_method SET shipping_method_uuid = CONCAT('SWAG-SHIPPING-METHOD-UUID-', shipping_method_id) WHERE shipping_method_id IS NOT NULL;
UPDATE shipping_method_payment_method SET payment_method_uuid = CONCAT('SWAG-PAYMENT-METHOD-UUID-', payment_method_id) WHERE payment_method_id IS NOT NULL;
UPDATE holiday SET uuid = CONCAT('SWAG-HOLIDAY-UUID-', id) WHERE id IS NOT NULL;
UPDATE shipping_method_price SET uuid = CONCAT('SWAG-SHIPPING-COST-PRICE-UUID-', id) WHERE id IS NOT NULL;
UPDATE shipping_method_price SET shipping_method_uuid = CONCAT('SWAG-SHIPPING-METHOD-UUID-', shipping_method_id) WHERE shipping_method_id IS NOT NULL;
UPDATE category SET product_stream_uuid = CONCAT('SWAG-PRODUCT-STREAM-UUID-', product_stream_id) WHERE product_stream_id IS NOT NULL;
UPDATE category SET media_uuid = CONCAT('SWAG-MEDIA-UUID-', media_id) WHERE media_id IS NOT NULL AND media_id > 0;
UPDATE product_configurator_group SET uuid = CONCAT('SWAG-PRODUCT-CONFIGURATOR-GROUP-UUID-', id) WHERE id IS NOT NULL;
UPDATE product_configurator_option SET uuid = CONCAT('SWAG-PRODUCT-CONFIGURATOR-OPTION-UUID-', id) WHERE id IS NOT NULL;
UPDATE product_configurator_option SET product_configurator_group_uuid = CONCAT('SWAG-PRODUCT-CONFIGURATOR-GROUP-UUID-', group_id) WHERE group_id IS NOT NULL;
UPDATE product_stream SET uuid = CONCAT('SWAG-PRODUCT-STREAM-UUID-', id) WHERE id IS NOT NULL;
UPDATE product_stream SET listing_sorting_uuid = CONCAT('SWAG-LISTING-SORTING-UUID-', listing_sorting_id) WHERE listing_sorting_id IS NOT NULL;
UPDATE product_stream_tab SET product_stream_uuid = CONCAT('SWAG-PRODUCT-STREAM-UUID-', product_stream_id) WHERE product_stream_id IS NOT NULL;

UPDATE product_stream_tab SET uuid = CONCAT('SWAG-PRODUCT-STREAM-TAB-UUID-', id) WHERE id IS NOT NULL;
UPDATE product_stream_attribute SET product_stream_uuid = CONCAT('SWAG-PRODUCT-STREAM-UUID-', product_stream_id) WHERE product_stream_id IS NOT NULL;
UPDATE product_stream_attribute SET uuid = CONCAT('SWAG-PRODUCT-STREAM-ATTRIBUTE-UUID-', id) WHERE id IS NOT NULL;
UPDATE product_stream_assignment SET product_stream_uuid = CONCAT('SWAG-PRODUCT-STREAM-UUID-', product_stream_id) WHERE product_stream_id IS NOT NULL;

UPDATE product_stream_assignment SET uuid = CONCAT('SWAG-PRODUCT-STREAM-ASSIGNMENT-UUID-', id) WHERE id IS NOT NULL;
UPDATE listing_facet SET uuid = CONCAT('SWAG-LISTING-FACET-UUID-', id) WHERE id IS NOT NULL;
UPDATE listing_sorting SET uuid = CONCAT('SWAG-LISTING-SORTING-UUID-', id) WHERE id IS NOT NULL;

UPDATE statistic_product_impression SET shop_uuid = CONCAT('SWAG-SHOP-UUID-', shop_id) WHERE shop_id IS NOT NULL;
UPDATE statistic_product_impression SET uuid = CONCAT('SWAG-STATISTIC-PRODUCT-IMPRESSION-UUID-', id) WHERE id IS NOT NULL;
UPDATE statistic_current_customer SET uuid = CONCAT('SWAG-CUSTOMER-UUID-', customer_id) WHERE customer_id IS NOT NULL;
UPDATE statistic_current_customer SET uuid = CONCAT('SWAG-STATISTIC-CURRENT-CUSTOMER-UUID-', id) WHERE id IS NOT NULL;
UPDATE statistic_address_pool SET uuid = CONCAT('SWAG-STATISTIC-ADDRESS-POOL-UUID-', id) WHERE id IS NOT NULL;
UPDATE statistic_referer SET uuid = CONCAT('SWAG-STATISTIC-REFERER-UUID-', id) WHERE id IS NOT NULL;
UPDATE statistic_search SET shop_uuid = CONCAT('SWAG-SHOP-UUID-', shop_id) WHERE shop_id IS NOT NULL;
UPDATE statistic_search SET uuid = CONCAT('SWAG-STATISTIC-SEARCH-UUID-', id) WHERE id IS NOT NULL;
UPDATE statistic_visitor SET shop_uuid = CONCAT('SWAG-SHOP-UUID-', shop_id) WHERE shop_id IS NOT NULL;
UPDATE statistic_visitor SET uuid = CONCAT('SWAG-STATISTIC-VISITOR-UUID-', id) WHERE id IS NOT NULL;
UPDATE customer SET last_payment_method_uuid = CONCAT('SWAG-PAYMENT-METHOD-UUID-', last_payment_method_id) WHERE last_payment_method_id IS NOT NULL;
UPDATE customer SET default_payment_method_uuid = CONCAT('SWAG-PAYMENT-METHOD-UUID-', default_payment_method_id) WHERE default_payment_method_id IS NOT NULL;
UPDATE customer SET shop_uuid = CONCAT('SWAG-SHOP-UUID-', shop_id) WHERE shop_id IS NOT NULL;
UPDATE customer SET main_shop_uuid = CONCAT('SWAG-SHOP-UUID-', main_shop_id) WHERE main_shop_id IS NOT NULL;
UPDATE customer SET default_billing_address_uuid = CONCAT('SWAG-CUSTOMER-ADDRESS-UUID-', default_billing_address_id) WHERE default_billing_address_id IS NOT NULL;
UPDATE customer SET default_shipping_address_uuid = CONCAT('SWAG-CUSTOMER-ADDRESS-UUID-', default_shipping_address_id) WHERE default_shipping_address_id IS NOT NULL;
UPDATE customer SET customer_group_uuid = CONCAT('SWAG-CUSTOMER-GROUP-UUID-', (SELECT id FROM customer_group WHERE customer_group.group_key = customer_group_key)) WHERE customer_group_key IS NOT NULL;
UPDATE customer SET uuid = CONCAT('SWAG-CUSTOMER-UUID-', id) WHERE id IS NOT NULL;
UPDATE customer_address SET customer_uuid = CONCAT('SWAG-CUSTOMER-UUID-', customer_id) WHERE customer_id IS NOT NULL;
UPDATE customer_address SET area_country_uuid = CONCAT('SWAG-AREA-COUNTRY-UUID-', area_country_id) WHERE area_country_id IS NOT NULL;
UPDATE customer_address SET area_country_state_uuid = CONCAT('SWAG-AREA-COUNTRY-STATE-UUID-', area_country_state_id) WHERE area_country_state_id IS NOT NULL;
UPDATE customer_address SET uuid = CONCAT('SWAG-CUSTOMER-ADDRESS-UUID-', id) WHERE id IS NOT NULL;
UPDATE customer_address_attribute SET uuid = CONCAT('SWAG-CUSTOMER-ADDRESS-ATTRIBUTE-UUID-', id) WHERE id IS NOT NULL;
UPDATE customer_address_attribute SET customer_address_uuid = CONCAT('SWAG-CUSTOMER-ADDRESS-UUID-', address_id) WHERE address_id IS NOT NULL;
UPDATE customer_attribute SET uuid = CONCAT('SWAG-CUSTOMER-ATTRIBUTE-UUID-', id) WHERE id IS NOT NULL;
UPDATE customer_attribute SET customer_uuid = CONCAT('SWAG-CUSTOMER-UUID-', customer_id) WHERE customer_id IS NOT NULL;
UPDATE album SET uuid = CONCAT('SWAG-ALBUM-UUID-', id);
UPDATE album SET parent_uuid = CONCAT('SWAG-ALBUM-UUID-', parent_id);
UPDATE media SET uuid = CONCAT('SWAG-MEDIA-UUID-', id);
UPDATE media_attribute SET uuid = CONCAT('SWAG-MEDIA-ATTRIBUTE-UUID-', id);
UPDATE media_attribute SET media_uuid = CONCAT('SWAG-MEDIA-UUID-', media_id);
UPDATE premium_product SET uuid = CONCAT('SWAG-PREMIUM-PRODUCT-UUID-', id) WHERE id IS NOT NULL;
UPDATE premium_product SET shop_uuid = CONCAT('SWAG-SHOP-UUID-', shop_id) WHERE shop_id IS NOT NULL AND shop_id > 0;
UPDATE attribute_configuration SET uuid = CONCAT('SWAG-ATTRIBUTE-CONFIGURATION-UUID-', id) WHERE id IS NOT NULL;
UPDATE blog SET uuid = CONCAT('SWAG-BLOG-UUID-', id) WHERE id IS NOT NULL;
UPDATE blog SET category_uuid = CONCAT('SWAG-CATEGORY-UUID-', category_id) WHERE category_id IS NOT NULL;
UPDATE blog_attribute SET uuid = CONCAT('SWAG-BLOG-ATTRIBUTE-UUID-', id) WHERE id IS NOT NULL;
UPDATE blog_attribute SET blog_uuid = CONCAT('SWAG-BLOG-UUID-', blog_id) WHERE blog_id IS NOT NULL;
UPDATE blog_comment SET uuid = CONCAT('SWAG-BLOG-COMMENT-UUID-', id) WHERE id IS NOT NULL;
UPDATE blog_comment SET blog_uuid = CONCAT('SWAG-BLOG-UUID-', blog_id) WHERE blog_id IS NOT NULL;
UPDATE blog_media SET uuid = CONCAT('SWAG-BLOG-MEDIA-UUID-', id) WHERE id IS NOT NULL;
UPDATE blog_media SET media_uuid = CONCAT('SWAG-MEDIA-UUID-', media_id) WHERE media_id IS NOT NULL;
UPDATE blog_media SET blog_uuid = CONCAT('SWAG-BLOG-UUID-', blog_id) WHERE blog_id IS NOT NULL;
UPDATE blog_tag SET uuid = CONCAT('SWAG-BLOG-TAG-UUID-', id) WHERE id IS NOT NULL;
UPDATE blog_tag SET blog_uuid = CONCAT('SWAG-BLOG-UUID-', blog_id) WHERE blog_id IS NOT NULL;
UPDATE blog_product SET blog_uuid = CONCAT('SWAG-BLOG-UUID-', blog_id) WHERE blog_id IS NOT NULL;
UPDATE shop_page SET uuid = CONCAT('SWAG-SHOP-PAGE-UUID-', id) WHERE id IS NOT NULL;
UPDATE shop_page SET parent_uuid = CONCAT('SWAG-SHOP-PAGE-UUID-', parent_id) WHERE parent_id IS NOT NULL AND parent_id > 0;
UPDATE shop_page_attribute SET uuid = CONCAT('SWAG-SHOP-PAGE-ATTRIBUTE-UUID-', id) WHERE id IS NOT NULL;
UPDATE shop_page_attribute SET shop_page_uuid = CONCAT('SWAG-SHOP-PAGE-UUID-', shop_page_id) WHERE shop_page_id IS NOT NULL;
UPDATE shop_page_group SET uuid = CONCAT('SWAG-SHOP-PAGE-GROUP-UUID-', id) WHERE id IS NOT NULL;
UPDATE shop_form SET uuid = CONCAT('SWAG-SHOP-FORM-UUID-', id) WHERE id IS NOT NULL;
UPDATE shop_form_attribute SET uuid = CONCAT('SWAG-SHOP-FORM-ATTRIBUTE-UUID-', id) WHERE id IS NOT NULL;
UPDATE shop_form_attribute SET shop_form_uuid = CONCAT('SWAG-SHOP-FORM-UUID-', shop_form_id) WHERE shop_form_id IS NOT NULL;
UPDATE shop_form_field SET uuid = CONCAT('SWAG-SHOP-FORM-FIELD-UUID-', id) WHERE id IS NOT NULL;
UPDATE shop_form_field SET shop_form_uuid = CONCAT('SWAG-SHOP-FORM-UUID-', shop_form_id) WHERE shop_form_id IS NOT NULL;
UPDATE `user` SET uuid = CONCAT('SWAG-USER-UUID-', id) WHERE id IS NOT NULL;
UPDATE `user` SET user_role_uuid = CONCAT('SWAG-USER-ROLE-UUID-', user_role_id) WHERE user_role_id IS NOT NULL;
UPDATE `user` SET locale_uuid = CONCAT('SWAG-LOCALE-UUID-', locale_id) WHERE locale_id IS NOT NULL;
UPDATE user_attribute SET uuid = CONCAT('SWAG-USER-ATTRIBUTE-UUID-', id) WHERE id IS NOT NULL;
UPDATE user_attribute SET user_uuid = CONCAT('SWAG-USER-UUID-', user_id) WHERE user_id IS NOT NULL;
UPDATE config_form_field SET uuid = CONCAT('SWAG-CONFIG-FORM-FIELD-UUID-', id) WHERE id IS NOT NULL;
UPDATE config_form_field SET config_form_uuid = CONCAT('SWAG-CONFIG-FORM-UUID-', config_form_id) WHERE config_form_id IS NOT NULL AND config_form_id > 0;
UPDATE config_form_field_translation SET uuid = CONCAT('SWAG-CFFT-UUID-', id) WHERE id IS NOT NULL;
UPDATE config_form_field_translation SET config_form_field_uuid = CONCAT('SWAG-CONFIG-FORM-FIELD-UUID-', config_form_field_id) WHERE config_form_field_id IS NOT NULL;
UPDATE config_form_field_translation SET locale_uuid = CONCAT('SWAG-LOCALE-UUID-', locale_id) WHERE locale_id IS NOT NULL;
UPDATE config_form SET uuid = CONCAT('SWAG-CONFIG-FORM-UUID-', id) WHERE id IS NOT NULL;
UPDATE config_form SET parent_uuid = CONCAT('SWAG-CONFIG-FORM-UUID-', parent_id) WHERE parent_id IS NOT NULL;
UPDATE config_form SET plugin_uuid = CONCAT('SWAG-PLUGIN-UUID-', plugin_id) WHERE plugin_id IS NOT NULL;
UPDATE config_form_translation SET uuid = CONCAT('SWAG-CONFIG-FORM-TRANSLATION-UUID-', id) WHERE id IS NOT NULL;
UPDATE config_form_translation SET config_form_uuid = CONCAT('SWAG-CONFIG-FORM-UUID-', config_form_id) WHERE config_form_id IS NOT NULL;
UPDATE config_form_translation SET locale_uuid = CONCAT('SWAG-LOCALE-UUID-', locale_id) WHERE locale_id IS NOT NULL;
UPDATE mail SET uuid = CONCAT('SWAG-MAIL-UUID-', id) WHERE id IS NOT NULL;
UPDATE mail SET order_state_uuid = CONCAT('SWAG-ORDER-STATE-UUID-', order_state_id) WHERE order_state_id IS NOT NULL;
UPDATE mail_attachment SET uuid = CONCAT('SWAG-MAIL-ATTACHMENT-UUID-', id) WHERE id IS NOT NULL;
UPDATE mail_attachment SET mail_uuid = CONCAT('SWAG-MAIL-UUID-', mail_id) WHERE mail_id IS NOT NULL;
UPDATE mail_attachment SET media_uuid = CONCAT('SWAG-MEDIA-UUID-', media_id) WHERE media_id IS NOT NULL;
UPDATE mail_attachment SET shop_uuid = CONCAT('SWAG-SHOP-UUID-', shop_id) WHERE shop_id IS NOT NULL;
UPDATE mail_attribute SET uuid = CONCAT('SWAG-MAIL-ATTRIBUTE-UUID-', id) WHERE id IS NOT NULL;
UPDATE mail_attribute SET mail_uuid = CONCAT('SWAG-MAIL-UUID-', mail_id) WHERE mail_id IS NOT NULL;
UPDATE config_form_field_value SET uuid = CONCAT('SWAG-CONFIG-FORM-FIELD-VALUE-UUID-', id) WHERE id IS NOT NULL;
UPDATE config_form_field_value SET shop_uuid = CONCAT('SWAG-SHOP-UUID-', shop_id) WHERE shop_id IS NOT NULL;
UPDATE config_form_field_value SET config_form_field_uuid = CONCAT('SWAG-CONFIG-FORM-FIELD-UUID-', config_form_field_id) WHERE config_form_field_id IS NOT NULL;
UPDATE area SET uuid = CONCAT('SWAG-AREA-UUID-', id) WHERE id IS NOT NULL;
UPDATE area_country_attribute SET uuid = CONCAT('SWAG-AREA-COUNTRY-ATTRIBUTE-UUID-', id) WHERE id IS NOT NULL;
UPDATE area_country_attribute SET area_country_uuid = CONCAT('SWAG-AREA-COUNTRY-UUID-', area_country_id) WHERE area_country_id IS NOT NULL;
UPDATE area_country_state SET uuid = CONCAT('SWAG-AREA-COUNTRY-STATE-UUID-', id) WHERE id IS NOT NULL;
UPDATE area_country_state SET area_country_uuid = CONCAT('SWAG-AREA-COUNTRY-UUID-', area_country_id) WHERE area_country_id IS NOT NULL;
UPDATE area_country_state_attribute SET uuid = CONCAT('SWAG-AREA-COUNTRY-STATE-ATTRIBUTE-UUID-', id) WHERE id IS NOT NULL;
UPDATE area_country_state_attribute SET area_country_state_uuid = CONCAT('SWAG-AREA-COUNTRY-STATE-UUID-', area_country_state_id) WHERE area_country_state_id IS NOT NULL;
UPDATE currency SET uuid = CONCAT('SWAG-CURRENCY-UUID-', id) WHERE id IS NOT NULL;
UPDATE customer_group SET uuid = CONCAT('SWAG-CUSTOMER-GROUP-UUID-', id) WHERE id IS NOT NULL;
UPDATE customer_group_attribute SET uuid = CONCAT('SWAG-CUSTOMER-GROUP-ATTRIBUTE-UUID-', id) WHERE id IS NOT NULL;
UPDATE customer_group_attribute SET customer_group_uuid = CONCAT('SWAG-CUSTOMER-GROUP-UUID-', customer_group_id) WHERE customer_group_id IS NOT NULL;
UPDATE customer_group_discount SET uuid = CONCAT('SWAG-CUSTOMER-GROUP-DISCOUNT-UUID-', id) WHERE id IS NOT NULL;
UPDATE customer_group_discount SET customer_group_uuid = CONCAT('SWAG-CUSTOMER-GROUP-UUID-', customer_group_id) WHERE customer_group_id IS NOT NULL;
UPDATE locale SET uuid = CONCAT('SWAG-LOCALE-UUID-', id) WHERE id IS NOT NULL;
UPDATE product p SET p.uuid = CONCAT('SWAG-PRODUCT-UUID-', p.id);
UPDATE product p SET p.product_manufacturer_uuid = CONCAT('SWAG-PRODUCT-MANUFACTURER-UUID-', p.manufacturer_id) WHERE product_manufacturer_uuid IS NOT NULL;
UPDATE product p SET p.tax_uuid = CONCAT('SWAG-TAX-UUID-', p.tax_id) WHERE tax_id IS NOT NULL;
UPDATE product p SET p.main_detail_uuid = (SELECT sub.order_number FROM product_detail sub WHERE sub.id = p.main_detail_id LIMIT 1);
UPDATE product p SET p.filter_group_uuid = CONCAT('SWAG-FILTER-UUID-', p.filter_group_id) WHERE filter_group_id IS NOT NULL;
UPDATE `product` SET price_group_uuid = CONCAT('SWAG-PRICE-GROUP-UUID-', price_group_id) WHERE price_group_id IS NOT NULL;
UPDATE product_attribute pa SET pa.uuid = CONCAT('SWAG-PRODUCT-ATTRIBUTE-UUID-', pa.id);
UPDATE log SET uuid = CONCAT('SWAG-LOG-UUID-', id) WHERE id IS NOT NULL;
UPDATE payment_method SET uuid = CONCAT('SWAG-PAYMENT-METHOD-UUID-', id) WHERE id IS NOT NULL;
UPDATE payment_method_attribute SET uuid = CONCAT('SWAG-PAYMENT-METHOD-ATTRIBUTE-UUID-', id) WHERE id IS NOT NULL;
UPDATE plugin SET uuid = CONCAT('SWAG-PLUGIN-UUID-', id) WHERE id IS NOT NULL;
UPDATE plugin_category SET uuid = CONCAT('SWAG-PLUGIN-CATEGORY-UUID-', id) WHERE id IS NOT NULL;
UPDATE price_group SET uuid = CONCAT('SWAG-PRICE-GROUP-UUID-', id) WHERE id IS NOT NULL;
UPDATE price_group_discount SET uuid = CONCAT('SWAG-PRICE-GROUP-DISCOUNT-UUID-', id) WHERE id IS NOT NULL;
UPDATE snippet SET uuid = CONCAT('SWAG-SNIPPET-UUID-', id) WHERE id IS NOT NULL;
UPDATE order_state SET uuid = CONCAT('SWAG-ORDER-STATE-UUID-', id) WHERE id IS NOT NULL;
UPDATE tax SET uuid = CONCAT('SWAG-TAX-UUID-', id) WHERE id IS NOT NULL;
UPDATE tax_area_rule SET uuid = CONCAT('SWAG-TAX-AREA-RULE-UUID-', id) WHERE id IS NOT NULL;
UPDATE shop_template SET uuid = CONCAT('SWAG-SHOP-TEMPLATE-UUID-', id) WHERE id IS NOT NULL;
UPDATE shop_template_config_preset SET uuid = CONCAT('SWAG-SHOP-TEMPLATE-CONFIG-PRESET-UUID-', id) WHERE id IS NOT NULL;
UPDATE shop_template_config_form_field SET uuid = CONCAT('SWAG-STCFF-UUID-', id) WHERE id IS NOT NULL;
UPDATE shop_template_config_form SET uuid = CONCAT('SWAG-SHOP-TEMPLATE-CONFIG-FORM-UUID-', id) WHERE id IS NOT NULL;
UPDATE shop_template_config_form_field_value SET uuid = CONCAT('SWAG-STCFF-VALUE-UUID-', id) WHERE id IS NOT NULL;
UPDATE unit SET uuid = CONCAT('SWAG-UNIT-UUID-', id) WHERE id IS NOT NULL;
UPDATE payment_method SET plugin_uuid = CONCAT('SWAG-PLUGIN-UUID-', plugin_id) WHERE plugin_id IS NOT NULL;
UPDATE payment_method_attribute SET payment_method_uuid = CONCAT('SWAG-PAYMENT-METHOD-UUID-', payment_method_id) WHERE payment_method_id IS NOT NULL;
UPDATE payment_method_country SET area_country_uuid = CONCAT('SWAG-AREA-COUNTRY-UUID-', area_country_id) WHERE area_country_id IS NOT NULL;
UPDATE payment_method_country SET payment_method_uuid = CONCAT('SWAG-PAYMENT-METHOD-UUID-', payment_method_id) WHERE payment_method_id IS NOT NULL;
UPDATE payment_method_shop SET payment_method_uuid = CONCAT('SWAG-PAYMENT-METHOD-UUID-', payment_method_id) WHERE payment_method_id IS NOT NULL;
UPDATE payment_method_shop SET shop_uuid = CONCAT('SWAG-SHOP-UUID-', shop_id) WHERE shop_id IS NOT NULL;
UPDATE plugin_category SET parent_uuid = CONCAT('SWAG-PLUGIN-CATEGORY-UUID-', parent_id) WHERE parent_id IS NOT NULL;
UPDATE price_group_discount SET price_group_uuid = CONCAT('SWAG-PRICE-GROUP-UUID-', price_group_id) WHERE price_group_id IS NOT NULL;
UPDATE price_group_discount SET customer_group_uuid = CONCAT('SWAG-CUSTOMER-GROUP-UUID-', customer_group_id) WHERE customer_group_id IS NOT NULL;
UPDATE shop_currency SET shop_uuid = CONCAT('SWAG-SHOP-UUID-', shop_id) WHERE shop_id IS NOT NULL;
UPDATE shop_currency SET currency_uuid = CONCAT('SWAG-CURRENCY-UUID-', currency_id) WHERE currency_id IS NOT NULL;
UPDATE shop_page_group_mapping SET shop_uuid = CONCAT('SWAG-SHOP-UUID-', shop_id) WHERE shop_id IS NOT NULL;
UPDATE shop_page_group_mapping SET shop_page_group_uuid = CONCAT('SWAG-SHOP-PAGE-GROUP-UUID-', shop_page_group_id) WHERE shop_page_group_id IS NOT NULL;
UPDATE snippet SET shop_uuid = CONCAT('SWAG-SHOP-UUID-', shop_id) WHERE shop_id IS NOT NULL;
UPDATE tax_area_rule SET area_country_uuid = CONCAT('SWAG-AREA-COUNTRY-UUID-', area_country_id) WHERE area_country_id IS NOT NULL;
UPDATE tax_area_rule SET area_uuid = CONCAT('SWAG-AREA-UUID-', area_id) WHERE area_id IS NOT NULL;
UPDATE tax_area_rule SET area_country_state_uuid = CONCAT('SWAG-AREA-COUNTRY-STATE-UUID-', area_country_state_id) WHERE area_country_state_id IS NOT NULL;
UPDATE tax_area_rule SET tax_uuid = CONCAT('SWAG-TAX-UUID-', tax_id) WHERE tax_id IS NOT NULL;
UPDATE tax_area_rule SET customer_group_uuid = CONCAT('SWAG-CUSTOMER-GROUP-UUID-', customer_group_id) WHERE customer_group_id IS NOT NULL;
UPDATE shop_template SET plugin_uuid = CONCAT('SWAG-PLUGIN-UUID-', plugin_id) WHERE plugin_id IS NOT NULL;
UPDATE shop_template SET parent_uuid = CONCAT('SWAG-SHOP-TEMPLATE-UUID-', parent_id) WHERE parent_id IS NOT NULL;
UPDATE shop_template_config_preset SET shop_template_uuid = CONCAT('SWAG-SHOP-TEMPLATE-UUID-', shop_template_id) WHERE shop_template_id IS NOT NULL;
UPDATE shop_template_config_form_field SET shop_template_uuid = CONCAT('SWAG-SHOP-TEMPLATE-UUID-', shop_template_id) WHERE shop_template_id IS NOT NULL;
UPDATE shop_template_config_form_field SET shop_template_config_form_uuid = CONCAT('SWAG-SHOP-TEMPLATE-CONFIG-FORM-UUID-', shop_template_config_form_id) WHERE shop_template_config_form_id IS NOT NULL;
UPDATE shop_template_config_form SET parent_uuid = CONCAT('SWAG-SHOP-TEMPLATE-CONFIG-FORM-UUID-', parent_id) WHERE parent_id IS NOT NULL;
UPDATE shop_template_config_form SET shop_template_uuid = CONCAT('SWAG-SHOP-TEMPLATE-UUID-', shop_template_id) WHERE shop_template_id IS NOT NULL;
UPDATE shop_template_config_form_field_value SET shop_uuid = CONCAT('SWAG-SHOP-UUID-', shop_id) WHERE shop_id IS NOT NULL;
UPDATE shop_template_config_form_field_value SET shop_template_config_form_field_uuid = CONCAT('SWAG-STCFF-UUID-', shop_template_config_form_field_id) WHERE shop_template_config_form_field_id IS NOT NULL;
UPDATE customer_group SET uuid = '3294e6f6-372b-415f-ac73-71cbc191548f' WHERE group_key = 'EK';
UPDATE shop SET customer_group_uuid = '3294e6f6-372b-415f-ac73-71cbc191548f' WHERE customer_group_id = 1;
UPDATE product_vote SET uuid = CONCAT('SWAG-PRODUCT-VOTE-UUID-', id) WHERE id IS NOT NULL;
UPDATE product_vote SET shop_uuid = CONCAT('SWAG-SHOP-UUID-', shop_id) WHERE shop_id IS NOT NULL;


-- cleanup again

UPDATE media SET user_uuid = NULL;
UPDATE `customer` SET `customer_group_uuid` = '3294e6f6-372b-415f-ac73-71cbc191548f' WHERE `customer_group_uuid` = 'SWAG-CUSTOMER-GROUP-UUID-1';
UPDATE `customer` SET `default_payment_method_uuid` = 'SWAG-PAYMENT-METHOD-UUID-2' WHERE `default_payment_method_uuid` = 'SWAG-PAYMENT-METHOD-UUID-0';

DELETE FROM config_form_field_translation WHERE config_form_field_uuid = 'SWAG-CONFIG-FORM-FIELD-UUID-0';
DELETE a FROM config_form_field_translation a LEFT JOIN config_form_field b on a.config_form_field_uuid = b.uuid WHERE b.uuid IS NULL;
DELETE a FROM config_form_translation a LEFT JOIN config_form b on a.config_form_uuid = b.uuid WHERE b.uuid IS NULL;


-- set uuid as primary key

ALTER TABLE `album`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `area`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `area_country`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `area_country_attribute`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `area_country_state`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `area_country_state_attribute`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `attribute_configuration`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `blog`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `blog_attribute`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `blog_comment`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `blog_media`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `blog_tag`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `category_attribute`
    DROP FOREIGN KEY category_attribute_ibfk_1
;

ALTER TABLE `category`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `category_attribute`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`, `category_uuid`)
;

ALTER TABLE `config_form`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `config_form_field`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `config_form_field_translation`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `config_form_field_value`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `config_form_field_value`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `config_form_translation`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `currency`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `customer_address`
    DROP FOREIGN KEY customer_address_ibfk_3
;

ALTER TABLE `customer_attribute`
    DROP FOREIGN KEY customer_attribute_ibfk_1
;

ALTER TABLE `customer`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `customer_address_attribute`
    DROP FOREIGN KEY customer_address_attribute_ibfk_1
;

ALTER TABLE `customer_address`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `customer_address_attribute`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `customer_attribute`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `customer_group`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `customer_group_attribute`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `customer_group_discount`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`, `customer_group_uuid`)
;

ALTER TABLE `filter_attribute`
    DROP FOREIGN KEY filter_attribute_ibfk_1
;

ALTER TABLE filter_option_attribute
    DROP FOREIGN KEY `filter_option_attribute_ibfk_1`
;

ALTER TABLE filter_value_attribute
    DROP FOREIGN KEY `filter_value_attribute_ibfk_1`
;

ALTER TABLE `filter`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `filter_attribute`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `filter_option`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `filter_option_attribute`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`, `filter_option_uuid`)
;

ALTER TABLE `filter_relation`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `filter_value`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `holiday`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `listing_facet`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`, `unique_key`)
;

ALTER TABLE `listing_sorting`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `locale`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`, `code`)
;

ALTER TABLE `log`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `mail_attribute`
    DROP FOREIGN KEY mail_attribute_ibfk_1
;

ALTER TABLE `mail`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`, `name`)
;

ALTER TABLE `mail_attachment`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `mail_attribute`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `media_attribute`
    DROP FOREIGN KEY media_attribute_ibfk_1
;

ALTER TABLE `media`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `media_attribute`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `order_state`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE payment_method_attribute
    DROP FOREIGN KEY `payment_method_attribute_ibfk_1`
;

ALTER TABLE `payment_method`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`, `name`)
;

ALTER TABLE `payment_method_attribute`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `payment_method_country`
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (`payment_method_uuid`, `area_country_uuid`)
;

ALTER TABLE `payment_method_shop`
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (`payment_method_uuid`, `shop_uuid`)
;

ALTER TABLE `plugin`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`, `name`)
;

ALTER TABLE `plugin_category`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`, `locale`)
;

ALTER TABLE `premium_product`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `price_group`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `price_group_discount`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE product_stream_tab
    DROP FOREIGN KEY `s_product_streams_articles_fk_article_id`,
    DROP FOREIGN KEY `s_product_streams_articles_fk_stream_id`
;
ALTER TABLE product_stream_assignment
    DROP FOREIGN KEY `s_product_streams_selection_fk_article_id`,
    DROP FOREIGN KEY `s_product_streams_selection_fk_stream_id`
;

ALTER TABLE `product`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `product_accessory`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `product_also_bought_ro`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `product_attachment_attribute`
    DROP FOREIGN KEY product_attachment_attribute_ibfk_1
;

ALTER TABLE `product_attachment`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `product_attachment_attribute`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `product_attribute`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `product_avoid_customer_group`
    ADD PRIMARY KEY (`product_uuid`, `customer_group_uuid`)
;

ALTER TABLE `product_category_seo`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`shop_uuid`, `product_uuid`, `category_uuid`)
;

-- TODO: product_configurator tables

ALTER TABLE `product_price_attribute`
    DROP FOREIGN KEY product_price_attribute_ibfk_1
;

ALTER TABLE `product_price`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `product_price_attribute`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `product_esd_attribute`
    DROP FOREIGN KEY `product_esd_attribute_ibfk_1`
;

ALTER TABLE `product_esd`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `product_esd_attribute`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `product_esd_serial`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `product_link_attribute`
    DROP FOREIGN KEY `product_link_attribute_ibfk_1`
;

ALTER TABLE `product_link`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `product_link_attribute`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `product_manufacturer_attribute`
    DROP FOREIGN KEY product_manufacturer_attribute_ibfk_1
;

ALTER TABLE `product_manufacturer`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `product_manufacturer_attribute`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `product_media_attribute`
    DROP FOREIGN KEY product_media_attribute_ibfk_1
;

ALTER TABLE `product_media`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `product_media_attribute`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `product_media_mapping`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `product_media_mapping_rule`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `product_notification`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `product_similar`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `product_similar_shown_ro`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `product_stream_attribute`
    DROP FOREIGN KEY product_stream_attribute_ibfk_1
;

ALTER TABLE category
    DROP FOREIGN KEY `s_categories_fk_stream_id`
;

ALTER TABLE `product_stream`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `product_stream_assignment`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `product_stream_attribute`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `product_stream_tab`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `product_top_seller_ro`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `product_vote`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `seo_url`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `shipping_method_attribute`
    DROP FOREIGN KEY shipping_method_attribute_ibfk_1
;

ALTER TABLE `shipping_method`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `shipping_method_attribute`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`, `shipping_method_uuid`)
;

ALTER TABLE `shipping_method_category`
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (`shipping_method_uuid`, `category_uuid`)
;

ALTER TABLE `shipping_method_country`
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (`shipping_method_uuid`, `area_country_uuid`)
;

ALTER TABLE `shipping_method_holiday`
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (`shipping_method_uuid`, `holiday_uuid`)
;

ALTER TABLE `shipping_method_payment_method`
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (`shipping_method_uuid`, `payment_method_uuid`)
;

ALTER TABLE `shipping_method_price`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `shop`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `shop_currency`
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (`shop_uuid`, `currency_uuid`)
;

ALTER TABLE `shop_form_attribute`
    DROP FOREIGN KEY shop_form_attribute_ibfk_1
;

ALTER TABLE `shop_form`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `shop_form_attribute`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `shop_form_field`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`),
    ADD UNIQUE `name_shop_form` (`name`, `shop_form_uuid`),
    DROP INDEX `name`
;

ALTER TABLE `shop_page_attribute`
    DROP FOREIGN KEY shop_page_attribute_ibfk_1
;

ALTER TABLE `shop_page`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `shop_page_attribute`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`, `shop_page_uuid`)
;

ALTER TABLE `shop_page_group`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `shop_page_group_mapping`
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (`shop_uuid`, `shop_page_group_uuid`)
;

ALTER TABLE `shop_template`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `shop_template_config_form`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `shop_template_config_form_field`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `shop_template_config_form_field_value`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `shop_template_config_preset`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `shopping_world_component`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `shopping_world_component_field`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `snippet`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `statistic_address_pool`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `statistic_current_customer`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `statistic_product_impression`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `statistic_referer`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `statistic_search`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `statistic_visitor`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `tax`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `tax_area_rule`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `unit`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `user_attribute`
    DROP FOREIGN KEY user_attribute_ibfk_1
;

ALTER TABLE `user`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE `user_attribute`
    DROP PRIMARY KEY,
    CHANGE `id` `id` int(11),
    ADD PRIMARY KEY (`uuid`)
;






-- add indexes


CREATE INDEX product_by_category_sort_name ON product (name, id);
CREATE INDEX product_by_category_sort_release ON product (created_at, id);

ALTER TABLE `plugin_category`
    ADD INDEX `parent_uuid` (`parent_uuid`);

ALTER TABLE `shop_template`
    ADD INDEX `parent_uuid` (`parent_uuid`);

ALTER TABLE `shop_template_config_form`
    ADD INDEX `parent_uuid` (`parent_uuid`);

ALTER TABLE `product_media`
    ADD INDEX `product_uuid` (`product_uuid`);

ALTER TABLE `blog_product`
    ADD INDEX `blog_uuid` (`blog_uuid`),
    ADD INDEX `product_uuid` (`product_uuid`);

ALTER TABLE `customer`
    ADD INDEX `customer_group_uuid` (`customer_group_uuid`);

ALTER TABLE `customer_address`
    ADD INDEX `customer_uuid` (`customer_uuid`),
    ADD INDEX `area_country_state_uuid` (`area_country_state_uuid`),
    ADD INDEX `area_country_uuid` (`area_country_uuid`);

ALTER TABLE `customer_address_attribute`
    ADD UNIQUE `customer_address_uuid` (`customer_address_uuid`);

ALTER TABLE `product_category_seo`
    ADD INDEX `shop_uuid_product_uuid` (`shop_uuid`, `product_uuid`),
    ADD INDEX `category_uuid` (`category_uuid`);

ALTER TABLE `product_price`
    ADD INDEX `product_uuid` (`product_uuid`)
;

ALTER TABLE `premium_product`
    ADD INDEX `shop_uuid` (`shop_uuid`);

ALTER TABLE `shop_page`
    ADD INDEX `parent_uuid` (`parent_uuid`);

ALTER TABLE `config_form_field`
    ADD INDEX `config_form_uuid` (`config_form_uuid`);

ALTER TABLE `config_form_field_translation`
    ADD INDEX `config_form_field_uuid` (`config_form_field_uuid`);

ALTER TABLE `config_form_translation`
    ADD INDEX `config_form_uuid` (`config_form_uuid`);

ALTER TABLE `shop`
    ADD INDEX `parent_uuid` (`parent_uuid`),
    ADD UNIQUE `uuid` (`uuid`);

ALTER TABLE `mail_attachment`
    ADD UNIQUE `mail_uuid` (`mail_uuid`),
    ADD INDEX `media_uuid` (`media_uuid`),
    ADD INDEX `shop_uuid` (`shop_uuid`),
    ADD UNIQUE `mail_uuid_media_uuid_shop_uuid` (`mail_uuid`, `media_uuid`, `shop_uuid`);

ALTER TABLE `category`
    ADD INDEX `media_uuid` (`media_uuid`);

ALTER TABLE `category_avoid_customer_group`
    ADD INDEX `customer_group_uuid` (`customer_group_uuid`);

ALTER TABLE `media`
    ADD INDEX `album_uuid` (`album_uuid`),
    ADD INDEX `user_uuid` (`user_uuid`);

ALTER TABLE `album`
    ADD INDEX `parent_uuid` (`parent_uuid`);

ALTER TABLE `product`
    ADD INDEX `product_manufacturer_uuid` (`product_manufacturer_uuid`),
    ADD INDEX `tax_uuid` (`tax_uuid`),
    ADD INDEX `main_detail_uuid` (`main_detail_uuid`),
    ADD INDEX `filter_group_uuid` (`filter_group_uuid`);

ALTER TABLE `filter_relation`
    ADD INDEX `filter_group_uuid` (`filter_group_uuid`),
    ADD INDEX `filter_option_uuid` (`filter_option_uuid`);

ALTER TABLE `filter_value`
    ADD INDEX `option_uuid` (`option_uuid`),
    ADD INDEX `media_uuid` (`media_uuid`);

ALTER TABLE `product_vote`
    ADD INDEX `product_uuid` (`product_uuid`),
    ADD INDEX `shop_uuid` (`shop_uuid`);

















-- add foreign keys
ALTER TABLE media_attribute
    ADD CONSTRAINT `fk_media_attribute.media_uuid`
FOREIGN KEY (media_uuid) REFERENCES media (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE media
    ADD CONSTRAINT `fk_media.album_uuid`
FOREIGN KEY (album_uuid) REFERENCES album (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE media
    ADD CONSTRAINT `fk_media.user_uuid`
FOREIGN KEY (user_uuid) REFERENCES user (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE album
    ADD CONSTRAINT `fk_album.parent_uuid`
FOREIGN KEY (parent_uuid) REFERENCES album (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE shop
    ADD CONSTRAINT `fk_shop.parent_uuid`
FOREIGN KEY (parent_uuid) REFERENCES shop (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE shop
    ADD CONSTRAINT `fk_shop.shop_template_uuid`
FOREIGN KEY (shop_template_uuid) REFERENCES shop_template (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE shop
    ADD CONSTRAINT `fk_shop.document_template_uuid`
FOREIGN KEY (document_template_uuid) REFERENCES shop_template (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE shop
    ADD CONSTRAINT `fk_shop.category_uuid`
FOREIGN KEY (category_uuid) REFERENCES category (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE shop
    ADD CONSTRAINT `fk_shop.locale_uuid`
FOREIGN KEY (locale_uuid) REFERENCES locale (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE shop
    ADD CONSTRAINT `fk_shop.currency_uuid`
FOREIGN KEY (currency_uuid) REFERENCES currency (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE shop
    ADD CONSTRAINT `fk_shop.customer_group_uuid`
FOREIGN KEY (customer_group_uuid) REFERENCES customer_group (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE shop
    ADD CONSTRAINT `fk_shop.fallback_locale_uuid`
FOREIGN KEY (fallback_locale_uuid) REFERENCES locale (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE shop
    ADD CONSTRAINT `fk_shop.payment_method_uuid`
FOREIGN KEY (payment_method_uuid) REFERENCES payment_method (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE shop
    ADD CONSTRAINT `fk_shop.shipping_method_uuid`
FOREIGN KEY (shipping_method_uuid) REFERENCES shipping_method (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE shop
    ADD CONSTRAINT `fk_shop.area_country_uuid`
FOREIGN KEY (area_country_uuid) REFERENCES area_country (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE area_country
    ADD CONSTRAINT `fk_area_country.area_uuid`
FOREIGN KEY (area_uuid) REFERENCES area (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE shopping_world_component
    ADD CONSTRAINT `fk_shopping_world_component.plugin_uuid`
FOREIGN KEY (plugin_uuid) REFERENCES plugin (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE shopping_world_component_field
    ADD CONSTRAINT `fk_shopping_world_component_field.shopping_world_component_uuid`
FOREIGN KEY (shopping_world_component_uuid) REFERENCES shopping_world_component (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE shipping_method
    ADD CONSTRAINT `fk_shipping_method.shop_uuid`
FOREIGN KEY (shop_uuid) REFERENCES shop (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE shipping_method
    ADD CONSTRAINT `fk_shipping_method.customer_group_uuid`
FOREIGN KEY (customer_group_uuid) REFERENCES customer_group (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE shipping_method_attribute
    ADD CONSTRAINT `fk_shipping_method_attribute.shipping_method_uuid`
FOREIGN KEY (shipping_method_uuid) REFERENCES shipping_method (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE shipping_method_category
    ADD CONSTRAINT `fk_shipping_method_category.shipping_method_uuid`
FOREIGN KEY (shipping_method_uuid) REFERENCES shipping_method (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE shipping_method_category
    ADD CONSTRAINT `fk_shipping_method_category.category_uuid`
FOREIGN KEY (category_uuid) REFERENCES category (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE shipping_method_country
    ADD CONSTRAINT `fk_shipping_method_country.area_country_uuid`
FOREIGN KEY (area_country_uuid) REFERENCES area_country (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE shipping_method_country
    ADD CONSTRAINT `fk_shipping_method_area_country.shipping_method_uuid`
FOREIGN KEY (shipping_method_uuid) REFERENCES shipping_method (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE shipping_method_holiday
    ADD CONSTRAINT `fk_shipping_method_holiday.holiday_uuid`
FOREIGN KEY (holiday_uuid) REFERENCES holiday (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE shipping_method_holiday
    ADD CONSTRAINT `fk_shipping_method_holiday.shipping_method_uuid`
FOREIGN KEY (shipping_method_uuid) REFERENCES shipping_method (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE shipping_method_payment_method
    ADD CONSTRAINT `fk_shipping_method_payment_method.payment_method_uuid`
FOREIGN KEY (payment_method_uuid) REFERENCES payment_method (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE shipping_method_payment_method
    ADD CONSTRAINT `fk_shipping_method_payment_method.shipping_method_uuid`
FOREIGN KEY (shipping_method_uuid) REFERENCES shipping_method (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE shipping_method_price
    ADD CONSTRAINT `fk_shipping_method_price.shipping_method_uuid`
FOREIGN KEY (shipping_method_uuid) REFERENCES shipping_method (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE product_stream
    ADD CONSTRAINT `fk_product_stream.listing_sorting_uuid`
FOREIGN KEY (listing_sorting_uuid) REFERENCES listing_sorting (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE product_stream_tab
    ADD CONSTRAINT `fk_product_stream_tab.product_stream_uuid`
FOREIGN KEY (product_stream_uuid) REFERENCES product_stream (uuid) ON DELETE CASCADE ON UPDATE CASCADE;


ALTER TABLE product_stream_attribute
    ADD CONSTRAINT `fk_product_stream_attribute.product_stream_uuid`
FOREIGN KEY (product_stream_uuid) REFERENCES product_stream (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE product_stream_assignment
    ADD CONSTRAINT `fk_product_stream_assignment.product_stream_uuid`
FOREIGN KEY (product_stream_uuid) REFERENCES product_stream (uuid) ON DELETE CASCADE ON UPDATE CASCADE;


ALTER TABLE statistic_product_impression
    ADD CONSTRAINT `fk_statistic_product_impression.shop_uuid`
FOREIGN KEY (shop_uuid) REFERENCES shop (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE statistic_current_customer
    ADD CONSTRAINT `fk_statistic_current_customer.customer_uuid`
FOREIGN KEY (customer_uuid) REFERENCES customer (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE statistic_search
    ADD CONSTRAINT `fk_statistic_search.shop_uuid`
FOREIGN KEY (shop_uuid) REFERENCES shop (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE statistic_visitor
    ADD CONSTRAINT `fk_statistic_visitor.shop_uuid`
FOREIGN KEY (shop_uuid) REFERENCES shop (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE customer
    ADD CONSTRAINT `fk_customer.last_payment_method_uuid`
FOREIGN KEY (last_payment_method_uuid) REFERENCES payment_method (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE customer
    ADD CONSTRAINT `fk_customer.customer_group_uuid`
FOREIGN KEY (customer_group_uuid) REFERENCES customer_group (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE customer
    ADD CONSTRAINT `fk_customer.default_payment_method_uuid`
FOREIGN KEY (default_payment_method_uuid) REFERENCES payment_method (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE customer
    ADD CONSTRAINT `fk_customer.shop_uuid`
FOREIGN KEY (shop_uuid) REFERENCES shop (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE customer
    ADD CONSTRAINT `fk_customer.main_shop_uuid`
FOREIGN KEY (main_shop_uuid) REFERENCES shop (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE customer
    ADD CONSTRAINT `fk_customer.default_billing_address_uuid`
FOREIGN KEY (default_billing_address_uuid) REFERENCES customer_address (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE customer
    ADD CONSTRAINT `fk_customer.default_shipping_address_uuid`
FOREIGN KEY (default_shipping_address_uuid) REFERENCES customer_address (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE customer_address
    ADD CONSTRAINT `fk_customer_address.customer_uuid`
FOREIGN KEY (customer_uuid) REFERENCES customer (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE customer_address
    ADD CONSTRAINT `fk_customer_address.area_country_uuid`
FOREIGN KEY (area_country_uuid) REFERENCES area_country (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE customer_address_attribute
    ADD CONSTRAINT `fk_customer_address_attribute.customer_address_uuid`
FOREIGN KEY (customer_address_uuid) REFERENCES customer_address (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE customer_attribute
    ADD CONSTRAINT `fk_customer_attribute.customer_uuid`
FOREIGN KEY (customer_uuid) REFERENCES customer (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE product_avoid_customer_group ADD CONSTRAINT `fk_product_avoid_customer_group.customer_group_uuid`
FOREIGN KEY (customer_group_uuid) REFERENCES customer_group (uuid) ON DELETE CASCADE ON UPDATE CASCADE;


ALTER TABLE product_category
    ADD CONSTRAINT `fk_product_category.category_uuid`
FOREIGN KEY (category_uuid) REFERENCES category (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE product_category_seo
    ADD CONSTRAINT `fk_product_category_seo.shop_uuid`
FOREIGN KEY (shop_uuid) REFERENCES shop (uuid) ON DELETE CASCADE ON UPDATE CASCADE,

    ADD CONSTRAINT `fk_product_category_seo.category_uuid`
FOREIGN KEY (category_uuid) REFERENCES category (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE product_attachment_attribute
    ADD CONSTRAINT `fk_product_attachment_attribute.product_uuid`
FOREIGN KEY (product_attachment_uuid) REFERENCES product_attachment (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE product_esd_attribute
    ADD CONSTRAINT `fk_product_esd_attribute.product_uuid`
FOREIGN KEY (product_esd_uuid) REFERENCES product_esd (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE product_esd_serial
    ADD CONSTRAINT `fk_product_esd_serial.product_uuid`
FOREIGN KEY (product_esd_uuid) REFERENCES product_esd (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE product_media_attribute
    ADD CONSTRAINT `fk_product_media_attribute.product_uuid`
FOREIGN KEY (product_media_uuid) REFERENCES product_media (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE product_media_mapping
    ADD CONSTRAINT `fk_product_media_mapping.product_uuid`
FOREIGN KEY (product_media_uuid) REFERENCES product_media (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE product_link_attribute
    ADD CONSTRAINT `fk_product_link_attribute.product_uuid`
FOREIGN KEY (product_link_uuid) REFERENCES product_link (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE product_manufacturer_attribute
    ADD CONSTRAINT `fk_product_manufacturer_attribute.product_uuid`
FOREIGN KEY (product_manufacturer_uuid) REFERENCES product_manufacturer (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE product_price_attribute
    ADD CONSTRAINT `fk_product_price_attribute.product_uuid`
FOREIGN KEY (product_price_uuid) REFERENCES product_price (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE filter_attribute
    ADD CONSTRAINT `fk_filter_attribute.filter_uuid`
FOREIGN KEY (filter_uuid) REFERENCES filter (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE filter_value_attribute
    ADD CONSTRAINT `fk_filter_value_attribute.filter_value_uuid`
FOREIGN KEY (filter_value_uuid) REFERENCES filter_value (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE filter_option_attribute
    ADD CONSTRAINT `fk_filter_option_attribute.filter_value_uuid`
FOREIGN KEY (filter_option_uuid) REFERENCES filter_option (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE premium_product
    ADD CONSTRAINT `fk_premium_product.shop_uuid`
FOREIGN KEY (shop_uuid) REFERENCES shop (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE blog
    ADD CONSTRAINT `fk_blog.category_uuid`
FOREIGN KEY (category_uuid) REFERENCES category (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE blog_attribute
    ADD CONSTRAINT `fk_blog_attribute.blog_uuid`
FOREIGN KEY (blog_uuid) REFERENCES blog (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE blog_comment
    ADD CONSTRAINT `fk_blog_comment.blog_uuid`
FOREIGN KEY (blog_uuid) REFERENCES blog (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE blog_media
    ADD CONSTRAINT `fk_blog_media.media_uuid`
FOREIGN KEY (media_uuid) REFERENCES media (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE blog_media
    ADD CONSTRAINT `fk_blog_media.blog_uuid`
FOREIGN KEY (blog_uuid) REFERENCES blog (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE blog_tag
    ADD CONSTRAINT `fk_blog_tag.blog_uuid`
FOREIGN KEY (blog_uuid) REFERENCES blog (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE shop_page
    ADD CONSTRAINT `fk_shop_page.parent_uuid`
FOREIGN KEY (parent_uuid) REFERENCES shop_page (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE shop_page_attribute
    ADD CONSTRAINT `fk_shop_page_attribute.shop_page_uuid`
FOREIGN KEY (shop_page_uuid) REFERENCES shop_page (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE shop_form_attribute
    ADD CONSTRAINT `fk_shop_form_attribute.shop_form_uuid`
FOREIGN KEY (shop_form_uuid) REFERENCES shop_form (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE shop_form_field
    ADD CONSTRAINT `fk_shop_form_field.shop_form_uuid`
FOREIGN KEY (shop_form_uuid) REFERENCES shop_form (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `user`
    ADD CONSTRAINT `fk_user.locale_uuid`
FOREIGN KEY (locale_uuid) REFERENCES locale (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE user_attribute
    ADD CONSTRAINT `fk_user_attribute.user_uuid`
FOREIGN KEY (user_uuid) REFERENCES `user` (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE config_form_field
    ADD CONSTRAINT `fk_config_form_field.config_form_uuid`
FOREIGN KEY (config_form_uuid) REFERENCES config_form (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE config_form_field_translation
    ADD CONSTRAINT `fk_config_form_field_translation.config_form_field_uuid`
FOREIGN KEY (config_form_field_uuid) REFERENCES config_form_field (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE config_form_field_translation
    ADD CONSTRAINT `fk_config_form_field_translation.locale_uuid`
FOREIGN KEY (locale_uuid) REFERENCES locale (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE config_form
    ADD CONSTRAINT `fk_config_form.parent_uuid`
FOREIGN KEY (parent_uuid) REFERENCES config_form (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE config_form
    ADD CONSTRAINT `fk_config_form.plugin_uuid`
FOREIGN KEY (plugin_uuid) REFERENCES plugin (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE config_form_translation
    ADD CONSTRAINT `fk_config_form_translation.config_form_uuid`
FOREIGN KEY (config_form_uuid) REFERENCES config_form (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE config_form_translation
    ADD CONSTRAINT `fk_config_form_translation.locale_uuid`
FOREIGN KEY (locale_uuid) REFERENCES locale (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE mail
    ADD CONSTRAINT `fk_mail.order_state_uuid`
FOREIGN KEY (order_state_uuid) REFERENCES order_state (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE mail_attachment
    ADD CONSTRAINT `fk_mail_attachment.mail_uuid`
FOREIGN KEY (mail_uuid) REFERENCES mail (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE mail_attachment
    ADD CONSTRAINT `fk_mail_attachment.media_uuid`
FOREIGN KEY (media_uuid) REFERENCES media (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE mail_attachment
    ADD CONSTRAINT `fk_mail_attachment.shop_uuid`
FOREIGN KEY (shop_uuid) REFERENCES shop (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE mail_attribute
    ADD CONSTRAINT `fk_mail_attribute.mail_uuid`
FOREIGN KEY (mail_uuid) REFERENCES mail (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE config_form_field_value
    ADD CONSTRAINT `fk_config_form_field_value.shop_uuid`
FOREIGN KEY (shop_uuid) REFERENCES shop (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE area_country_attribute
    ADD CONSTRAINT `fk_area_country_attribute.area_country_uuid`
FOREIGN KEY (area_country_uuid) REFERENCES area_country (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE area_country_state
    ADD CONSTRAINT `fk_area_country_state.area_country_uuid`
FOREIGN KEY (area_country_uuid) REFERENCES area_country (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE area_country_state_attribute
    ADD CONSTRAINT `fk_area_country_state_attribute.area_country_state_uuid`
FOREIGN KEY (area_country_state_uuid) REFERENCES area_country_state (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE customer_group_attribute
    ADD CONSTRAINT `fk_customer_group_attribute.customer_group_uuid`
FOREIGN KEY (customer_group_uuid) REFERENCES customer_group (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE customer_group_discount
    ADD CONSTRAINT `fk_customer_group_discount.customer_group_uuid`
FOREIGN KEY (customer_group_uuid) REFERENCES customer_group (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE payment_method ADD CONSTRAINT `fk_payment_method.plugin_uuid` FOREIGN KEY (plugin_uuid) REFERENCES plugin (uuid) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE payment_method_attribute ADD CONSTRAINT `fk_payment_method_attribute.payment_method_uuid` FOREIGN KEY (payment_method_uuid) REFERENCES payment_method (uuid) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE payment_method_country ADD CONSTRAINT `fk_payment_method_country.area_country_uuid` FOREIGN KEY (area_country_uuid) REFERENCES area_country (uuid) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE payment_method_country ADD CONSTRAINT `fk_payment_method_country.payment_method_uuid` FOREIGN KEY (payment_method_uuid) REFERENCES payment_method (uuid) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE payment_method_shop ADD CONSTRAINT `fk_payment_method_shop.payment_method_uuid` FOREIGN KEY (payment_method_uuid) REFERENCES payment_method (uuid) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE payment_method_shop ADD CONSTRAINT `fk_payment_method_shop.shop_uuid` FOREIGN KEY (shop_uuid) REFERENCES shop (uuid) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE plugin_category ADD CONSTRAINT `fk_plugin_category.parent_uuid` FOREIGN KEY (parent_uuid) REFERENCES plugin_category (uuid) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE price_group_discount ADD CONSTRAINT `fk_price_group_discount.price_group_uuid` FOREIGN KEY (price_group_uuid) REFERENCES price_group (uuid) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE price_group_discount ADD CONSTRAINT `fk_price_group_discount.customer_group_uuid` FOREIGN KEY (customer_group_uuid) REFERENCES customer_group (uuid) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE shop_currency ADD CONSTRAINT `fk_shop_currency.shop_uuid` FOREIGN KEY (shop_uuid) REFERENCES shop (uuid) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE shop_currency ADD CONSTRAINT `fk_shop_currency.currency_uuid` FOREIGN KEY (currency_uuid) REFERENCES currency (uuid) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE shop_page_group_mapping ADD CONSTRAINT `fk_shop_page_group_mapping.shop_uuid` FOREIGN KEY (shop_uuid) REFERENCES shop (uuid) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE shop_page_group_mapping ADD CONSTRAINT `fk_shop_page_group_mapping.shop_page_group_uuid` FOREIGN KEY (shop_page_group_uuid) REFERENCES shop_page_group (uuid) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE snippet ADD CONSTRAINT `fk_snippet.shop_uuid` FOREIGN KEY (shop_uuid) REFERENCES shop (uuid) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE tax_area_rule ADD CONSTRAINT `fk_tax_area_rule.area_country_uuid` FOREIGN KEY (area_country_uuid) REFERENCES area_country (uuid) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE tax_area_rule ADD CONSTRAINT `fk_tax_area_rule.area_uuid` FOREIGN KEY (area_uuid) REFERENCES area (uuid) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE tax_area_rule ADD CONSTRAINT `fk_tax_area_rule.area_country_state_uuid` FOREIGN KEY (area_country_state_uuid) REFERENCES area_country_state (uuid) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE tax_area_rule ADD CONSTRAINT `fk_tax_area_rule.tax_uuid` FOREIGN KEY (tax_uuid) REFERENCES tax (uuid) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE tax_area_rule ADD CONSTRAINT `fk_tax_area_rule.customer_group_uuid` FOREIGN KEY (customer_group_uuid) REFERENCES customer_group (uuid) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE shop_template ADD CONSTRAINT `fk_shop_template.plugin_uuid` FOREIGN KEY (plugin_uuid) REFERENCES plugin (uuid) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE shop_template ADD CONSTRAINT `fk_shop_template.parent_uuid` FOREIGN KEY (parent_uuid) REFERENCES shop_template (uuid) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE shop_template_config_preset ADD CONSTRAINT `fk_shop_template_config_preset.shop_template_uuid` FOREIGN KEY (shop_template_uuid) REFERENCES shop_template (uuid) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE shop_template_config_form_field ADD CONSTRAINT `fk_shop_template_config_form_field.shop_template_uuid` FOREIGN KEY (shop_template_uuid) REFERENCES shop_template (uuid) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE shop_template_config_form_field ADD CONSTRAINT `fk_shop_template_cff.shop_template_config_form_uuid` FOREIGN KEY (shop_template_config_form_uuid) REFERENCES shop_template_config_form (uuid) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE shop_template_config_form ADD CONSTRAINT `fk_shop_template_config_form.parent_uuid` FOREIGN KEY (parent_uuid) REFERENCES shop_template_config_form (uuid) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE shop_template_config_form ADD CONSTRAINT `fk_shop_template_config_form.shop_template_uuid` FOREIGN KEY (shop_template_uuid) REFERENCES shop_template (uuid) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE shop_template_config_form_field_value ADD CONSTRAINT `fk_shop_template_cffv.shop_uuid` FOREIGN KEY (shop_uuid) REFERENCES shop (uuid) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE shop_template_config_form_field_value ADD CONSTRAINT `fk_shop_template_cffv.shop_template_config_form_field_uuid` FOREIGN KEY (shop_template_config_form_field_uuid) REFERENCES shop_template_config_form_field (uuid) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `blog` ADD FOREIGN KEY (`user_uuid`) REFERENCES `user` (`uuid`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `blog_product`
    ADD FOREIGN KEY (`blog_uuid`) REFERENCES `blog` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `category`
    ADD FOREIGN KEY (`media_uuid`) REFERENCES `media` (`uuid`) ON DELETE NO ACTION ON UPDATE NO ACTION,
    ADD FOREIGN KEY (`parent_uuid`) REFERENCES `category` (`uuid`) ON DELETE CASCADE ON UPDATE NO ACTION;

ALTER TABLE `category_avoid_customer_group`
    ADD FOREIGN KEY (`category_uuid`) REFERENCES `category` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
    ADD FOREIGN KEY (`customer_group_uuid`) REFERENCES `customer_group` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `category_attribute`
    ADD FOREIGN KEY (`category_uuid`) REFERENCES `category` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `customer_address`
    ADD FOREIGN KEY (`area_country_state_uuid`) REFERENCES `area_country_state` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `filter_product`
    ADD FOREIGN KEY (`filter_value_uuid`) REFERENCES `filter_value` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `filter_relation`
    ADD FOREIGN KEY (`filter_group_uuid`) REFERENCES `filter` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
    ADD FOREIGN KEY (`filter_option_uuid`) REFERENCES `filter_option` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `filter_value`
    ADD FOREIGN KEY (`media_uuid`) REFERENCES `media` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
    ADD FOREIGN KEY (`option_uuid`) REFERENCES `filter_option` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `product_vote`
    ADD FOREIGN KEY (`shop_uuid`) REFERENCES `shop` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;


UPDATE `payment_method` SET absolute_surcharge = NULL WHERE absolute_surcharge = 0;
UPDATE `payment_method` SET percentage_surcharge = NULL WHERE percentage_surcharge = 0;


CREATE TABLE `product_new` (
    `original_id` int(11),
    `original_detail_id` int(11),

    #identification
    `uuid` varchar(42) COLLATE utf8mb4_unicode_ci NOT NULL,
    `container_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NULL,
    `is_main` tinyint(1) unsigned NOT NULL DEFAULT '1',
    `active` tinyint(1) unsigned NOT NULL DEFAULT '1',

    #foreign key columns
    `tax_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    `product_manufacturer_uuid` varchar(42) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    `price_group_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `filter_group_uuid` varchar(42) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
    `pack_unit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `additional_text` LONGTEXT COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
    `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `description` mediumtext COLLATE utf8mb4_unicode_ci,
    `description_long` mediumtext COLLATE utf8mb4_unicode_ci,
    `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `keywords` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO product_new
(
    uuid,
    container_uuid,
    tax_uuid,
    is_main,
    product_manufacturer_uuid,
    pseudo_sales,
    mark_as_topseller,
    price_group_uuid,
    filter_group_uuid,
    is_closeout,
    allow_notification,
    template,
    created_at,
    updated_at,
    supplier_number,
    sales,
    active,
    stock,
    min_stock,
    weight,
    position,
    width,
    height,
    length,
    ean,
    unit_uuid,
    purchase_steps,
    max_purchase,
    min_purchase,
    purchase_unit,
    reference_unit,
    release_date,
    shipping_free,
    purchase_price,
    additional_text,
    pack_unit,
    name,
    description,
    description_long,
    meta_title,
    keywords,
    original_id,
    original_detail_id
)
SELECT
    d.uuid as uuid,
    p.uuid as container_uuid,
    p.tax_uuid,
    d.is_main,
    p.product_manufacturer_uuid,
    p.pseudo_sales,
    p.mark_as_topseller,
    p.price_group_uuid,
    p.filter_group_uuid,
    p.is_closeout,
    p.allow_notification,
    p.template,
    p.created_at,
    p.updated_at,
    d.supplier_number,
    d.sales,
    d.active,
    d.stock,
    d.min_stock,
    d.weight,
    d.position,
    d.width,
    d.height,
    d.length,
    d.ean,
    d.unit_uuid,
    d.purchase_steps,
    d.max_purchase,
    d.min_purchase,
    d.purchase_unit,
    d.reference_unit,
    d.release_date,
    d.shipping_free,
    d.purchase_price,
    d.additional_text,
    d.pack_unit,
    p.name,
    p.description,
    p.description_long,
    p.meta_title,
    p.keywords,
    d.product_id as original_id,
    d.id as original_detail_id
FROM product_detail d
    INNER JOIN product p
        ON p.id = d.product_id
;

DROP TABLE IF EXISTS product;
ALTER TABLE product_new RENAME TO `product`;

# product tables merged > update product_uuid references with new order number uuid

UPDATE product_avoid_customer_group pac SET
    pac.product_uuid = (SELECT uuid FROM product p WHERE p.original_id = product_id AND p.is_main = 1 LIMIT 1);

UPDATE product_category pc SET pc.product_uuid  = (SELECT uuid FROM product p WHERE p.original_id = product_id AND p.is_main = 1 LIMIT 1);

DELETE FROM product_category_ro;

UPDATE product_attachment pd SET
    pd.product_uuid = (SELECT uuid FROM product p WHERE p.original_id = product_id AND p.is_main = 1 LIMIT 1)
;

UPDATE product_link p SET
    p.product_uuid = (SELECT uuid FROM product WHERE product.original_id = product_id AND product.is_main LIMIT 1)
;

UPDATE product_price p SET
    p.product_uuid = (SELECT sub.uuid FROM product sub WHERE sub.original_detail_id = p.product_detail_id LIMIT 1)
;

UPDATE product_accessory p SET
    p.product_uuid         = (SELECT uuid FROM product WHERE original_id = p.product_id AND product.is_main LIMIT 1),
    p.related_product_uuid = (SELECT uuid FROM product WHERE original_id = p.related_product AND product.is_main LIMIT 1)
;

UPDATE product_similar p SET
    p.product_uuid         = (SELECT uuid FROM product WHERE original_id = p.product_id AND product.is_main LIMIT 1),
    p.related_product_uuid = (SELECT uuid FROM product WHERE original_id = p.related_product AND product.is_main LIMIT 1)
;

UPDATE product_category_seo pcs SET
    pcs.product_uuid  = (SELECT uuid FROM product p WHERE original_id = product_id AND is_main = 1 LIMIT 1)
;

UPDATE product_media p SET
    p.product_uuid = (SELECT uuid FROM product WHERE product.original_id = product_id AND is_main = 1 LIMIT 1)
;

UPDATE product_esd pe SET pe.product_uuid  = (SELECT uuid FROM product WHERE product.original_detail_id = product_detail_id LIMIT 1);

UPDATE filter_product f SET
    f.product_uuid = (SELECT uuid FROM product WHERE product.original_id = product_id AND is_main = 1 LIMIT 1)
;

ALTER TABLE `filter_product`
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (`product_uuid`, `filter_value_uuid`),
    ADD INDEX (`filter_value_uuid`, `product_uuid`)
;

UPDATE product_stream_tab SET product_uuid = (SELECT uuid FROM product WHERE product.original_id = product_id AND is_main = 1 LIMIT 1) WHERE product_id IS NOT NULL;
UPDATE product_stream_assignment SET product_uuid = (SELECT uuid FROM product WHERE product.original_id = product_id AND is_main = 1 LIMIT 1) WHERE product_id IS NOT NULL;
UPDATE statistic_product_impression SET product_uuid = (SELECT uuid FROM product WHERE product.original_id = product_id AND is_main = 1 LIMIT 1) WHERE product_id IS NOT NULL;
UPDATE blog_product SET product_uuid = (SELECT uuid FROM product WHERE product.original_id = product_id AND is_main = 1 LIMIT 1) WHERE blog_id IS NOT NULL;
UPDATE product_also_bought_ro pabr SET pabr.product_uuid = (SELECT uuid FROM product WHERE product.original_id = product_id AND is_main = 1 LIMIT 1) WHERE pabr.product_id  IS NOT NULL;
UPDATE product_also_bought_ro pabr SET pabr.related_product_uuid = (SELECT uuid FROM product WHERE product.original_id = product_id AND is_main = 1 LIMIT 1) WHERE related_product_id  IS NOT NULL;
UPDATE product_vote SET product_uuid = (SELECT uuid FROM product WHERE product.original_id = product_id AND is_main = 1 LIMIT 1) WHERE product_id IS NOT NULL;

UPDATE premium_product SET product_uuid = product_order_number;

UPDATE product_price p SET
    p.product_uuid = (SELECT uuid FROM product WHERE product.original_detail_id = product_detail_id LIMIT 1)
;



ALTER TABLE `product`
    ADD FOREIGN KEY (`product_manufacturer_uuid`) REFERENCES `product_manufacturer` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
    ADD FOREIGN KEY (`tax_uuid`) REFERENCES `tax` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
    ADD FOREIGN KEY (`filter_group_uuid`) REFERENCES `filter` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

# references to product table
ALTER TABLE product_stream_tab
    ADD CONSTRAINT `fk_product_stream_tab.product_uuid`
FOREIGN KEY (product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE product_stream_assignment
    ADD CONSTRAINT `fk_product_stream_assignment.product_uuid`
FOREIGN KEY (product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE statistic_product_impression
    ADD CONSTRAINT `fk_statistic_product_impression.product_uuid`
FOREIGN KEY (product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE product_avoid_customer_group
    ADD CONSTRAINT `fk_product_avoid_customer_group.product_uuid`
FOREIGN KEY (product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE product_category
    ADD CONSTRAINT `fk_product_category.product_uuid`
FOREIGN KEY (product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE product_category_seo
    ADD CONSTRAINT `fk_product_category_seo.product_uuid`
FOREIGN KEY (product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

# ALTER TABLE product_detail
#     ADD CONSTRAINT `fk_product_detail.product_uuid`
# FOREIGN KEY (product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE product_attachment
    ADD CONSTRAINT `fk_product_attachment.product_uuid`
FOREIGN KEY (product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE product_esd
    ADD CONSTRAINT `fk_product_esd.product_uuid`
FOREIGN KEY (product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE product_media
    ADD CONSTRAINT `fk_product_media.product_uuid`
FOREIGN KEY (product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE product_link
    ADD CONSTRAINT `fk_product_link.product_uuid`
FOREIGN KEY (product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE product_accessory
    ADD CONSTRAINT `fk_product_accessory.product_uuid`
FOREIGN KEY (product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE,

    ADD CONSTRAINT `fk_product_accessory.related_product_uuid`
FOREIGN KEY (related_product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE product_similar
    ADD CONSTRAINT `fk_product_similar.product_uuid`
FOREIGN KEY (product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_product_similar.related_product_uuid`
FOREIGN KEY (related_product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE product_similar_shown_ro
    ADD CONSTRAINT `fk_product_similar_shown_ro.product_uuid`
FOREIGN KEY (product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_product_similar_shown_ro.related_product_uuid`
FOREIGN KEY (related_product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE product_top_seller_ro
    ADD CONSTRAINT `fk_product_top_seller_ro.product_uuid`
FOREIGN KEY (product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE product_also_bought_ro
    ADD CONSTRAINT `fk_product_also_bought_ro.product_uuid`
FOREIGN KEY (product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_product_also_bought_ro.related_product_uuid`
FOREIGN KEY (related_product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `filter_product`
    ADD FOREIGN KEY (`product_uuid`) REFERENCES `product` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `blog_product`
    ADD FOREIGN KEY (`product_uuid`) REFERENCES `product` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `product_vote`
    ADD FOREIGN KEY (`product_uuid`) REFERENCES `product` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

# ALTER TABLE `product_media`
#     ADD FOREIGN KEY (`product_detail_uuid`) REFERENCES `product_detail` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `product_price`
    ADD FOREIGN KEY (`product_uuid`) REFERENCES `product` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;


# references to product_detail
#
# ALTER TABLE product_attribute
#     ADD CONSTRAINT `fk_product_attribute.product_detail_uuid`
# FOREIGN KEY (product_detail_uuid) REFERENCES product_detail (uuid) ON DELETE CASCADE ON UPDATE CASCADE
# ;
#

ALTER TABLE premium_product
    ADD CONSTRAINT `fk_premium_product.product_uuid`
FOREIGN KEY (product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE;

