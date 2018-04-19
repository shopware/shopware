ALTER TABLE `s_core_sessions`
  ADD COLUMN `lifetime` INT NULL AFTER `expiry`;

DROP INDEX articles_by_category_sort_release ON s_articles;
DROP INDEX articles_by_category_sort_name ON s_articles;
ALTER TABLE s_articles
    RENAME TO product,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE COLUMN supplierID manufacturer_id INT(11) unsigned,
    CHANGE COLUMN shippingtime shipping_time VARCHAR(11),
    CHANGE COLUMN datum created_at DATETIME,
    CHANGE COLUMN taxID tax_id INT(11) unsigned,
    CHANGE COLUMN pseudosales pseudo_sales INT(11) NOT NULL DEFAULT '0',
    CHANGE COLUMN metaTitle meta_title VARCHAR(255),
    CHANGE COLUMN changetime updated_at DATETIME NOT NULL,
    CHANGE COLUMN pricegroupID price_group_id INT(11) unsigned,
    CHANGE COLUMN filtergroupID filter_group_id INT(11) unsigned,
    CHANGE COLUMN laststock last_stock INT(1) NOT NULL,
    DROP pricegroupActive,
    ADD COLUMN main_detail_uuid VARCHAR(42) NOT NULL AFTER tax_id,
    ADD COLUMN tax_uuid VARCHAR(42) NOT NULL AFTER tax_id,
    ADD product_manufacturer_uuid VARCHAR(42) NOT NULL AFTER manufacturer_id,
    ADD filter_group_uuid VARCHAR(42) AFTER filter_group_id
;

CREATE INDEX product_by_category_sort_name ON product (name, id);
CREATE INDEX product_by_category_sort_release ON product (created_at, id);

-- migration
UPDATE product p
SET p.uuid = CONCAT('SWAG-PRODUCT-UUID-', p.id),
    p.product_manufacturer_uuid = CONCAT('SWAG-PRODUCT-MANUFACTURER-UUID-', p.manufacturer_id),
    p.tax_uuid = CONCAT('SWAG-CONFIG-TAX-UUID-', p.tax_id),
    p.main_detail_uuid = CONCAT('SWAG-PRODUCT-DETAIL-UUID-', p.main_detail_id),
    p.filter_group_uuid = CONCAT('SWAG-FILTER-GROUP-UUID-', p.filter_group_id)
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
    CHANGE COLUMN groupID group_id INT(11) unsigned NOT NULL AFTER uuid
;

ALTER TABLE s_article_configurator_option_relations
    RENAME TO product_configurator_option_relation,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE article_id product_id INT(11) unsigned NOT NULL
;


ALTER TABLE s_article_configurator_options
    RENAME TO product_configurator_option,
    ADD uuid VARCHAR(42) NOT NULL AFTER id
;

ALTER TABLE s_article_configurator_options_attributes
    RENAME TO product_configurator_option_attribute,
    ADD uuid VARCHAR(42) NOT NULL,
    MODIFY COLUMN optionID INT(11) unsigned NOT NULL AFTER uuid
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
    ADD related_product_uuid VARCHAR(42) NOT NULL AFTER related_product_id,
    ADD product_uuid VARCHAR(42) NOT NULL AFTER product_id
;

-- migration
UPDATE product_also_bought_ro pabr SET
    pabr.product_uuid = CONCAT('SWAG-PRODUCT-UUID-',pabr.product_id),
    pabr.related_product_uuid = CONCAT('SWAG-PRODUCT-UUID-',pabr.related_product_id)
;

-- clean up task
DELETE a.* FROM s_articles_attributes a WHERE
    a.articleID IS NULL
    OR a.articledetailsID IS NULL
;

ALTER TABLE s_articles_attributes
    RENAME TO product_attribute,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE articledetailsID product_details_id INT(11) unsigned,
    ADD product_detail_uuid VARCHAR(42) NOT NULL AFTER product_details_id
;

-- migration
UPDATE product_attribute pa SET
    pa.uuid                = CONCAT('SWAG-PRODUCT-ATTRIBUTE-UUID-', pa.id),
    pa.product_detail_uuid = CONCAT('SWAG-PRODUCT-DETAIL-UUID-', pa.product_details_id)
;

ALTER TABLE s_articles_avoid_customergroups
    RENAME TO product_avoid_customer_group,
    CHANGE articleID product_id INT(11) NOT NULL,
    CHANGE customergroupID customer_group_id INT(11) NOT NULL,
    ADD customer_group_uuid VARCHAR(42) NOT NULL AFTER customer_group_id,
    ADD product_uuid VARCHAR(42) NOT NULL AFTER product_id
;

-- migration
UPDATE product_avoid_customer_group pac SET
    pac.product_uuid = CONCAT('SWAG-PRODUCT-UUID-', pac.product_id),
    pac.customer_group_uuid = CONCAT('SWAG-CONFIG-CUSTOMER-GROUP-UUID-', pac.customer_group_id)
;

