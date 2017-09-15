# cleanup data
UPDATE product_price p SET p.pricegroup = 'EK' WHERE p.pricegroup NOT IN (SELECT group_key FROM customer_group);
UPDATE product_price p SET p.customer_group_uuid = (SELECT c.uuid FROM customer_group c WHERE c.group_key = p.pricegroup LIMIT 1);

-- DROP FOREIGN KEYS BEFORE DROPPING IDs
ALTER TABLE area_country_attribute
    DROP FOREIGN KEY `area_country_attribute_ibfk_1`
;

ALTER TABLE area_country_state_attribute
    DROP FOREIGN KEY `area_country_state_attribute_ibfk_1`
;

ALTER TABLE blog_attribute
    DROP FOREIGN KEY `blog_attribute_ibfk_1`
;

ALTER TABLE category
    DROP FOREIGN KEY `s_categories_fk_stream_id`
;

ALTER TABLE category_attribute
    DROP FOREIGN KEY `category_attribute_ibfk_1`
;

ALTER TABLE customer_address
    DROP FOREIGN KEY `customer_address_ibfk_1`,
    DROP FOREIGN KEY `customer_address_ibfk_2`,
    DROP FOREIGN KEY `customer_address_ibfk_3`
;

ALTER TABLE customer_address_attribute
    DROP FOREIGN KEY `customer_address_attribute_ibfk_1`
;

ALTER TABLE customer_attribute
    DROP FOREIGN KEY `customer_attribute_ibfk_1`
;

ALTER TABLE customer_group_attribute
    DROP FOREIGN KEY `customer_group_attribute_ibfk_1`
;

ALTER TABLE filter_attribute
    DROP FOREIGN KEY `filter_attribute_ibfk_1`
;

ALTER TABLE filter_option_attribute
    DROP FOREIGN KEY `filter_option_attribute_ibfk_1`
;

ALTER TABLE filter_value_attribute
    DROP FOREIGN KEY `filter_value_attribute_ibfk_1`
;

ALTER TABLE mail_attribute
    DROP FOREIGN KEY `mail_attribute_ibfk_1`
;

ALTER TABLE mail
    DROP FOREIGN KEY `mail_ibfk_1`
;

ALTER TABLE media_attribute
    DROP FOREIGN KEY `media_attribute_ibfk_1`
;

ALTER TABLE payment_method_attribute
    DROP FOREIGN KEY `payment_method_attribute_ibfk_1`
;

ALTER TABLE product_attachment_attribute
    DROP FOREIGN KEY `product_attachment_attribute_ibfk_1`
;

-- ALTER TABLE product_configurator_group_attribute
--     DROP FOREIGN KEY `product_configurator_group_attribute_ibfk_1`
-- ;
--
-- ALTER TABLE product_configurator_option_attribute
--     DROP FOREIGN KEY `product_configurator_option_attribute_ibfk_1`
-- ;
--
-- ALTER TABLE product_configurator_template_attribute
--     DROP FOREIGN KEY `product_configurator_template_attribute_ibfk_1`
-- ;
--
-- ALTER TABLE product_configurator_template_price_attribute
--     DROP FOREIGN KEY `product_configurator_template_price_attribute_ibfk_1`
-- ;

ALTER TABLE product_esd_attribute
    DROP FOREIGN KEY `product_esd_attribute_ibfk_1`
;

ALTER TABLE product_link_attribute
    DROP FOREIGN KEY `product_link_attribute_ibfk_1`
;

ALTER TABLE product_manufacturer_attribute
    DROP FOREIGN KEY `product_manufacturer_attribute_ibfk_1`
;

ALTER TABLE product_media_attribute
    DROP FOREIGN KEY `product_media_attribute_ibfk_1`
;

ALTER TABLE product_price_attribute
    DROP FOREIGN KEY `product_price_attribute_ibfk_1`
;

ALTER TABLE product_stream_assignment
    DROP FOREIGN KEY `s_product_streams_selection_fk_article_id`,
    DROP FOREIGN KEY `s_product_streams_selection_fk_stream_id`
