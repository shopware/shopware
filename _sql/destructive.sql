# cleanup data

## fix product media relations which product or media entity no more exists
DELETE FROM product_media WHERE media_uuid NOT IN (SELECT uuid FROM media);
DELETE FROM product_media WHERE product_uuid NOT IN (SELECT uuid FROM product);
ALTER TABLE `product_media` ADD FOREIGN KEY (`media_uuid`) REFERENCES `media` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;


## fix product votes without associated shop
UPDATE product_vote SET shop_uuid = CONCAT('SWAG-SHOP-UUID-1') WHERE shop_id IS NULL;
ALTER TABLE `product_vote_average`
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

ALTER TABLE category_attribute
    DROP FOREIGN KEY `category_attribute_ibfk_1`
;

ALTER TABLE customer_address
    DROP FOREIGN KEY `customer_address_ibfk_1`,
    DROP FOREIGN KEY `customer_address_ibfk_2`,
    DROP FOREIGN KEY `customer_address_ibfk_3`
;

ALTER TABLE customer_group_attribute
    DROP FOREIGN KEY `customer_group_attribute_ibfk_1`
;

ALTER TABLE mail
    DROP FOREIGN KEY `mail_ibfk_1`
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

-- DROP IDs
ALTER TABLE media_album
    DROP `id`,
    DROP `parent_id`
;

ALTER TABLE country_area
    DROP `id`
;

ALTER TABLE country
    DROP `id`,
    DROP `country_area_id`,
    DROP `en`
;

ALTER TABLE area_country_attribute
    DROP `id`,
    DROP `country_id`
;

ALTER TABLE country_state
    DROP `id`,
    DROP `country_id`
;

ALTER TABLE area_country_state_attribute
    DROP `id`,
    DROP `country_state_id`
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
    DROP `config_form_id`,
    DROP label,
    DROP description
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
    #DROP `country_area_id`,
    #DROP `country_state_id`
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
    DROP `media_album_id`,
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
    DROP `country_id`
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

ALTER TABLE product_also_bought_ro
    DROP `id`,
    DROP `product_id`,
    DROP `related_product_id`
;

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

ALTER TABLE product_seo_category
    DROP `id`,
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


ALTER TABLE `media_album`
    DROP `name`;

ALTER TABLE `country_area`
    DROP `name`;

ALTER TABLE `country`
    DROP `name`,
    DROP `notice`;

ALTER TABLE `country_state`
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
    DROP `country_id`,
    DROP `country_state_id`;

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
    DROP `pricegroup`
;

# ALTER TABLE `product_stream`
#     DROP `name`,
#     DROP `description`;

ALTER TABLE `shipping_method`
    DROP `id`,
    DROP `name`,
    DROP `description`,
    DROP `comment`,
    DROP `shop_id`,
    DROP `shop_uuid`,
    DROP `customer_group_id`;

ALTER TABLE `shipping_method_category`
    DROP `shipping_method_id`,
    DROP `category_id`;

ALTER TABLE `shipping_method_country`
    DROP `shipping_method_id`,
    DROP `country_id`;

ALTER TABLE `shipping_method_holiday`
    DROP `shipping_method_id`,
    DROP `holiday_id`;

ALTER TABLE `shipping_method_payment_method`
    DROP `shipping_method_id`,
    DROP `payment_method_id`;

ALTER TABLE `shipping_method_price`
    ADD UNIQUE `shipping_method_uuid_quantity_from` (`shipping_method_uuid`, `quantity_from`),
    DROP INDEX `from`
;

ALTER TABLE `shipping_method_price`
    DROP `id`,
    DROP `shipping_method_id`
;

ALTER TABLE `shipping_method_attribute`
    DROP `id`,
    DROP `shipping_method_id`
;

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
    DROP `country_id`
;

ALTER TABLE `shop_form`
    DROP `id`
;

ALTER TABLE `shop_form_attribute`
    DROP `id`,
    DROP `shop_form_id`
;

ALTER TABLE `shop_form_field`
    DROP `id`,
    DROP `shop_form_id`
;

ALTER TABLE `shop_page`
    DROP `id`,
    DROP `parent_id`
;

ALTER TABLE `shop_page_attribute`
    DROP `id`,
    DROP `shop_page_id`
;

ALTER TABLE `shop_page_group`
    DROP `id`
-- todo: mapping_id conversion?
;

ALTER TABLE `shop_page_group_mapping`
    DROP `shop_id`,
    DROP `shop_page_group_id`
;

ALTER TABLE `shop_template`
    DROP `id`,
    DROP `plugin_id`,
    DROP `parent_id`
;

ALTER TABLE `shop_template_config_form`
    DROP `id`,
    DROP `parent_id`,
    DROP `shop_template_id`
;

ALTER TABLE `shop_template_config_form_field`
    DROP `id`,
    DROP `shop_template_id`,
    DROP `shop_template_config_form_id`
;