ALTER TABLE s_articles_categories
    RENAME TO product_category,
    CHANGE articleID product_id INT(11) unsigned NOT NULL,
    CHANGE categoryID category_id INT(11) unsigned NOT NULL,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    ADD product_uuid VARCHAR(42) NOT NULL AFTER product_id,
    ADD category_uuid VARCHAR(42) NOT NULL AFTER category_id
;

-- migration
UPDATE product_category pc SET
    pc.uuid          = CONCAT('SWAG-PRODUCT-CATEGORY-UUID-', pc.id),
    pc.product_uuid  = CONCAT('SWAG-PRODUCT-UUID-', pc.product_id),
    pc.category_uuid = CONCAT('SWAG-CATEGORY-UUID-', pc.category_id)
;

ALTER TABLE s_articles_categories_ro
    RENAME TO product_category_ro,
    CHANGE articleID product_id INT(11) unsigned NOT NULL,
    CHANGE categoryID category_id INT(11) unsigned NOT NULL,
    CHANGE parentCategoryID parent_category_id INT(11) unsigned NOT NULL,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    ADD product_uuid VARCHAR(42) NOT NULL AFTER product_id,
    ADD category_uuid VARCHAR(42) NOT NULL AFTER category_id,
    ADD parent_category_uuid VARCHAR(42) NOT NULL AFTER parent_category_id
;

-- migration
UPDATE product_category_ro pcr SET
    pcr.uuid = CONCAT('SWAG-PRODUCT-CATEGORY-RO-UUID-', pcr.id),
    pcr.product_uuid = CONCAT('SWAG-PRODUCT-UUID-', pcr.product_id),
    pcr.category_uuid = CONCAT('SWAG-CATEGORY-UUID-', pcr.category_id),
    pcr.parent_category_uuid = CONCAT('SWAG-CATEGORY-UUID-', pcr.parent_category_id)
;

ALTER TABLE s_articles_categories_seo
    RENAME TO product_category_seo,
    CHANGE article_id product_id INT(11) NOT NULL,
    ADD shop_uuid VARCHAR(42) NOT NULL AFTER shop_id,
    ADD product_uuid VARCHAR(42) NOT NULL AFTER product_id,
    ADD category_uuid VARCHAR(42) NOT NULL AFTER category_id
;

-- migration
UPDATE product_category_seo pcs SET
    pcs.shop_uuid     = CONCAT('SWAG-CONFIG-SHOP-UUID-', pcs.shop_id),
    pcs.product_uuid  = CONCAT('SWAG-PRODUCT-UUID-', pcs.product_id),
    pcs.category_uuid = CONCAT('SWAG-CATEGORY-UUID-', pcs.product_id)
;

DROP INDEX get_similar_articles ON s_articles_details;
DROP INDEX articles_by_category_sort_popularity ON s_articles_details;
DROP INDEX articleID ON s_articles_details;

ALTER TABLE s_articles_details
    RENAME TO product_detail,
    CHANGE articleID product_id INT(11) unsigned NOT NULL DEFAULT '0',
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    ADD product_uuid VARCHAR(42) NOT NULL AFTER product_id,
    CHANGE ordernumber order_number VARCHAR(255) NOT NULL,
    CHANGE suppliernumber supplier_number VARCHAR(255),
    CHANGE additionaltext additional_text VARCHAR(255),
    CHANGE instock stock INT(11),
    CHANGE unitID unit_id INT(11) unsigned,
    CHANGE purchasesteps purchase_steps INT(11) unsigned,
    CHANGE maxpurchase max_purchase INT(11) unsigned,
    CHANGE minpurchase min_purchase INT(11) unsigned NOT NULL DEFAULT '1',
    CHANGE purchaseunit purchase_unit DECIMAL(11,4) unsigned,
    CHANGE referenceunit reference_unit DECIMAL(10,3) unsigned,
    CHANGE packunit pack_unit VARCHAR(255),
    CHANGE releasedate release_date DATETIME,
    CHANGE shippingfree shipping_free INT(1) unsigned NOT NULL DEFAULT '0',
    CHANGE shippingtime shipping_time VARCHAR(11),
    CHANGE purchaseprice purchase_price DOUBLE NOT NULL DEFAULT '0'
;

-- migration
UPDATE product_detail pd SET
    pd.uuid = CONCAT('SWAG-PRODUCT-DETAIL-UUID-', pd.id),
    pd.product_uuid = CONCAT('SWAG-PRODUCT-UUID-', pd.product_id)
;

ALTER TABLE s_articles_downloads
    RENAME TO product_download,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE articleID product_id INT(11) unsigned NOT NULL,
    ADD product_uuid VARCHAR(42) NOT NULL AFTER product_id,
    CHANGE filename file_name VARCHAR(255) NOT NULL
;

-- migration
UPDATE product_download pd SET
    pd.uuid         = CONCAT('SWAG-PRODUCT-DOWNLOAD-UUID-', pd.id),
    pd.product_uuid = CONCAT('SWAG-PRODUCT-UUID-', pd.product_id)
