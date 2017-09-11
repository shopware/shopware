CREATE TABLE `album_translation` (
  `album_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `name` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

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

CREATE TABLE `area_translation` (
  `area_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `name` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

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

CREATE TABLE `area_country_translation` (
  `area_country_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `name` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

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

CREATE TABLE `area_country_state_translation` (
  `area_country_state_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `name` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

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

CREATE TABLE `blog_tag_translation` (
  `blog_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `name` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

INSERT INTO blog_tag_translation (language_uuid, blog_uuid, name)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            b.uuid                                    AS blog_uuid,
            b.name                                    AS name
        FROM
            blog_tag b
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

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

CREATE TABLE `currency_translation` (
  `currency_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `currency` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `name` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

INSERT INTO currency_translation (language_uuid, currency_uuid, currency, name)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            c.uuid                                    AS category_uuid,
            c.currency                                AS currency,
            c.name                                    AS name
        FROM
            currency c
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

CREATE TABLE `customer_group_translation` (
  `customer_group_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `description` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

INSERT INTO customer_group_translation (language_uuid, customer_group_uuid, description)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            c.uuid                                    AS customer_group_uuid,
            c.description                             AS description
        FROM
            customer_group c
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

CREATE TABLE `filter_translation` (
  `filter_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `name` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

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

CREATE TABLE `filter_option_translation` (
  `filter_option_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `name` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

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

CREATE TABLE `filter_value_translation` (
  `filter_value_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `value` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

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

CREATE TABLE `holiday_translation` (
  `holiday_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `name` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

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

CREATE TABLE `listing_facet_translation` (
  `listing_facet_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `name` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

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

CREATE TABLE `listing_sorting_translation` (
  `listing_sorting_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `label` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

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

CREATE TABLE `locale_translation` (
  `locale_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `territory` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

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

CREATE TABLE `mail_translation` (
  `mail_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `subject` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `content` MEDIUMTEXT NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `content_html` MEDIUMTEXT NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

INSERT INTO mail_translation (language_uuid, mail_uuid, subject, content, content_html)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            m.uuid                                    AS mail_uuid,
            m.subject                                 AS subject,
            m.content                                 AS content,
            m.content_html                            AS content_html
        FROM
            mail m
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

CREATE TABLE `media_translation` (
  `media_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `name` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `description` TEXT NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

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

CREATE TABLE `order_state_translation` (
  `order_state_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `description` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

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

CREATE TABLE `payment_method_translation` (
  `payment_method_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `description` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `additional_description` MEDIUMTEXT NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

INSERT INTO payment_method_translation (language_uuid, payment_method_uuid, description, additional_description)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            p.uuid                                    AS payment_method_uuid,
            p.description                             AS description,
            p.additional_description                  AS additional_description
        FROM
            payment_method p
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

CREATE TABLE `price_group_translation` (
  `price_group_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `description` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

INSERT INTO price_group_translation (language_uuid, price_group_uuid, description)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            p.uuid                                    AS price_group_uuid,
            p.description                             AS description
        FROM
            price_group p
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

CREATE TABLE `product_attachment_translation` (
  `product_attachment_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `description` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

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


CREATE TABLE `product_configurator_group_translation` (
  `product_configurator_group_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `name` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `description` TEXT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

INSERT INTO product_configurator_group_translation (language_uuid, product_configurator_group_uuid, name, description)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            p.uuid                                    AS product_configurator_group_uuid,
            p.name                                    AS name,
            p.description                                    AS description
        FROM
            product_configurator_group p
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

CREATE TABLE `product_configurator_option_translation` (
  `product_configurator_option_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `name` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

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

CREATE TABLE `product_detail_translation` (
  `product_detail_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `additional_text` VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb4_unicode_ci',
  `pack_unit` VARCHAR(255) NULL DEFAULT NULL DEFAULT '' COLLATE 'utf8mb4_unicode_ci',
  PRIMARY KEY (`product_detail_uuid`, `language_uuid`),
  INDEX `fk_product_detail_translation.language_uuid` (`language_uuid`)
--   CONSTRAINT `fk_product_detail_translation.language_uuid` FOREIGN KEY (`language_uuid`) REFERENCES `shop` (`uuid`) ON UPDATE CASCADE ON DELETE CASCADE,
--   CONSTRAINT `fk_product_detail_translation.product_detail_uuid` FOREIGN KEY (`product_detail_uuid`) REFERENCES `product_detail` (`uuid`) ON UPDATE CASCADE ON DELETE CASCADE
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

CREATE TABLE `product_link_translation` (
  `product_link_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `description` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

INSERT INTO product_link_translation (language_uuid, product_link_uuid, description)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            p.uuid                                    AS product_link_uuid,
            p.description                             AS description
        FROM
            product_link p
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

CREATE TABLE `product_manufacturer_translation` (
  `product_manufacturer_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `description` LONGTEXT NULL COLLATE 'utf8mb4_unicode_ci',
  `meta_title` VARCHAR(255) NULL COLLATE 'utf8mb4_unicode_ci',
  `meta_description` VARCHAR(255) NULL COLLATE 'utf8mb4_unicode_ci',
  `meta_keywords` VARCHAR(255) NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

INSERT INTO product_manufacturer_translation (language_uuid, product_manufacturer_uuid, description, meta_title, meta_description, meta_keywords)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            p.uuid                                    AS product_link_uuid,
            p.description                             AS description,
            p.meta_title                              AS meta_title,
            p.meta_description                        AS meta_description,
            p.meta_keywords                           AS meta_keywords
        FROM
            product_manufacturer p
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

CREATE TABLE `product_media_translation` (
  `product_media_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `description` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

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

CREATE TABLE `statistic_search_translation` (
  `statistic_search_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `term` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

INSERT INTO statistic_search_translation (language_uuid, statistic_search_uuid, term)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            ss.uuid                                   AS statistic_search_uuid,
            ss.term                                   AS term
        FROM
            statistic_search ss
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

CREATE TABLE `tax_area_rule_translation` (
  `tax_area_rule_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `name` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

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

CREATE TABLE `unit_translation` (
  `unit_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `language_uuid` VARCHAR(42) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `unit` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `description` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci'
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
;

INSERT INTO unit_translation (language_uuid, unit_uuid, unit, description)
    (
        SELECT
            s.uuid                                    AS language_uuid,
            u.uuid                                    AS unit_uuid,
            u.unit                                    AS unit,
            u.description                             AS description
        FROM
            unit u
        JOIN
            shop s ON s.fallback_locale_uuid IS NULL
    );