ALTER TABLE `shop_template_config_form_field_value`
    DROP `id`,
    DROP`shop_template_config_form_field_id`,
    DROP `shop_id`
;

ALTER TABLE `shop_template_config_preset`
    DROP `id`,
    DROP `shop_template_id`
;

ALTER TABLE `shopping_world_component`
    DROP `id`,
    DROP `plugin_id`
;

ALTER TABLE `shopping_world_component_field`
    DROP `id`,
    DROP `shopping_world_component_id`
;

ALTER TABLE `snippet`
    DROP `id`
#     DROP `shop_id` Todo: removal produces error on snippet import
;

ALTER TABLE `statistic_address_pool`
    DROP `id`
;

ALTER TABLE `statistic_current_customer`
    DROP `id`,
    DROP `customer_id`
;

ALTER TABLE `statistic_product_impression`
    DROP `id`,
    DROP `product_id`,
    DROP `shop_id`
;

ALTER TABLE `statistic_referer`
    DROP `id`
;

ALTER TABLE `statistic_search`
    DROP `id`,
    DROP `shop_id`
;

ALTER TABLE `statistic_visitor`
    DROP `id`,
    DROP `shop_id`
;

ALTER TABLE `tax`
    DROP `id`
;

ALTER TABLE `tax_area_rule`
    DROP `name`;

ALTER TABLE `unit`
    DROP `id`,
    DROP `short_code`,
    DROP `name`;

ALTER TABLE `media`
    DROP `name`,
    DROP `description`;

ALTER TABLE `tax_area_rule`
    DROP `id`,
    DROP `country_id`,
    DROP `country_area_id`,
    DROP `country_state_id`,
    DROP `tax_id`,
    DROP `customer_group_id`
;

ALTER TABLE `user`
    DROP `id`,
    DROP `user_role_id`,
    DROP `locale_id`
;

ALTER TABLE `user_attribute`
    DROP `id`,
    DROP `user_id`
;

ALTER TABLE config_form
    DROP label,
    DROP description
;

ALTER TABLE `mail`
    DROP `from_mail`,
    DROP `from_name`,
    DROP `subject`,
    DROP `content`,
    DROP `content_html`;