;

ALTER TABLE s_articles_downloads_attributes
    RENAME TO product_download_attribute,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE downloadID download_id INT(11) unsigned AFTER uuid,
    ADD product_download_uuid VARCHAR(42) NOT NULL AFTER download_id
;

-- migration

UPDATE product_download_attribute pda SET
    pda.uuid          = CONCAT('SWAG-PRODUCT-DOWNLOAD-ATTRIBUTE-UUID-', pda.id),
    pda.product_download_uuid = CONCAT('SWAG-PRODUCT-DOWNLOAD-UUID-', pda.download_id)
;

ALTER TABLE s_articles_esd
    RENAME TO product_esd,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE articledetailsID product_detail_id INT(11) NOT NULL DEFAULT '0',
    CHANGE articleID product_id INT(11) NOT NULL DEFAULT '0',
    CHANGE maxdownloads max_downloads INT(11) NOT NULL DEFAULT '0',
    CHANGE datum created_at DATETIME NOT NULL,
    ADD COLUMN product_uuid VARCHAR(42) NOT NULL AFTER product_id,
    ADD COLUMN product_detail_uuid VARCHAR(42) NOT NULL AFTER product_detail_id
;

-- migration
UPDATE product_esd pe SET
    pe.uuid                = CONCAT('SWAG-PRODUCT-ES-UUID-', pe.id),
    pe.product_uuid        = CONCAT('SWAG-PRODUCT-UUID-', pe.product_id),
    pe.product_detail_uuid = CONCAT('SWAG-PRODUCT-DETAIL-UUID-', pe.product_detail_id)
;

ALTER TABLE s_articles_esd_attributes
    RENAME TO product_esd_attribute,
    CHANGE esdID esd_id INT(11),
    ADD COLUMN uuid VARCHAR(42) NOT NULL AFTER id,
    ADD COLUMN product_esd_uuid VARCHAR(42) NOT NULL AFTER esd_id
;

-- migration
UPDATE product_esd_attribute pea SET
    pea.uuid = CONCAT('SWAG-PRODUCT-ES-ATTRIBUTE-UUID-', pea.id),
    pea.product_esd_uuid = CONCAT('SWAG-PRODUCT-ES-UUID-', pea.esd_id)
;

ALTER TABLE s_articles_esd_serials
    RENAME TO product_esd_serial,
    CHANGE COLUMN esdID esd_id INT(11) NOT NULL DEFAULT '0' AFTER id,
    CHANGE COLUMN serialnumber serial_number VARCHAR(255) NOT NULL,
    ADD COLUMN uuid VARCHAR(42) NOT NULL AFTER id,
    ADD COLUMN product_esd_uuid VARCHAR(42) NOT NULL AFTER esd_id
;

DELETE p.* FROM product_esd_serial p WHERE p.esd_id = 1;

-- migration
UPDATE product_esd_serial pes SET
    pes.uuid = CONCAT('SWAG-PRODUCT-ES-SERIAL-UUID-', pes.id),
    pes.product_esd_uuid = CONCAT('SWAG-PRODUCT-ES-UUID-', pes.esd_id)
;

DROP INDEX article_images_query ON s_articles_img;
DROP INDEX article_detail_id ON s_articles_img;
DROP INDEX article_cover_image_query ON s_articles_img;
ALTER TABLE s_articles_img
    RENAME TO product_image,
    ADD COLUMN uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE COLUMN articleID product_id INT(11),
    CHANGE COLUMN article_detail_id product_detail_id INT(10) unsigned,
    ADD COLUMN product_uuid VARCHAR(42) NOT NULL AFTER product_id,
    ADD COLUMN product_detail_uuid VARCHAR(42) NOT NULL AFTER product_detail_id
;

-- clean up task
DELETE p.* FROM product_image p WHERE
    p.product_id IS NULL
;

-- migration
UPDATE product_image p SET
    p.uuid                = CONCAT('SWAG-PRODUCT-IMAGE-UUID-', p.id),
    p.product_uuid        = CONCAT('SWAG-PRODUCT-UUID-', p.product_id),
    p.product_detail_uuid = CONCAT('SWAG-PRODUCT-DETAIL-UUID-', IFNULL(p.product_detail_id, ''))
;

ALTER TABLE s_articles_img_attributes
    RENAME TO product_image_attribute,
    ADD COLUMN uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE COLUMN imageID image_id INT(11),
    ADD COLUMN product_image_uuid VARCHAR(42) NOT NULL AFTER image_id
;

-- migration
UPDATE product_image_attribute p SET
    p.uuid = CONCAT('SWAG-PRODUCT-IMAGE-ATTRIBUTE-UUID-', p.id),
    p.product_image_uuid = CONCAT('SWAG-PRODUCT-IMAGE-UUID-', p.image_id)
;

