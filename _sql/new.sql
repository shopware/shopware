CREATE TABLE `product_detail_translation` (
  `product_detail_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `additional_text` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb4_unicode_ci',
  `pack_unit` VARCHAR(255) NULL DEFAULT NULL DEFAULT '' COLLATE 'utf8mb4_unicode_ci',
  PRIMARY KEY (`product_detail_uuid`, `language_uuid`),
  INDEX `fk_product_detail_translation.language_uuid` (`language_uuid`)
#   CONSTRAINT `fk_product_detail_translation.language_uuid` FOREIGN KEY (`language_uuid`) REFERENCES `s_core_shops` (`uuid`) ON UPDATE CASCADE ON DELETE CASCADE,
#   CONSTRAINT `fk_product_detail_translation.product_detail_uuid` FOREIGN KEY (`product_detail_uuid`) REFERENCES `product_detail` (`uuid`) ON UPDATE CASCADE ON DELETE CASCADE
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

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
