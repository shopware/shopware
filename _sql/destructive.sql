ALTER TABLE product
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
    ADD PRIMARY KEY (`uuid`);
;

UPDATE product_detail SET
  uuid = order_number
;

ALTER TABLE product_detail
    DROP `product_id`,
    DROP `order_number`
;

ALTER TABLE product_attribute
    DROP COLUMN articleID
;

ALTER TABLE product_translation
  DROP `id`,
  DROP `uuid`,
  DROP `product_id`,
  DROP `language_id`,
  DROP `description_clear`,
  ADD PRIMARY KEY (`product_uuid`, `language_uuid`);

ALTER TABLE `product_category`
  DROP id,
  DROP uuid,
  DROP product_id,
  DROP category_id,
  ADD PRIMARY KEY (`product_uuid`, `category_uuid`)
;

UPDATE product_price SET `to` = NULL WHERE `to` = 'beliebig';

ALTER TABLE product_price
    CHANGE `to` `to` INT(11) NULL DEFAULT NULL,
--     DROP id,
    DROP FOREIGN KEY `fk_product_price.product_uuid`,
    DROP product_uuid,
    DROP product_detail_id
;

ALTER TABLE `product_category_ro`
    DROP INDEX articleID,
    DROP INDEX articleID_2,
    DROP INDEX categoryID,
    DROP INDEX categoryID_2,
    DROP INDEX category_id_by_article_id,
    DROP INDEX elastic_search,
    DROP COLUMN id,
    DROP COLUMN category_id,
    DROP COLUMN parent_category_id,
    DROP COLUMN product_id,
    DROP COLUMN uuid
;

ALTER TABLE product_category_ro
    ADD PRIMARY KEY (product_uuid, category_uuid, parent_category_uuid);


# ALTER TABLE category_attribute
#     DROP FOREIGN KEY category_attribute_ibfk_1,
#     DROP id,
#     DROP category_id
# ;

# ALTER TABLE category
#     DROP COLUMN id,
#     DROP COLUMN parent,
#     DROP COLUMN level,
#     DROP COLUMN media_id
# ;

DROP TABLE IF EXISTS `s_core_sessions`;

ALTER TABLE media
  DROP `extension`,
  DROP `width`,
  DROP `height`
;