ALTER TABLE s_article_img_mappings
    RENAME TO product_image_mapping,
    ADD COLUMN uuid VARCHAR(42) NOT NULL AFTER id,
    ADD COLUMN product_image_uuid VARCHAR(42) NOT NULL AFTER image_id
;

-- migration
UPDATE product_image_mapping p SET
    p.uuid = CONCAT('SWAG-PRODUCT-IMAGE-MAPPING-UUID-', p.id),
    p.product_image_uuid = CONCAT('SWAG-PRODUCT-IMAGE-UUID-', p.image_id)
;

-- TODO option_id ???
ALTER TABLE s_article_img_mapping_rules
    RENAME TO product_image_mapping_rule,
    ADD COLUMN uuid VARCHAR(42) NOT NULL AFTER id,
    ADD COLUMN product_image_mapping_uuid VARCHAR(42) NOT NULL AFTER mapping_id
;

-- migration
UPDATE product_image_mapping_rule p SET
    p.uuid = CONCAT('SWAG-PRODUCT-IMAGE-MAPPING-RULE-UUID-', p.id),
    p.product_image_mapping_uuid = CONCAT('SWAG-PRODUCT-IMAGE-MAPPING-UUID-', p.mapping_id)
;

ALTER TABLE s_articles_information
    RENAME TO product_information,
    ADD COLUMN uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE COLUMN articleID product_id INT(11) NOT NULL DEFAULT '0',
    ADD COLUMN product_uuid VARCHAR(42) NOT NULL AFTER product_id
;

-- migration
UPDATE product_information p SET
    p.uuid = CONCAT('SWAG-PRODUCT-INFORMATION-UUID-', p.id),
    p.product_uuid = CONCAT('SWAG-PRODUCT-UUID-', p.product_id)
;


ALTER TABLE s_articles_information_attributes
    RENAME TO product_information_attribute,
    CHANGE COLUMN informationID information_id INT(11),
    ADD COLUMN uuid VARCHAR(42) NOT NULL AFTER id,
    ADD COLUMN product_information_uuid VARCHAR(42) NOT NULL AFTER information_id
;

-- migration
UPDATE product_information_attribute p SET
    p.uuid             = CONCAT('SWAG-PRODUCT-INFORMATION-ATTRIBUTE-UUID-', p.id),
    p.product_information_uuid = CONCAT('SWAG-PRODUCT-INFORMATION-UUID-', p.information_id)
;

ALTER TABLE s_articles_notification
    RENAME TO product_notification,
    ADD COLUMN uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE COLUMN ordernumber order_number VARCHAR(255) NOT NULL,
    CHANGE COLUMN `date` created_at DATETIME NOT NULL,
    CHANGE COLUMN shopLink shop_link VARCHAR(255) NOT NULL
;

-- migration
UPDATE product_notification p SET
    p.uuid = CONCAT('SWAG-PRODUCT-NOTIFICATION-UUID-', p.id)
;

ALTER TABLE s_articles_prices
    RENAME TO product_price,
    ADD COLUMN uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE COLUMN articledetailsID product_detail_id INT(11) NOT NULL DEFAULT '0',
    CHANGE COLUMN articleID product_id INT(11) NOT NULL DEFAULT '0',
    ADD COLUMN product_uuid VARCHAR(42) NOT NULL AFTER product_id,
    ADD COLUMN product_detail_uuid VARCHAR(42) NOT NULL AFTER product_detail_id
;

-- migration
UPDATE product_price p SET
    p.uuid = CONCAT('SWAG-PRODUCT-PRICE-UUID-', p.id),
    p.product_uuid = CONCAT('SWAG-PRODUCT-UUID-', p.product_id),
    p.product_detail_uuid = CONCAT('SWAG-PRODUCT-DETAIL-UUID-', p.product_detail_id)
;

ALTER TABLE s_articles_prices_attributes
    RENAME TO product_price_attribute,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE priceID price_id INT(11) unsigned,
    ADD product_price_uuid VARCHAR(42) NOT NULL AFTER price_id
;

-- migration
UPDATE product_price_attribute p SET
    p.uuid       = CONCAT('SWAG-PRODUCT-PRICE-ATTRIBUTE-UUID-', p.id),
    p.product_price_uuid = CONCAT('SWAG-PRODUCT-PRICE-UUID-', p.price_id)
;

ALTER TABLE s_articles_relationships
    RENAME TO product_relationship,
    ADD uuid VARCHAR(42) NOT NULL,
    CHANGE relatedarticle related_product VARCHAR(30) NOT NULL,
    CHANGE articleID product_id INT(30) NOT NULL,
    ADD product_uuid VARCHAR(42) NOT NULL AFTER product_id,
    ADD related_product_uuid VARCHAR(42) NOT NULL AFTER related_product
;

-- migration
UPDATE product_relationship p SET
    p.uuid                 = CONCAT('SWAG-PRODUCT-RELATIONSHIP-UUID-', p.id),
    p.product_uuid         = CONCAT('SWAG-PRODUCT-UUID-', p.product_id),
    p.related_product_uuid = CONCAT('SWAG-PRODUCT-UUID-', p.related_product)
