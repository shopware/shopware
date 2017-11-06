# cleanup data

## fix product media relations which product or media entity no more exists
DELETE FROM product_media WHERE media_uuid NOT IN (SELECT uuid FROM media);
DELETE FROM product_media WHERE product_uuid NOT IN (SELECT uuid FROM product);
ALTER TABLE `product_media` ADD FOREIGN KEY (`media_uuid`) REFERENCES `media` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;


## fix product votes without associated shop
UPDATE product_vote SET shop_uuid = CONCAT('SWAG-SHOP-UUID-1') WHERE shop_id IS NULL;
ALTER TABLE `product_vote_average_ro`
    ADD FOREIGN KEY (`shop_uuid`) REFERENCES `shop` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;


## fixes prices without existing customer group association
UPDATE product_price p SET p.pricegroup = 'EK' WHERE p.pricegroup NOT IN (SELECT group_key FROM customer_group);
UPDATE product_price p SET p.customer_group_uuid = (SELECT c.uuid FROM customer_group c WHERE c.group_key = p.pricegroup LIMIT 1);
ALTER TABLE `product_price`
    ADD FOREIGN KEY (`customer_group_uuid`) REFERENCES `customer_group` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;



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
    DROP `name`,
    DROP `description`,
    DROP `description_long`,
    DROP `keywords`,
    DROP `meta_title`
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

DELETE FROM product_media WHERE parent_uuid IS NOT NULL;

ALTER TABLE product_media DROP product_detail_uuid;

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
    DROP `product_detail_uuid`,
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

ALTER TABLE `product`
    DROP original_id,
    DROP pack_unit,
    DROP additional_text,
    DROP original_detail_id
;

ALTER TABLE product_esd DROP product_detail_uuid;

ALTER TABLE premium_product DROP product_order_number;


ALTER TABLE `price_group`
    DROP `name`;

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

ALTER TABLE `product_price` DROP `pricegroup`;

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

ALTER TABLE `tax_area_rule`
    DROP `id`,
    DROP `area_id`,
    DROP `area_country_id`,
    DROP `area_country_state_id`,
    DROP `tax_id`,
    DROP `customer_group_id`;

DROP TABLE `s_order_attributes`;
DROP TABLE `s_order`;
DROP TABLE `s_order_basket_attributes`;
DROP TABLE `s_order_basket`;
DROP TABLE `s_order_basket_signatures`;
DROP TABLE `s_order_billingaddress_attributes`;
DROP TABLE `s_order_billingaddress`;
DROP TABLE `s_order_comparisons`;
DROP TABLE `s_order_details_attributes`;
DROP TABLE `s_order_details`;
DROP TABLE `s_order_documents_attributes`;
DROP TABLE `s_order_documents`;
DROP TABLE `s_order_esd`;
DROP TABLE `s_order_history`;
DROP TABLE `s_order_notes`;
DROP TABLE `s_order_number`;
DROP TABLE `s_order_shippingaddress_attributes`;
DROP TABLE `s_order_shippingaddress`;
DROP TABLE product_detail;

