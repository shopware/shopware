INSERT INTO `_dpkc_main` (`id`, `mission_id`, `another_id`, `varchar`, `nbr`)
VALUES (0xc020965ff8f44438a169226cefcdd7d5, 0x899bd9b11d1a4c6db4fdd103942b44ee, 0xc16bc2242f294a819e80781197a0bdc1, 'test', 1),
       (0x6fd7de65b8f948db9cea2a11c05cee27, 0x899bd9b11d1a4c6db4fdd103942b44ee, 0xc16bc2242f294a819e80781197a0bdc1, 'check', 2);

INSERT INTO `_dpkc_main_translation` (`_dpkc_main_id`, `_dpkc_main_mission_id`, `language_id`, `varchar`)
VALUES (0xc020965ff8f44438a169226cefcdd7d5, 0x899bd9b11d1a4c6db4fdd103942b44ee, 0x2fbb5fe2e29a4d70aa5854ce7ce3e20b, 'test');



INSERT INTO `_dpkc_1n_relation1` (`id`, `varchar`, `_dpkc_main_id`, `_dpkc_main_mission_id`)
VALUES (0x93862a4375554534a8171bd5c94313ba, 'test', 0xc020965ff8f44438a169226cefcdd7d5, 0x899bd9b11d1a4c6db4fdd103942b44ee);

INSERT INTO `_dpkc_1n_relation2` (`id`, `varchar`, `_dpkc_main_id`, `_dpkc_main_mission_id`)
VALUES (0x77367883c2f14d5e9db9bf47b5fa451e, 'test', 0xc020965ff8f44438a169226cefcdd7d5, 0x899bd9b11d1a4c6db4fdd103942b44ee);

INSERT INTO `_dpkc_1n_relation3` (`id`, `varchar`, `_dpkc_main_id`, `_dpkc_main_mission_id`)
VALUES (0xe099887286e9461fbadf4f45ae24a1a0, 'test', 0xc020965ff8f44438a169226cefcdd7d5, 0x899bd9b11d1a4c6db4fdd103942b44ee);

INSERT INTO `_dpkc_mn_relation1` (`_dpkc_main_id`, `_dpkc_other_id`, `_dpkc_main_mission_id`)
VALUES (0x6fd7de65b8f948db9cea2a11c05cee27, 0x388f7f79e0624387934116474ec30401, 0x899bd9b11d1a4c6db4fdd103942b44ee);

INSERT INTO `_dpkc_mn_relation2` (`_dpkc_main_id`, `_dpkc_other_id`, `_dpkc_main_mission_id`)
VALUES (0x6fd7de65b8f948db9cea2a11c05cee27, 0x388f7f79e0624387934116474ec30401, 0x899bd9b11d1a4c6db4fdd103942b44ee);

INSERT INTO `_dpkc_mn_relation_multi_pk` (`_dpkc_main_id`,`_dpkc_other_multi_pk_id`,`_dpkc_other_multi_pk_other_id`, `_dpkc_main_mission_id`)
VALUES (0xc020965ff8f44438a169226cefcdd7d5, 0x6c21f788c4394f3b8c5be37d06cb5dae, 0xe462f833bdc34b57a3113f2347a484fa, 0x899bd9b11d1a4c6db4fdd103942b44ee);

INSERT INTO `_dpkc_1n_multi_relation` (`id`, `varchar`, `_dpkc_main_id`, `_dpkc_main_select_id`, `_dpkc_main_mission_id`)
VALUES (0xceb2e7f8e9c54a05ad98b45185dc570b, 'test', 0xc020965ff8f44438a169226cefcdd7d5, 0x6fd7de65b8f948db9cea2a11c05cee27, 0x899bd9b11d1a4c6db4fdd103942b44ee);

INSERT INTO `_dpkc_1n_relation_double_constraint` (`id`, `varchar`, `_dpkc_main_id`, `_dpkc_main_another_id`, `_dpkc_main_mission_id`)
VALUES (0xc7ab544b0e7e4f3c915ca692cb1f5b49, 'test', 0x6fd7de65b8f948db9cea2a11c05cee27, 0xc16bc2242f294a819e80781197a0bdc1, 0x899bd9b11d1a4c6db4fdd103942b44ee);

INSERT INTO `_dpkc_1n_relation_double_constraint_two` (`id`, `varchar`, `_dpkc_main_id`, `_dpkc_main_another_id`, `_dpkc_main_mission_id`)
VALUES (0x5f1bfc7bbd124627acd8fd019d2e8cfb, 'test', 0x6fd7de65b8f948db9cea2a11c05cee27, 0xc16bc2242f294a819e80781197a0bdc1, 0x899bd9b11d1a4c6db4fdd103942b44ee);
