DELETE FROM `_dpkc_1n_relation1`;
DELETE FROM `_dpkc_1n_relation2`;
DELETE FROM`_dpkc_1n_relation3`;
DELETE FROM `_dpkc_main_translation`;
DELETE FROM `_dpkc_mn_relation1`;
DELETE FROM `_dpkc_mn_relation2`;
DELETE FROM `_dpkc_mn_relation_multi_pk`;
DELETE FROM `_dpkc_1n_multi_relation`;
DELETE FROM `_dpkc_1n_relation_on_another_id`;
DELETE FROM `_dpkc_1n_relation_double_constraint`;
DELETE FROM `_dpkc_1n_relation_double_constraint_two`;
DELETE FROM `_dpkc_main`;
DELETE FROM `_dpkc_other`;
DELETE FROM `_dpkc_other_multi_pk`;

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
VALUES (0xbaeabe6c86ea429eb1b8cc58e807a9ca, 'test', 0xc020965ff8f44438a169226cefcdd7d5),
       (0xbaeabe6c86ea429eb1b8cc58e807aaca, 'test', NULL);

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