;

ALTER TABLE s_articles_similar
    RENAME TO product_similar,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE relatedarticle related_product VARCHAR(255) NOT NULL,
    CHANGE articleID product_id INT(30) NOT NULL,
    ADD product_uuid VARCHAR(42) NOT NULL AFTER product_id,
    ADD related_product_uuid VARCHAR(42) NOT NULL AFTER related_product
;

UPDATE product_similar p SET
    p.uuid                 = CONCAT('SWAG-PRODUCT-RELATIONSHIP-UUID-', p.id),
    p.product_uuid         = CONCAT('SWAG-PRODUCT-UUID-', p.product_id),
    p.related_product_uuid = CONCAT('SWAG-PRODUCT-UUID-', p.related_product)
;

ALTER TABLE s_articles_similar_shown_ro
    RENAME TO product_similar_shown_ro,
    CHANGE related_article_id related_product_id INT(11) NOT NULL,
    CHANGE article_id product_id INT(11) unsigned NOT NULL,
    CHANGE init_date created_at DATETIME NOT NULL,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    ADD product_uuid VARCHAR(42) NOT NULL AFTER product_id,
    ADD related_product_uuid VARCHAR(42) NOT NULL AFTER related_product_id
;

UPDATE product_similar_shown_ro p SET
    p.uuid                 = CONCAT('SWAG-PRODUCT-RELATIONSHIP-UUID-', p.id),
    p.product_uuid         = CONCAT('SWAG-PRODUCT-UUID-', p.product_id),
    p.related_product_uuid = CONCAT('SWAG-PRODUCT-UUID-', p.related_product_uuid)
;

ALTER TABLE s_articles_supplier
    RENAME TO product_manufacturer,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE `changed` updated_at DATETIME NOT NULL
;

-- migration
UPDATE product_manufacturer p SET
    p.uuid = CONCAT('SWAG-PRODUCT-MANUFACTURER-UUID-', p.id)
;

ALTER TABLE s_articles_supplier_attributes
    RENAME TO product_manufacturer_attribute,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE supplierID manufacturer_id INT(11) AFTER uuid,
    ADD product_manufacturer_uuid VARCHAR(42) NOT NULL AFTER manufacturer_id
;

-- migration
UPDATE product_manufacturer_attribute p SET
    p.uuid             = CONCAT('SWAG-PRODUCT-MANUFACTURER-ATTRIBUTE-UUID-', p.id),
    p.product_manufacturer_uuid = CONCAT('SWAG-PRODUCT-MANUFACTURER-UUID-', p.manufacturer_id)
;

ALTER TABLE s_articles_top_seller_ro
    RENAME TO product_top_seller_ro,
    CHANGE article_id product_id INT(11) unsigned NOT NULL,
    CHANGE last_cleared cleared_at DATETIME,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    ADD product_uuid VARCHAR(42) NOT NULL AFTER product_id
;

-- migration
UPDATE product_top_seller_ro p SET
    p.uuid         = CONCAT('SWAG-PRODUCT-TOP-SELLER-RO-UUID-', p.id),
    p.product_uuid = CONCAT('SWAG-PRODUCT-UUID-', p.product_id);
;

ALTER TABLE s_articles_translations
    RENAME TO product_translation,
    CHANGE articleID product_id INT(11) NOT NULL,
    CHANGE languageID language_id INT(11) NOT NULL,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    ADD product_uuid VARCHAR(42) NOT NULL AFTER product_id,
    ADD language_uuid VARCHAR(42) NOT NULL AFTER language_id
;

-- migration
UPDATE product_translation p SET
    p.uuid          = CONCAT('SWAG-PRODUCT-TRANSLATION-UUID-', p.id),
    p.product_uuid  = CONCAT('SWAG-PRODUCT-UUID-', p.product_id),
    p.language_uuid = CONCAT('SWAG-CONFIG-LOCALES-UUID-', p.language_uuid)
;

ALTER TABLE s_articles_vote
    RENAME TO product_vote,
    CHANGE articleID product_id INT(11) NOT NULL,
    CHANGE datum created_at DATETIME,
    CHANGE answer_date answer_at DATETIME,
    ADD uuid VARCHAR(42) NOT NULL,
    ADD product_uuid VARCHAR(42) NOT NULL,
    ADD shop_uuid VARCHAR(42) NOT NULL
;

-- --------------------------------------
-- --------------------------------------
-- --------------------------------------
-- --------------------------------------
-- --------------------------------------
-- non product related tables
-- --------------------------------------
-- --------------------------------------
-- --------------------------------------
-- --------------------------------------

ALTER TABlE s_core_customergroups
    ADD COLUMN uuid VARCHAR(42) NOT NULL AFTER id
;

-- migration
UPDATE s_core_customergroups s SET
    s.uuid = CONCAT('SWAG-CONFIG-CUSTOMER-GROUP-UUID-', s.id)
;