# MLP reduction
ALTER TABLE product DROP COLUMN filter_group_uuid;

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
DROP TABLE `product_detail`;
DROP TABLE area_country_attribute;
DROP TABLE area_country_state_attribute;
DROP TABLE attribute_configuration_translation;
DROP TABLE attribute_configuration;
DROP TABLE blog_attribute;
DROP TABLE blog_comment;
DROP TABLE blog_media;
DROP TABLE blog_product;
DROP TABLE blog_tag_translation;
DROP TABLE blog_tag;
DROP TABLE blog_translation;
DROP TABLE blog;
DROP TABLE category_attribute;
DROP TABLE category_avoid_customer_group;
DROP TABLE customer_address_attribute;
DROP TABLE customer_attribute;
DROP TABLE customer_group_attribute;
DROP TABLE filter_attribute;
DROP TABLE filter_option_attribute;
DROP TABLE filter_option_translation;
DROP TABLE filter_product;
DROP TABLE filter_relation;
DROP TABLE filter_translation;
DROP TABLE filter_value_attribute;
DROP TABLE filter_value_translation;
DROP TABLE filter_value;
DROP TABLE filter_option;
DROP TABLE filter;
DROP TABLE mail_attribute;
DROP TABLE media_attribute;
DROP TABLE payment_method_attribute;
DROP TABLE payment_method_country;
DROP TABLE payment_method_shop;
DROP TABLE plugin_category;
DROP TABLE premium_product;
DROP TABLE price_group_discount;
DROP TABLE price_group_translation;
DROP TABLE price_group;
DROP TABLE product_accessory;
DROP TABLE product_also_bought_ro;
DROP TABLE product_attachment_attribute;
DROP TABLE product_attachment_translation;
DROP TABLE product_attachment;
DROP TABLE product_attribute;
DROP TABLE product_avoid_customer_group;
DROP TABLE product_configurator_dependency;
DROP TABLE product_configurator_group_attribute;
DROP TABLE product_configurator_group_translation;
DROP TABLE product_configurator_group;
DROP TABLE product_configurator_option_attribute;
DROP TABLE product_configurator_option_relation;
DROP TABLE product_configurator_option_translation;
DROP TABLE product_configurator_option;
DROP TABLE product_configurator_price_variation;
DROP TABLE product_configurator_set_group_relation;
DROP TABLE product_configurator_set_option_relation;
DROP TABLE product_configurator_set;
DROP TABLE product_configurator_template_attribute;
DROP TABLE product_configurator_template_price_attribute;
DROP TABLE product_configurator_template_price;
DROP TABLE product_configurator_template;
DROP TABLE product_esd_attribute;
DROP TABLE product_esd_serial;
DROP TABLE product_esd;
DROP TABLE product_link_attribute;
DROP TABLE product_link_translation;
DROP TABLE product_link;
DROP TABLE product_manufacturer_attribute;
DROP TABLE product_media_attribute;
DROP TABLE product_media_mapping;
DROP TABLE product_media_mapping_rule;
DROP TABLE product_notification;
DROP TABLE product_price_attribute;
DROP TABLE product_similar;
DROP TABLE product_similar_shown_ro;
DROP TABLE product_stream_attribute;
DROP TABLE product_top_seller_ro;
DROP TABLE product_vote;
DROP TABLE product_vote_average;
DROP TABLE shipping_method_attribute;
DROP TABLE shipping_method_category;
DROP TABLE shipping_method_country;
DROP TABLE shipping_method_holiday;
DROP TABLE shipping_method_payment_method;
DROP TABLE shopping_world_component_field;
DROP TABLE shopping_world_component;
DROP TABLE holiday_translation;
DROP TABLE holiday;
DROP TABLE shop_form_attribute;
DROP TABLE shop_form_field_translation;
DROP TABLE shop_form_translation;
DROP TABLE shop_form_field;
DROP TABLE shop_form;
DROP TABLE shop_page_attribute;
DROP TABLE shop_page_group_mapping;
DROP TABLE shop_page_group;
DROP TABLE shop_page;
DROP TABLE statistic_address_pool;
DROP TABLE statistic_current_customer;
DROP TABLE statistic_product_impression;
DROP TABLE statistic_referer;
DROP TABLE statistic_search;
DROP TABLE statistic_visitor;
DROP TABLE s_articles_translations;
DROP TABLE s_billing_template;
DROP TABLE s_campaigns_articles;
DROP TABLE s_campaigns_banner;
DROP TABLE s_campaigns_containers;
DROP TABLE s_campaigns_groups;
DROP TABLE s_campaigns_html;
DROP TABLE s_campaigns_links;
DROP TABLE s_campaigns_logs;
DROP TABLE s_campaigns_mailaddresses;
DROP TABLE s_campaigns_maildata;
DROP TABLE s_campaigns_mailings;
DROP TABLE s_campaigns_positions;
DROP TABLE s_campaigns_sender;
DROP TABLE s_campaigns_templates;
DROP TABLE s_core_acl_privileges;
DROP TABLE s_core_acl_resources;
DROP TABLE s_core_acl_roles;
DROP TABLE s_core_auth_roles;
DROP TABLE s_core_customerpricegroups;
DROP TABLE s_core_detail_states;
DROP TABLE s_core_documents;
DROP TABLE s_core_documents_box;
DROP TABLE s_core_engine_groups;
DROP TABLE s_core_licenses;
DROP TABLE s_core_menu;
DROP TABLE s_core_optin;
DROP TABLE s_core_payment_data;
DROP TABLE s_core_payment_instance;
DROP TABLE s_core_rewrite_urls;
DROP TABLE s_core_rulesets;
DROP TABLE s_core_sessions_backend;
DROP TABLE s_core_subscribes;
DROP TABLE s_core_theme_settings;
DROP TABLE s_core_translations;
DROP TABLE s_core_widgets;
DROP TABLE s_core_widget_views;
DROP TABLE s_crontab;
DROP TABLE s_emarketing_banners_attributes;
DROP TABLE s_emarketing_banners_statistics;
DROP TABLE s_emarketing_banners;
DROP TABLE s_emarketing_lastarticles;
DROP TABLE s_emarketing_partner_attributes;
DROP TABLE s_emarketing_partner;
DROP TABLE s_emarketing_referer;
DROP TABLE s_emarketing_tellafriend;
DROP TABLE s_emarketing_vouchers_attributes;
DROP TABLE s_emarketing_voucher_codes;
DROP TABLE s_emarketing_vouchers;
DROP TABLE s_emotion_attributes;
DROP TABLE s_emotion_categories;
DROP TABLE s_emotion_element_viewports;
DROP TABLE s_emotion_shops;
DROP TABLE s_emotion_templates;
DROP TABLE s_emotion_element_value;
DROP TABLE s_emotion_element;
DROP TABLE s_emotion;
DROP TABLE s_es_backlog;
DROP TABLE s_export_articles;
DROP TABLE s_export_attributes;
DROP TABLE s_export_categories;
DROP TABLE s_export_suppliers;
DROP TABLE s_export;
DROP TABLE s_media_association;
DROP TABLE s_multi_edit_backup;
DROP TABLE s_multi_edit_filter;
DROP TABLE s_multi_edit_queue_articles;
DROP TABLE s_multi_edit_queue;
DROP TABLE s_plugin_recommendations;
DROP TABLE s_plugin_widgets_notes;
DROP TABLE s_search_fields;
DROP TABLE s_search_index;
DROP TABLE s_search_keywords;
DROP TABLE s_search_tables;
DROP TABLE s_user_billingaddress_attributes;
DROP TABLE s_user_billingaddress;
DROP TABLE s_user_shippingaddress_attributes;
DROP TABLE s_user_shippingaddress;
DROP TABLE user_attribute;