;

ALTER TABLE product_stream_attribute
    DROP FOREIGN KEY `product_stream_attribute_ibfk_1`
;

ALTER TABLE product_stream_tab
    DROP FOREIGN KEY `s_product_streams_articles_fk_article_id`,
    DROP FOREIGN KEY `s_product_streams_articles_fk_stream_id`
;

-- DROP IDs
ALTER TABLE album
    DROP `id`,
    DROP `parent_id`
;

ALTER TABLE area
    DROP `id`
;

ALTER TABLE area_country
    DROP `id`,
    DROP `area_id`,
    DROP `en`
;

ALTER TABLE area_country_attribute
    DROP `id`,
    DROP `area_country_id`
;

ALTER TABLE area_country_state
    DROP `id`,
    DROP `area_country_id`
;

ALTER TABLE area_country_state_attribute
    DROP `id`,
    DROP `area_country_state_id`
;

ALTER TABLE attribute_configuration
    DROP `id`
;

ALTER TABLE blog
    DROP `id`,
    DROP `user_id`,
    DROP `category_id`
;

ALTER TABLE blog_attribute
    DROP `id`,
    DROP `blog_id`
;

ALTER TABLE blog_comment
    DROP `id`,
    DROP `blog_id`
;

ALTER TABLE blog_media
    DROP `id`,
    DROP `blog_id`,
    DROP `media_id`
;

ALTER TABLE blog_product
    DROP `id`,
    DROP `blog_id`,
    DROP `product_id`
;

ALTER TABLE blog_tag
    DROP `id`,
    DROP `blog_id`
;

ALTER TABLE category
    DROP `id`,
    DROP `parent_id`,
    DROP `media_id`,
    DROP `product_stream_id`