ALTER TABlE s_core_shops
    ADD COLUMN uuid VARCHAR(42) NOT NULL AFTER id
;

-- migration
UPDATE s_core_shops s SET
    s.uuid = CONCAT('SWAG-CONFIG-SHOP-UUID-', s.id)
;

ALTER TABlE s_core_tax
    ADD COLUMN uuid VARCHAR(42) NOT NULL AFTER id
;

-- migration
UPDATE s_core_tax s SET
    s.uuid = CONCAT('SWAG-CONFIG-TAX-UUID-', s.id)
;

ALTER TABLE s_categories
    RENAME TO category,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    DROP `left`,
    DROP `right`,
    CHANGE mediaID media_id INT(11) unsigned,
    ADD media_uuid VARCHAR(42) NOT NULL AFTER media_id,
    CHANGE hidetop hide_top INT(1) NOT NULL,
    CHANGE hidefilter hide_filter INT(1) NOT NULL,
    CHANGE cmstext cms_description MEDIUMTEXT,
    CHANGE cmsheadline cms_headline VARCHAR(255),
    CHANGE metadescription meta_description MEDIUMTEXT,
    CHANGE metakeywords meta_keywords MEDIUMTEXT,
    CHANGE changed changed_at DATETIME NOT NULL,
    MODIFY COLUMN meta_title VARCHAR(255) AFTER meta_keywords
;

-- migration
UPDATE category c SET
    c.uuid = CONCAT('SWAG-CATEGORY-UUID-', c.id)
;

ALTER TABLE s_categories_attributes
    RENAME TO category_attribute,
    CHANGE categoryID category_id INT(11) unsigned,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    ADD category_uuid VARCHAR(42) NOT NULL AFTER category_id
;

-- migration
UPDATE category_attribute c SET
    c.uuid = CONCAT('SWAG-CATEGORY-ATTRIBUTE-UUID-', c.id),
    c.category_uuid = CONCAT('SWAG-CATEGORY-UUID-', c.category_id)
;

ALTER TABLE s_categories_avoid_customergroups
    RENAME TO category_avoid_customer_group,
    CHANGE categoryID category_id INT(11),
    CHANGE customergroupID customer_group_id INT(11),
    ADD category_uuid VARCHAR(42) NOT NULL AFTER category_id,
    ADD customer_group_uuid VARCHAR(42) NOT NULL AFTER customer_group_id
;

-- migration
UPDATE category_avoid_customer_group c SET
    c.customer_group_uuid = CONCAT('SWAG-CONFIG-CUSTOMER-GROUP-UUID-', c.customer_group_id),
    c.category_uuid = CONCAT('SWAG-CATEGORY-UUID-', c.category_id)
;


ALTER TABLE s_filter
    RENAME filter,
    ADD COLUMN uuid VARCHAR(42) NOT NULL after id
;

-- migration
UPDATE filter f SET
    f.uuid = CONCAT('SWAG-FILTER-UUID-', f.id)
;

ALTER TABLE s_filter_attributes
    RENAME filter_attribute,
    ADD COLUMN uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE filterID filter_id INT(11),
    ADD COLUMN filter_uuid VARCHAR(42) NOT NULL AFTER filter_id
;

-- migration
UPDATE filter_attribute f SET
    f.uuid = CONCAT('SWAG-FILTER-ATTRIBUTE-UUID-', f.id),
    f.filter_uuid = CONCAT('SWAG-FILTER-UUID-', f.filter_id)
;



ALTER TABLE s_filter_values
    RENAME filter_value,
    ADD uuid VARCHAR(42) NOT NULL after id,
    CHANGE optionID option_id INT(11) NOT NULL,
    ADD option_uuid VARCHAR(42) NOT NULL AFTER option_id,
    ADD media_uuid VARCHAR(42) AFTER media_id
;

-- migration
UPDATE filter_value f SET
    f.uuid = CONCAT('SWAG-FILTER-VALUES-UUID-', f.id),
    f.option_uuid = CONCAT('SWAG-FILTER-OPTION-UUID-', f.id),
    f.media_uuid = CONCAT('SWAG-MEDIA-UUID-', f.media_id)
;

ALTER TABLE s_filter_values_attributes
    RENAME filter_value_attribute,
    ADD COLUMN uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE valueID value_id INT(11),
    ADD COLUMN filter_value_uuid VARCHAR(42) NOT NULL AFTER value_id
;

-- migration
UPDATE filter_value_attribute f SET
    f.uuid = CONCAT('SWAG-FILTER-VALUE-ATTRIBUTE-UUID-', f.id),
    f.filter_value_uuid = CONCAT('SWAG-FILTER-VALUE-UUID-', f.value_id)
;

ALTER TABLE s_filter_options
    RENAME filter_option,
    ADD uuid VARCHAR(42) NOT NULL after id
;

-- migration
UPDATE filter_option f SET
    f.uuid = CONCAT('SWAG-FILTER-OPTION-UUID-', f.id)
;

