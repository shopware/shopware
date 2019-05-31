SET @languageId = (SELECT `id` FROM `language` LIMIT 1);

INSERT INTO `mail_template` (`id`, `sender_mail`, `mail_type`, `system_default`, `created_at`, `updated_at`) VALUES
(UNHEX('6e6c8745b3e8463bac638819f2bf17ed'), 'info@shopware.com', 'newsletterDoubleOptIn', 1, '2019-04-09 10:40:47.000', NULL),
(UNHEX('a81a98ca84914499a6e740de36511d4d'), 'info@shopware.com', 'newsletterRegister', 1, '2019-04-09 10:40:47.000', NULL);

INSERT INTO `mail_template_translation` (`mail_template_id`, `language_id`, `sender_name`, `subject`, `description`, `content_html`, `content_plain`, `created_at`, `updated_at`) VALUES
(UNHEX('6e6c8745b3e8463bac638819f2bf17ed'), @languageId, 'Shopware unit test', 'Shopware unit test', 'Shopware unit test', '<h1>Shopware unit test<h1>', 'Shopware unit test', '2019-04-09 10:48:18.651', '2019-04-09 10:48:18.757'),
(UNHEX('a81a98ca84914499a6e740de36511d4d'), @languageId, 'foo bar', 'foo bar', 'foo bar', '<h1>foo bar<h1>', 'foo bar', '2019-04-09 10:48:18.646', '2019-04-09 10:48:18.752');

UPDATE event_action SET config = '{"mailTemplateId": "6e6c8745b3e8463bac638819f2bf17ed"}' WHERE event_name = 'newsletter.confirm_register_newsletter';
UPDATE event_action SET config = '{"mailTemplateId": "a81a98ca84914499a6e740de36511d4d"}' WHERE event_name = 'newsletter.register_newsletter';