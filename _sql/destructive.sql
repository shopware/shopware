ALTER TABLE product
    DROP `manufacturer_id`,
    DROP `tax_id`,
    DROP `main_detail_id`,
    DROP `filter_group_id`
;

ALTER TABLE product_attribute
    DROP COLUMN articleID
;