--    DROP `level // todo: still required?
;

ALTER TABLE category_attribute
    DROP `id`,
    DROP `category_id`
;

ALTER TABLE category_avoid_customer_group
    DROP `category_id`,
    DROP `customer_group_id`
;

ALTER TABLE config_form
    DROP `id`,
    DROP `parent_id`,
    DROP `plugin_id`
;

ALTER TABLE config_form_field
    DROP `id`,
    DROP `config_form_id`
;

ALTER TABLE config_form_field_translation
    DROP `id`,
    DROP `config_form_field_id`,
    DROP `locale_id`
;

ALTER TABLE config_form_field_value
    DROP `id`,
    DROP `config_form_field_id`, -- todo: still empty -> migrate uuid!!
    DROP `shop_id`
;

ALTER TABLE config_form_translation
    DROP `id`,
    DROP `config_form_id`,
    DROP `locale_id`
;

ALTER TABLE currency
    DROP `id`
;

ALTER TABLE customer
    DROP `id`,
    DROP `last_payment_method_id`,
    DROP `default_payment_method_id`,
    DROP `shop_id`,
    DROP `main_shop_id`,
    DROP `price_group_id`,
    DROP `default_billing_address_id`,
    DROP `default_shipping_address_id`
;

ALTER TABLE customer_address
    DROP `id`,
    DROP `customer_id`
    #DROP `area_country_id`,
    #DROP `area_country_state_id`
;

ALTER TABLE customer_address_attribute
    DROP `id`,
    DROP `address_id`
;

ALTER TABLE customer_attribute
    DROP `id`,
    DROP `customer_id`
;

ALTER TABLE customer_group
    DROP `id`
;

ALTER TABLE customer_group_attribute
    DROP `id`,
    DROP `customer_group_id`
;

ALTER TABLE customer_group_discount
    DROP `id`,
    DROP `customer_group_id`
;

ALTER TABLE filter
    DROP `id`
;

ALTER TABLE filter_attribute
    DROP `id`,
    DROP `filter_id`
;

ALTER TABLE filter_option
    DROP `id`
;

ALTER TABLE filter_option_attribute
    DROP `id`,
    DROP `option_id`
;

ALTER TABLE filter_product
    DROP `product_id`,
    DROP `value_id`
;

ALTER TABLE filter_relation
    DROP `id`,
    DROP `group_id`,
    DROP `option_id`
;

ALTER TABLE filter_value
    DROP `id`,
    DROP `option_id`,
    DROP `media_id`
;

ALTER TABLE filter_value_attribute
    DROP `id`,
    DROP `value_id`
;

ALTER TABLE holiday
    DROP `id`
;

ALTER TABLE listing_facet
    DROP `id`
;

ALTER TABLE listing_sorting
    DROP `id`
;

ALTER TABLE locale
    DROP `id`
;

ALTER TABLE log
    DROP `id`
;

ALTER TABLE mail
    DROP `id`,
    DROP `order_state_id`
;

ALTER TABLE mail_attachment
    DROP `id`,
    DROP `mail_id`,
    DROP `media_id`,
    DROP `shop_id`
;

ALTER TABLE mail_attribute
    DROP `id`,
    DROP `mail_id`
;

ALTER TABLE media
    DROP `id`,
    DROP `album_id`,
    DROP `user_id`,
    DROP `extension`,
    DROP `width`,
    DROP `height`
;

ALTER TABLE media_attribute
    DROP `id`,
    DROP `media_id`
;

ALTER TABLE order_state
    DROP `id`
;

ALTER TABLE payment_method
    DROP `id`,
    DROP `plugin_id`
;

ALTER TABLE payment_method_attribute
    DROP `id`,
    DROP `payment_method_id`
;

ALTER TABLE payment_method_country
    DROP `payment_method_id`,
    DROP `area_country_id`
;

ALTER TABLE payment_method_shop
    DROP `payment_method_id`,
    DROP `shop_id`
;

DELETE FROM plugin WHERE namespace != 'ShopwarePlugins';

ALTER TABLE plugin
    DROP `id`,
    DROP `namespace`,
    DROP `source`
;

ALTER TABLE plugin_category
    DROP `id`,
    DROP `parent_id`
;

ALTER TABLE premium_product
    DROP `id`,
    DROP `shop_id`
;

ALTER TABLE price_group
    DROP `id`
;

ALTER TABLE price_group_discount
    DROP `id`,
    DROP `price_group_id`,
    DROP `customer_group_id`
;

ALTER TABLE product
-- `price_group_id` // todo: not migrated yet > still required?
-- `configurator_set_id` // todo: not migrated yet > still required?
    DROP `manufacturer_id`,
    DROP `tax_id`,
    DROP `main_detail_id`,
    DROP `filter_group_id`,
    DROP `name`,
    DROP `description`,
    DROP `description_long`,
    DROP `keywords`,
    DROP `meta_title`,
    DROP PRIMARY KEY,
    DROP `id`,
    DROP INDEX `ui_product.uuid`,
    ADD PRIMARY KEY (`uuid`)
;

ALTER TABLE product_accessory
    DROP `id`,
    DROP `product_id`,
    DROP `related_product`
;

--     product_also_bought_ro missing here // todo: migrate?

ALTER TABLE product_attachment
    DROP `id`,
    DROP `product_id`
;

ALTER TABLE product_attachment_attribute
    DROP `id`,
    DROP `product_attachment`
;

ALTER TABLE product_attribute
    DROP `id`,
    DROP `product_details_id`,
    DROP `articleID`
;

ALTER TABLE product_avoid_customer_group
    DROP `product_id`,
    DROP `customer_group_id`
;

ALTER TABLE product_category
  DROP `id`,
  DROP `uuid`,
  DROP `product_id`,
  DROP `category_id`,
  ADD PRIMARY KEY (`product_uuid`, `category_uuid`)
;

ALTER TABLE product_category_ro
    DROP `id`,
    DROP `category_id`,
    DROP `parent_category_id`,
    DROP `product_id`,
    DROP `uuid`,
    ADD PRIMARY KEY (`product_uuid`, `category_uuid`, `parent_category_uuid`)
;

ALTER TABLE product_category_seo
-- todo: id not migrated to uuid yet
    DROP `shop_id`,
    DROP `product_id`,
    DROP `category_id`
;

-- ALTER product_configurator_dependency
--     DROP `id`
-- ;

-- ALTER TABLE product_configurator_group
--     DROP `id`
-- ;

UPDATE product_detail SET
  uuid = order_number
;

ALTER TABLE product_detail
    DROP `id`,
    DROP `unit_id`,
    DROP `product_id`,
    DROP `order_number`
;

ALTER TABLE product_esd
    DROP `id`,
    DROP `product_id`,
    DROP `product_detail_id`
;

ALTER TABLE product_esd_attribute
    DROP `id`,
    DROP `esd_id`
;

ALTER TABLE product_esd_serial
    DROP `id`,
    DROP `esd_id`
;

ALTER TABLE product_link
    DROP `id`,
    DROP `product_id`
;

ALTER TABLE product_link_attribute
    DROP `id`,
    DROP `information_id`
;

ALTER TABLE product_manufacturer
    DROP `id`
;

ALTER TABLE product_manufacturer_attribute
    DROP `id`,
    DROP `manufacturer_id`
;

ALTER TABLE product_media
    DROP `id`,
    DROP `product_id`,
    DROP `product_detail_id`
--     todo: handle parent_id -> migration?
--           media_id ?
;

ALTER TABLE product_media_attribute
    DROP `id`,
    DROP `image_id`
;

ALTER TABLE product_media_mapping
    DROP `id`,
    DROP `image_id`
;

ALTER TABLE product_media_mapping_rule
    DROP `id`,
    DROP `mapping_id`
--     todo: option_id
;

ALTER TABLE product_notification
    DROP `id`
;

ALTER TABLE product_price
    DROP `id`,
    DROP `product_id`,
    DROP FOREIGN KEY `fk_product_price.product_uuid`,
    DROP FOREIGN KEY `fk_product_price.product_detail_uuid`,
    DROP `product_uuid`,
    DROP `product_detail_id`
;

ALTER TABLE product_price_attribute
    DROP `id`,
    DROP `price_id`
;

ALTER TABLE product_similar
    DROP `id`,
    DROP `product_id`,
    DROP `related_product`
;

ALTER TABLE product_similar_shown_ro -- todo: all migrated?
    DROP `id`,
    DROP `product_id`,
    DROP `related_product_id`
;

ALTER TABLE product_stream
    DROP `id`,
    DROP `listing_sorting_id`
;

ALTER TABLE product_stream_assignment
    DROP `id`,
    DROP `product_stream_id`,
    DROP `product_id`
;

ALTER TABLE product_stream_attribute
    DROP `id`,
    DROP `product_stream_id`
;

ALTER TABLE product_stream_tab
    DROP `id`,
    DROP `product_stream_id`,
    DROP `product_id`
;

ALTER TABLE product_top_seller_ro
    DROP `id`,
    DROP `product_id`
;

ALTER TABLE product_vote
    DROP `id`,
    DROP `product_id`,
    DROP `shop_id`
;

ALTER TABLE seo_url
    DROP `id`
;

DROP TABLE IF EXISTS `s_core_sessions`;

ALTER TABLE `snippet` DROP INDEX `namespace`;
ALTER TABLE `snippet` DROP locale_id;


DROP TABLE s_media_album_settings;

ALTER TABLE `product_stream` DROP `sorting`;


ALTER TABLE `album`
    DROP `name`;

ALTER TABLE `area`
    DROP `name`;

ALTER TABLE `area_country`
    DROP `name`,
    DROP `notice`;

ALTER TABLE `area_country_state`
    DROP `name`;

ALTER TABLE `category`
    DROP `name`,
    DROP `meta_keywords`,
    DROP `meta_title`,
    DROP `meta_description`,
    DROP `cms_headline`,
    DROP `cms_description`;

ALTER TABLE `currency`
    DROP `short_name`,
    DROP `name`;

ALTER TABLE `customer`
    DROP `customer_group_key`,
    DROP `price_group_uuid`;

ALTER TABLE `customer_address`
    DROP `area_country_id`,
    DROP `area_country_state_id`;

ALTER TABLE `customer_group`
    DROP `name`,
    DROP `group_key`;

ALTER TABLE `filter`
    DROP `name`;

ALTER TABLE `filter_option`
    DROP `name`;

ALTER TABLE `filter_value`
    DROP `value`;

ALTER TABLE `holiday`
    DROP `name`;

ALTER TABLE `listing_facet`
    DROP `name`;

ALTER TABLE `listing_sorting`
    DROP `label`;

ALTER TABLE `locale`
    DROP `language`,
    DROP `territory`;

ALTER TABLE `order_state`
    DROP `description`;

ALTER TABLE `payment_method`
    DROP `name`,
    DROP `additional_description`;

ALTER TABLE `price_group`
    DROP `name`;

ALTER TABLE `product`
    DROP `shipping_time`,
    DROP `price_group_id`,
    DROP `available_from`,
    DROP `available_to`,
    DROP `mode`;

ALTER TABLE `product_attachment`
    DROP `description`;

ALTER TABLE `product_category_seo`
    DROP `id`;

ALTER TABLE `product_detail`
    DROP `additional_text`,
    DROP `pack_unit`,
    DROP `shipping_time`;

ALTER TABLE `product_link`
    DROP `description`,
    DROP `link`;

ALTER TABLE `product_manufacturer`
    DROP `name`,
    DROP `description`,
    DROP `meta_title`,
    DROP `meta_description`,
    DROP `img`,
    DROP `meta_keywords`;

ALTER TABLE `product_media`
    DROP `img`,
    DROP `width`,
    DROP `height`,
    DROP `relations`,
    DROP `extension`,
    DROP `description`,
    DROP `parent_id`,
    DROP `media_id`;

ALTER TABLE `product_price`
    DROP `pricegroup`;

# ALTER TABLE `product_stream`
#     DROP `name`,
#     DROP `description`;

ALTER TABLE `shipping_method`
    DROP `name`,
    DROP `description`,
    DROP `comment`,
    DROP `shop_id`,
    DROP `customer_group_id`;

ALTER TABLE `shipping_method_category`
    DROP `shipping_method_id`,
    DROP `category_id`;

ALTER TABLE `shipping_method_country`
    DROP `shipping_method_id`,
    DROP `area_country_id`;

ALTER TABLE `shipping_method_holiday`
    DROP `shipping_method_id`,
    DROP `holiday_id`;

ALTER TABLE `shipping_method_payment_method`
    DROP `shipping_method_id`,
    DROP `payment_method_id`;

ALTER TABLE `shipping_method_price`
    ADD UNIQUE `shipping_method_uuid_quantity_from` (`shipping_method_uuid`, `quantity_from`),
    DROP INDEX `from`;

ALTER TABLE `shipping_method_price`
    DROP `id`,
    DROP `shipping_method_id`;

ALTER TABLE `shop_currency`
    DROP `shop_id`,
    DROP `currency_id`;

ALTER TABLE `shop`
    DROP `id`,
    DROP `main_id`,
    DROP `shop_template_id`,
    DROP `document_template_id`,
    DROP `category_id`,
    DROP `locale_id`,
    DROP `currency_id`,
    DROP `customer_group_id`,
    DROP `fallback_id`,
    DROP `payment_method_id`,
    DROP `shipping_method_id`,
    DROP `area_country_id`;

ALTER TABLE `tax_area_rule`
    DROP `name`;

ALTER TABLE `unit`
    DROP `short_code`,
    DROP `name`;

ALTER TABLE `media`
    DROP `name`,
    DROP `description`;

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

ALTER TABLE `tax_area_rule`
    DROP `id`,
    DROP `area_id`,
    DROP `area_country_id`,
    DROP `area_country_state_id`,
    DROP `tax_id`,
    DROP `customer_group_id`;
