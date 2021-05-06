DROP TABLE IF EXISTS `_dpkc_1n_relation1`;
DROP TABLE IF EXISTS `_dpkc_1n_relation2`;
DROP TABLE IF EXISTS `_dpkc_1n_relation3`;
DROP TABLE IF EXISTS `_dpkc_main_translation`;
DROP TABLE IF EXISTS `_dpkc_mn_relation1`;
DROP TABLE IF EXISTS `_dpkc_mn_relation2`;
DROP TABLE IF EXISTS `_dpkc_mn_relation_multi_pk`;
DROP TABLE IF EXISTS `_dpkc_1n_multi_relation`;
DROP TABLE IF EXISTS `_dpkc_1n_relation_on_another_id`;
DROP TABLE IF EXISTS `_dpkc_1n_relation_double_constraint`;
DROP TABLE IF EXISTS `_dpkc_1n_relation_double_constraint_two`;
DROP TABLE IF EXISTS `_dpkc_main`;
DROP TABLE IF EXISTS `_dpkc_other`;
DROP TABLE IF EXISTS `_dpkc_other_multi_pk`;

CREATE TABLE `_dpkc_main`
(
    `id`         binary(16)                              NOT NULL,
    `another_id` binary(16)                              NOT NULL,
    `varchar`    varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `nbr`        INT(11),
    PRIMARY KEY (`id`),
    KEY `fk._dpkc_main.nbr` (`nbr`),
    KEY `fk._dpkc_main.another_id` (`another_id`)
);