ALTER TABLE s_filter_options_attributes
    RENAME filter_option_attribute,
    ADD COLUMN uuid VARCHAR(42) NOT NULL AFTER id,
    CHANGE optionID option_id INT(11),
    ADD COLUMN filter_option_uuid VARCHAR(42) NOT NULL AFTER option_id
;

-- migration
UPDATE filter_option_attribute f SET
    f.uuid = CONCAT('SWAG-FILTER-OPTION-ATTRIBUTE-UUID-', f.id),
    f.filter_option_uuid = CONCAT('SWAG-FILTER-OPTION-UUID-', f.option_id)
;

ALTER TABLE s_filter_articles
    RENAME filter_product,
    CHANGE articleID product_id INT(10) unsigned NOT NULL,
    CHANGE valueID value_id INT(10) unsigned NOT NULL,
    ADD product_uuid VARCHAR(42) NOT NULL AFTER product_id,
    ADD filter_value_uuid VARCHAR(42) NOT NULL AFTER value_id
;

-- migration
UPDATE filter_product f SET
    f.product_uuid = CONCAT('SWAG-PRODUCT-UUID-', f.product_id),
    f.filter_value_uuid = CONCAT('SWAG-FILTER-VALUE-UUID-', f.value_id)
;

ALTER TABLE s_filter_relations
    RENAME filter_relation,
    CHANGE groupID group_id INT(11) NOT NULL,
    CHANGE optionID option_id INT(11) NOT NULL,
    ADD uuid VARCHAR(42) NOT NULL AFTER id,
    ADD filter_group_uuid VARCHAR(42) NOT NULL AFTER group_id,
    ADD filter_option_uuid VARCHAR(42) NOT NULL AFTER option_id
;

-- migration
UPDATE filter_relation f SET
    f.uuid = CONCAT('SWAG-FILTER-RELATION-UUID-', f.id),
    f.filter_group_uuid = CONCAT('SWAG-FILTER-GROUP-UUID-', f.group_id),
    f.filter_option_uuid = CONCAT('SWAG-FILTER-OPTION-UUID-', f.option_id)
;


-- --------------------------------------
-- --------------------------------------
-- --------------------------------------
-- foreign keys yeah
-- --------------------------------------
-- --------------------------------------
-- --------------------------------------
ALTER TABLE product_attribute
    DROP FOREIGN KEY product_attribute_ibfk_1,
    DROP FOREIGN KEY product_attribute_ibfk_2
;

CREATE UNIQUE INDEX `ui_category.uuid` ON category (uuid);
CREATE UNIQUE INDEX `ui_filter.uuid` ON filter (uuid);
CREATE UNIQUE INDEX `ui_filter_value.uuid` ON filter_value (uuid);
CREATE UNIQUE INDEX `ui_filter_option.uuid` ON filter_option (uuid);
CREATE UNIQUE INDEX `ui_product.uuid` ON product (uuid);
CREATE UNIQUE INDEX `ui_product_detail.uuid` ON product_detail (uuid);
CREATE UNIQUE INDEX `ui_product_download.uuid` ON product_download (uuid);
CREATE UNIQUE INDEX `ui_product_esd.uuid` ON product_esd (uuid);
CREATE UNIQUE INDEX `ui_product_image.uuid` ON product_image (uuid);
CREATE UNIQUE INDEX `ui_product_image_mapping.uuid` ON product_image_mapping (uuid);
CREATE UNIQUE INDEX `ui_product_information.uuid` ON product_information (uuid);
CREATE UNIQUE INDEX `ui_product_manufacturer.uuid` ON product_manufacturer (uuid);
CREATE UNIQUE INDEX `ui_product_price.uuid` ON product_price (uuid);
CREATE UNIQUE INDEX `ui_s_core_customergroups.uuid` ON s_core_customergroups (uuid);
CREATE UNIQUE INDEX `ui_s_core_shops.uuid` ON s_core_shops (uuid);
CREATE UNIQUE INDEX `ui_s_core_tax.uuid` ON s_core_tax (uuid);


