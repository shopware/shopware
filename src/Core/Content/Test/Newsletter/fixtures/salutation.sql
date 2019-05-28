SET @languageId = (SELECT `id` FROM `language` LIMIT 1);

INSERT INTO `salutation` (`id`, `salutation_key`, `created_at`, `updated_at`) VALUES
(UNHEX('ad165c1faac14059832b6258ac0a7339'), 'unitTest', '2019-04-03 15:27:31.949', NULL);

INSERT INTO `salutation_translation` (`salutation_id`, `language_id`, `display_name`, `letter_name`, `created_at`, `updated_at`) VALUES
(UNHEX('ad165c1faac14059832b6258ac0a7339'), @languageId, 'unitTest', 'unitTest', '2019-04-03 15:27:31.948', NULL);