CREATE TABLE `_dpkc_main_translation`
(
    `_dpkc_main_id` binary(16)                              NOT NULL,
    `language_id`   binary(16)                              NOT NULL,
    `varchar`       varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    PRIMARY KEY (`_dpkc_main_id`, `language_id`),
    KEY `fk._dpkc_main_translation.language_id` (`language_id`),
    CONSTRAINT `fk._dpkc_main_translation.cms_page_id` FOREIGN KEY (`_dpkc_main_id`) REFERENCES `_dpkc_main` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk._dpkc_main_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE `_dpkc_1n_relation1`
(
    `id`            binary(16)                              NOT NULL,
    `varchar`       varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `_dpkc_main_id` binary(16)                              NOT NULL,
    PRIMARY KEY (`id`),
    KEY `FK._dpkc_1n_relation1._dpkc_main_id` (`_dpkc_main_id`),
    CONSTRAINT `FK._dpkc_1n_relation1._dpkc_main_id` FOREIGN KEY (`_dpkc_main_id`) REFERENCES `_dpkc_main` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE `_dpkc_1n_relation2`
(
    `id`            binary(16)                              NOT NULL,
    `varchar`       varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `_dpkc_main_id` binary(16)                              DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `fk._dpkc_1n_relation2._dpkc_main_id` (`_dpkc_main_id`),
    CONSTRAINT `fk._dpkc_1n_relation2._dpkc_main_id` FOREIGN KEY (`_dpkc_main_id`) REFERENCES `_dpkc_main` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE `_dpkc_1n_relation3`
(
    `id`            binary(16)                              NOT NULL,
    `varchar`       varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `_dpkc_main_id` binary(16)                              NOT NULL,
    PRIMARY KEY (`id`),
    KEY `fk._dpkc_1n_relation3._dpkc_main_id` (`_dpkc_main_id`),
    CONSTRAINT `fk._dpkc_1n_relation3._dpkc_main_id` FOREIGN KEY (`_dpkc_main_id`) REFERENCES `_dpkc_main` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE `_dpkc_other`
(
    `id`      binary(16)                              NOT NULL,
    `varchar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    PRIMARY KEY (`id`)
);

CREATE TABLE `_dpkc_other_multi_pk`
(
    `id`       binary(16)                              NOT NULL,
    `other_id` binary(16)                              NOT NULL,
    `varchar`  varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    PRIMARY KEY (`id`, `other_id`)
);

CREATE TABLE `_dpkc_mn_relation1`
(
    `_dpkc_main_id`  binary(16) NOT NULL,
    `_dpkc_other_id` binary(16) NOT NULL,
    PRIMARY KEY (`_dpkc_main_id`, `_dpkc_other_id`),
    KEY `fk._dpkc_mn_relation1._dpkc_other_id` (`_dpkc_other_id`),
    CONSTRAINT `fk._dpkc_mn_relation1._dpkc_main_id` FOREIGN KEY (`_dpkc_main_id`) REFERENCES `_dpkc_main` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk._dpkc_mn_relation1._dpkc_other_id` FOREIGN KEY (`_dpkc_other_id`) REFERENCES `_dpkc_other` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE `_dpkc_mn_relation2`
(
    `_dpkc_main_id`  binary(16) NOT NULL,
    `_dpkc_other_id` binary(16) NOT NULL,
    PRIMARY KEY (`_dpkc_main_id`, `_dpkc_other_id`),
    KEY `fk._dpkc_mn_relation2._dpkc_other_id` (`_dpkc_other_id`),
    CONSTRAINT `fk._dpkc_mn_relation2._dpkc_main_id` FOREIGN KEY (`_dpkc_main_id`) REFERENCES `_dpkc_main` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk._dpkc_mn_relation2._dpkc_other_id` FOREIGN KEY (`_dpkc_other_id`) REFERENCES `_dpkc_other` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE `_dpkc_mn_relation_multi_pk`
(
    `_dpkc_main_id`                 binary(16) NOT NULL,
    `_dpkc_other_multi_pk_id`       binary(16) NOT NULL,
    `_dpkc_other_multi_pk_other_id` binary(16) NOT NULL,
    PRIMARY KEY (`_dpkc_main_id`, `_dpkc_other_multi_pk_id`, `_dpkc_other_multi_pk_other_id`),
    KEY `fk._dpkc_mn_relation_multi_pk.id` (`_dpkc_other_multi_pk_id`, `_dpkc_other_multi_pk_other_id`),
    CONSTRAINT `fk._dpkc_mn_relation_multi_pk._dpkc_main_id` FOREIGN KEY (`_dpkc_main_id`) REFERENCES `_dpkc_main` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk._dpkc_mn_relation_multi_pk._dpkc_other_multi_pk_id` FOREIGN KEY (`_dpkc_other_multi_pk_id`, `_dpkc_other_multi_pk_other_id`) REFERENCES `_dpkc_other_multi_pk` (`id`, `other_id`) ON DELETE CASCADE ON UPDATE CASCADE
);


CREATE TABLE `_dpkc_1n_multi_relation`
(
    `id`                   binary(16)                              NOT NULL,
    `varchar`              varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `_dpkc_main_id`        binary(16)                              NOT NULL,
    `_dpkc_main_select_id` binary(16)                              NOT NULL,
    PRIMARY KEY (`id`),
    KEY `fk._dpkc_1n_multi_relation._dpkc_main_id` (`_dpkc_main_id`),
    KEY `fk._dpkc_1n_multi_relation._dpkc_main_select_id` (`_dpkc_main_select_id`),
    CONSTRAINT `fk._dpkc_1n_multi_relation._dpkc_main_id` FOREIGN KEY (`_dpkc_main_id`) REFERENCES `_dpkc_main` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk._dpkc_1n_multi_relation._dpkc_main_select_id` FOREIGN KEY (`_dpkc_main_select_id`) REFERENCES `_dpkc_main` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE `_dpkc_1n_relation_on_another_id`
(
    `id`                    binary(16)                              NOT NULL,
    `varchar`               varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `_dpkc_main_another_id` binary(16)                              NOT NULL,
    PRIMARY KEY (`id`),
    KEY `fk._dpkc_1n_relation_on_another_id._dpkc_main_another_id` (`_dpkc_main_another_id`),
    CONSTRAINT `fk._dpkc_1n_relation_on_another_id._dpkc_main_another_id` FOREIGN KEY (`_dpkc_main_another_id`) REFERENCES `_dpkc_main` (`another_id`) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE `_dpkc_1n_relation_double_constraint`
(
    `id`                    binary(16)                              NOT NULL,
    `varchar`               varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `_dpkc_main_id`         binary(16)                              NOT NULL,
    `_dpkc_main_another_id` binary(16)                              NOT NULL,
    PRIMARY KEY (`id`),
    KEY `fk._dpkc_1n_relation_double_constraint._dpkc_main_id` (`_dpkc_main_id`),
    KEY `fk._dpkc_1n_relation_double_constraint._dpkc_main_another_id` (`_dpkc_main_another_id`),
    CONSTRAINT `fk._dpkc_1n_relation_double_constraint._dpkc_main` FOREIGN KEY (`_dpkc_main_another_id`, `_dpkc_main_id`) REFERENCES `_dpkc_main` (`another_id`, `id`) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE `_dpkc_1n_relation_double_constraint_two`
(
    `id`                    binary(16)                              NOT NULL,
    `varchar`               varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `_dpkc_main_id`         binary(16)                              NOT NULL,
    `_dpkc_main_another_id` binary(16)                              NOT NULL,
    PRIMARY KEY (`id`),
    KEY `fk._dpkc_1n_relation_double_constraint_two._dpkc_main_id` (`_dpkc_main_id`),
    KEY `fk._dpkc_1n_relation_double_constraint_two._dpkc_main_another_id` (`_dpkc_main_another_id`),
    CONSTRAINT `fk._dpkc_1n_relation_double_constraint_two._dpkc_main` FOREIGN KEY (`_dpkc_main_another_id`, `_dpkc_main_id`) REFERENCES `_dpkc_main` (`another_id`, `id`) ON DELETE CASCADE ON UPDATE CASCADE
);

INSERT INTO `_dpkc_main` (`id`, `another_id`, `varchar`, `nbr`)
VALUES (0xc020965ff8f44438a169226cefcdd7d5, 0xc16bc2242f294a819e80781197a0bdc1, 'test', 1),
       (0x6fd7de65b8f948db9cea2a11c05cee27, 0xc16bc2242f294a819e80781197a0bdc1, 'check', 2);

INSERT INTO `_dpkc_main_translation` (`_dpkc_main_id`, `language_id`, `varchar`)
VALUES (0xc020965ff8f44438a169226cefcdd7d5, 0x2fbb5fe2e29a4d70aa5854ce7ce3e20b, 'test');

INSERT INTO `_dpkc_other` (`id`, `varchar`)
VALUES (0x388f7f79e0624387934116474ec30401, 'test');

INSERT INTO `_dpkc_other_multi_pk` (`id`, `other_id`, `varchar`)
VALUES (0x6c21f788c4394f3b8c5be37d06cb5dae, 0xe462f833bdc34b57a3113f2347a484fa, 'test');

INSERT INTO `_dpkc_1n_relation1` (`id`, `varchar`, `_dpkc_main_id`)
VALUES (0xa0c5592c5bda46c589818039b47e8053, 'test', 0xc020965ff8f44438a169226cefcdd7d5);

INSERT INTO `_dpkc_1n_relation2` (`id`, `varchar`, `_dpkc_main_id`)
VALUES (0xbaeabe6c86ea429eb1b8cc58e807a9ca, 'test', 0xc020965ff8f44438a169226cefcdd7d5);

INSERT INTO `_dpkc_1n_relation3` (`id`, `varchar`, `_dpkc_main_id`)
VALUES (0x09906676dfb44395806de20ff0c2796a, 'test', 0xc020965ff8f44438a169226cefcdd7d5);

INSERT INTO `_dpkc_mn_relation1` (`_dpkc_main_id`, `_dpkc_other_id`)
VALUES (0x6fd7de65b8f948db9cea2a11c05cee27, 0x388f7f79e0624387934116474ec30401);

INSERT INTO `_dpkc_mn_relation2` (`_dpkc_main_id`, `_dpkc_other_id`)
VALUES (0x6fd7de65b8f948db9cea2a11c05cee27, 0x388f7f79e0624387934116474ec30401);

INSERT INTO `_dpkc_mn_relation_multi_pk` (`_dpkc_main_id`,`_dpkc_other_multi_pk_id`,`_dpkc_other_multi_pk_other_id`)
VALUES (0xc020965ff8f44438a169226cefcdd7d5, 0x6c21f788c4394f3b8c5be37d06cb5dae, 0xe462f833bdc34b57a3113f2347a484fa);

INSERT INTO `_dpkc_1n_multi_relation` (`id`, `varchar`, `_dpkc_main_id`, `_dpkc_main_select_id`)
VALUES (0xe462f833bdc34b57a3113f2347a484fa, 'test', 0xc020965ff8f44438a169226cefcdd7d5, 0x6fd7de65b8f948db9cea2a11c05cee27);

INSERT INTO `_dpkc_1n_relation_on_another_id` (`id`, `varchar`, `_dpkc_main_another_id`)
VALUES (0x085921fda4cc4116a810679a15a5e0f1, 'test', 0xc16bc2242f294a819e80781197a0bdc1);

INSERT INTO `_dpkc_1n_relation_double_constraint` (`id`, `varchar`, `_dpkc_main_id`, `_dpkc_main_another_id`)
VALUES (0x085921fda4cc4116a810679a15a5e0f1, 'test', 0x6fd7de65b8f948db9cea2a11c05cee27, 0xc16bc2242f294a819e80781197a0bdc1);

INSERT INTO `_dpkc_1n_relation_double_constraint_two` (`id`, `varchar`, `_dpkc_main_id`, `_dpkc_main_another_id`)
VALUES (0x085921fda4cc4116a810679a15a5e0f1, 'test', 0x6fd7de65b8f948db9cea2a11c05cee27, 0xc16bc2242f294a819e80781197a0bdc1);