ALTER TABLE product_attribute
    ADD CONSTRAINT `fk_product_attribute.product_detail_uuid`
    FOREIGN KEY (product_detail_uuid) REFERENCES product_detail (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE product_also_bought_ro
    ADD CONSTRAINT `fk_product_also_bought_ro.product_uuid`
    FOREIGN KEY (product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_product_also_bought_ro.related_product_uuid`
    FOREIGN KEY (related_product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE `product`
    CHANGE COLUMN `name` `name` VARCHAR(255) NOT NULL AFTER `product_manufacturer_uuid`
;

ALTER TABLE product_avoid_customer_group
    ADD CONSTRAINT `fk_product_avoid_customer_group.product_uuid`
    FOREIGN KEY (product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE,

    ADD CONSTRAINT `fk_product_avoid_customer_group.customer_group_uuid`
    FOREIGN KEY (customer_group_uuid) REFERENCES s_core_customergroups (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE product_category
    ADD CONSTRAINT `fk_product_category.product_uuid`
    FOREIGN KEY (product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE,

    ADD CONSTRAINT `fk_product_category.category_uuid`
    FOREIGN KEY (category_uuid) REFERENCES category (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE product_category_ro
    ADD CONSTRAINT `fk_product_category_ro.product_uuid`
    FOREIGN KEY (product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE,

    ADD CONSTRAINT `fk_product_category_ro.category_uuid`
    FOREIGN KEY (category_uuid) REFERENCES category (uuid) ON DELETE CASCADE ON UPDATE CASCADE,

    ADD CONSTRAINT `fk_product_category_ro.parent_category_uuid`
    FOREIGN KEY (parent_category_uuid) REFERENCES category (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE product_category_seo
    ADD CONSTRAINT `fk_product_category_seo.shop_uuid`
    FOREIGN KEY (shop_uuid) REFERENCES s_core_shops (uuid) ON DELETE CASCADE ON UPDATE CASCADE,

    ADD CONSTRAINT `fk_product_category_seo.product_uuid`
    FOREIGN KEY (product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE,

    ADD CONSTRAINT `fk_product_category_seo.category_uuid`
    FOREIGN KEY (category_uuid) REFERENCES category (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE product_detail
    ADD CONSTRAINT `fk_product_detail.product_uuid`
    FOREIGN KEY (product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE product_download
    ADD CONSTRAINT `fk_product_download.product_uuid`
    FOREIGN KEY (product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE product_download_attribute
    ADD CONSTRAINT `fk_product_download_attribute.product_uuid`
    FOREIGN KEY (product_download_uuid) REFERENCES product_download (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE product_esd
    ADD CONSTRAINT `fk_product_esd.product_uuid`
    FOREIGN KEY (product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE product_esd_attribute
    ADD CONSTRAINT `fk_product_esd_attribute.product_uuid`
    FOREIGN KEY (product_esd_uuid) REFERENCES product_esd (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE product_esd_serial
    ADD CONSTRAINT `fk_product_esd_serial.product_uuid`
    FOREIGN KEY (product_esd_uuid) REFERENCES product_esd (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE product_image
    ADD CONSTRAINT `fk_product_image.product_uuid`
    FOREIGN KEY (product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE product_image_attribute
    ADD CONSTRAINT `fk_product_image_attribute.product_uuid`
    FOREIGN KEY (product_image_uuid) REFERENCES product_image (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE product_image_mapping
    ADD CONSTRAINT `fk_product_image_mapping.product_uuid`
    FOREIGN KEY (product_image_uuid) REFERENCES product_image (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE product_information
    ADD CONSTRAINT `fk_product_information.product_uuid`
    FOREIGN KEY (product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE product_information_attribute
    ADD CONSTRAINT `fk_product_information_attribute.product_uuid`
    FOREIGN KEY (product_information_uuid) REFERENCES product_information (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE product_manufacturer_attribute
    ADD CONSTRAINT `fk_product_manufacturer_attribute.product_uuid`
    FOREIGN KEY (product_manufacturer_uuid) REFERENCES product_manufacturer (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE product_price
    ADD CONSTRAINT `fk_product_price.product_uuid`
    FOREIGN KEY (product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE,

    ADD CONSTRAINT `fk_product_price.product_detail_uuid`
    FOREIGN KEY (product_detail_uuid) REFERENCES product_detail (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE product_price_attribute
    ADD CONSTRAINT `fk_product_price_attribute.product_uuid`
    FOREIGN KEY (product_price_uuid) REFERENCES product_price (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE product_relationship
    ADD CONSTRAINT `fk_product_relationship.product_uuid`
    FOREIGN KEY (product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE,

    ADD CONSTRAINT `fk_product_relationship.related_product_uuid`
    FOREIGN KEY (related_product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;


ALTER TABLE product_similar
    ADD CONSTRAINT `fk_product_similar.product_uuid`
    FOREIGN KEY (product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE,

    ADD CONSTRAINT `fk_product_similar.related_product_uuid`
    FOREIGN KEY (related_product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE product_similar_shown_ro
    ADD CONSTRAINT `fk_product_similar_shown_ro.product_uuid`
    FOREIGN KEY (product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE,

    ADD CONSTRAINT `fk_product_similar_shown_ro.related_product_uuid`
    FOREIGN KEY (related_product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE
;

ALTER TABLE product_top_seller_ro
    ADD CONSTRAINT `fk_product_top_seller_ro.product_uuid`
    FOREIGN KEY (product_uuid) REFERENCES product (uuid) ON DELETE CASCADE ON UPDATE CASCADE
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

INSERT IGNORE INTO `s_core_templates` (`id`, `template`, `name`, `description`, `author`, `license`, `esi`, `style_support`, `emotion`, `version`, `plugin_id`, `parent_id`) VALUES
(11,	'Responsive',	'__theme_name__',	'__theme_description__',	'__author__',	'__license__',	1,	1,	1,	3,	NULL,	NULL);
