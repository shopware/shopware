<?php
class Migrations_Migration149 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
        SET @parent_form = (SELECT id FROM s_core_config_forms WHERE name = 'Frontend' LIMIT 1);

        INSERT IGNORE INTO `s_core_config_forms` (`id`, `parent_id`, `name`, `label`, `description`, `position`, `scope`, `plugin_id`) VALUES
        (NULL, @parent_form , 'SEPA', 'SEPA-Konfiguration', NULL, 0, 1, NULL);

        SET @parent = (SELECT id FROM s_core_config_forms WHERE name = 'SEPA' AND parent_id=@parent_form);

        INSERT IGNORE INTO `s_core_config_form_translations` (`id`, `form_id`, `locale_id`, `label`, `description`)
        VALUES (NULL, @parent, '2', 'SEPA configuration', NULL);

        INSERT IGNORE INTO `s_core_config_elements`
        (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`)
        VALUES
        (@parent, 'sepaCompany', 's:0:"";', 'Firmenname', NULL, 'text', 0, 1, 1, NULL, NULL, NULL),
        (@parent, 'sepaHeaderText', 's:0:"";', 'Überschrift', NULL, 'text', 0, 2, 1, NULL, NULL, NULL),
        (@parent, 'sepaSellerId', 's:0:""', 'Gläubiger-Identifikationsnummer', NULL, 'text', 0, 3, 1, NULL, NULL, NULL),
        (@parent, 'sepaSendEmail', 'i:1;', 'SEPA Mandat automatisch versenden', NULL, 'checkbox', 0, 4, 1, NULL, NULL, NULL),
        (@parent, 'sepaShowBic', 'i:1;', 'SEPA BIC Feld anzeigen', NULL, 'checkbox', 0, 5, 1, NULL, NULL, NULL),
        (@parent, 'sepaRequireBic', 'i:1;', 'SEPA BIC Feld erforderlich', NULL, 'checkbox', 0, 6, 1, NULL, NULL, NULL),
        (@parent, 'sepaShowBankName', 'i:1;', 'SEPA Kreditinstitut Feld anzeigen', NULL, 'checkbox', 0, 7, 1, NULL, NULL, NULL),
        (@parent, 'sepaRequireBankName', 'i:1;', 'SEPA Kreditinstitut Feld erforderlich', NULL, 'checkbox', 0, 8, 1, NULL, NULL, NULL);

        SET @elementOne = (SELECT id FROM s_core_config_elements WHERE name = 'sepaCompany' LIMIT 1);
        SET @elementTwo = (SELECT id FROM s_core_config_elements WHERE name = 'sepaHeaderText' LIMIT 1);
        SET @elementThree = (SELECT id FROM s_core_config_elements WHERE name = 'sepaSellerId' LIMIT 1);
        SET @elementFour = (SELECT id FROM s_core_config_elements WHERE name = 'sepaSendEmail' LIMIT 1);
        SET @elementFive = (SELECT id FROM s_core_config_elements WHERE name = 'sepaShowBic' LIMIT 1);
        SET @elementSix = (SELECT id FROM s_core_config_elements WHERE name = 'sepaRequireBic' LIMIT 1);
        SET @elementSeven = (SELECT id FROM s_core_config_elements WHERE name = 'sepaShowBankName' LIMIT 1);
        SET @elementEight = (SELECT id FROM s_core_config_elements WHERE name = 'sepaRequireBankName' LIMIT 1);

        INSERT IGNORE INTO `s_core_config_element_translations` (`id`, `element_id`, `locale_id`, `label`, `description`)
        VALUES
        (NULL, @elementOne, '2', 'Creditor name', 'Name of the creditor to be included in the mandate.'),
        (NULL, @elementTwo, '2', 'Header text', 'Header text of the mandate.'),
        (NULL, @elementThree, '2', 'Creditor number', 'Number of the creditor to be included in the mandate.'),
        (NULL, @elementFour, '2', 'Send email', 'Send email to the customer with the attached SEPA mandate file.'),
        (NULL, @elementFive, '2', 'Show SEPA\'s BIC field', 'Allow customer to specify its BIC when filling in SEPA payment data.'),
        (NULL, @elementSix, '2', 'Require SEPA\'s BIC field', 'Require customer to specify its BIC when filling in SEPA payment data. This option is ignored if the field is hidden.'),
        (NULL, @elementSeven, '2', 'Show SEPA\'s bank name field', 'Allow customer to specify its bank name when filling in SEPA payment data.'),
        (NULL, @elementEight, '2', 'Require SEPA\'s bank name field', 'Require customer to specify its bank name when filling in SEPA payment data. This option is ignored if the field is hidden.');

        CREATE TABLE IF NOT EXISTS `s_core_payment_instance` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `payment_mean_id` int(11) DEFAULT NULL,
            `order_id` int(11) DEFAULT NULL,
            `user_id` int(11) DEFAULT NULL,
            `firstname` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `lastname` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `zipcode` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `city` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `account_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `account_holder` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `bank_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `bank_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `bic` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `iban` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `amount` decimal(20,4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `created_at` date NOT NULL,
            PRIMARY KEY (`id`),
            KEY `payment_mean_id` (`payment_mean_id`),
            KEY `payment_mean_id_2` (`payment_mean_id`),
            KEY `order_id` (`order_id`),
            KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1 ;

        CREATE TABLE IF NOT EXISTS `s_core_payment_data` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `payment_mean_id` int(11) NOT NULL,
            `user_id` int(11) NOT NULL,
            `use_billing_data` int(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `bankname` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `bic` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `iban` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `created_at` date NOT NULL,
            PRIMARY KEY (`id`),
            KEY `payment_mean_id` (`payment_mean_id`,`user_id`),
            KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1 ;

        INSERT IGNORE INTO `s_core_paymentmeans` (`name`, `description`, `template`, `class`, `table`, `hide`, `additionaldescription`, `debit_percent`, `surcharge`, `surchargestring`, `position`, `active`, `esdactive`, `embediframe`, `hideprospect`, `action`, `pluginID`, `source`) VALUES
            ('sepa', 'SEPA', 'sepa.tpl', 'sepa', '', 0, 'SEPA debit', 0, 0, '', 5, 0, 0, '', 0, '', NULL, 1);

        INSERT IGNORE INTO `s_core_snippets` (`id`, `namespace`, `shopID`, `localeID`, `name`, `value`, `created`, `updated`) VALUES
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/payment/sepa', 1, 1, 'PaymentSepaLabelIban', 'IBAN', '2013-11-01 00:00:00', '2013-11-01 00:00:00'),
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/payment/sepa', 1, 2, 'PaymentSepaLabelIban', 'IBAN', '2013-11-01 00:00:00', '2013-11-01 00:00:00'),
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/payment/sepa', 1, 1, 'PaymentSepaLabelBic', 'BIC', '2013-11-01 00:00:00', '2013-11-01 00:00:00'),
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/payment/sepa', 1, 2, 'PaymentSepaLabelBic', 'BIC', '2013-11-01 00:00:00', '2013-11-01 00:00:00'),
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/payment/sepa', 1, 1, 'PaymentSepaLabelBankName', 'Ihre Bank', '2013-11-01 00:00:00', '2013-11-01 00:00:00'),
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/payment/sepa', 1, 2, 'PaymentSepaLabelBankName', 'Name of bank', '2013-11-01 00:00:00', '2013-11-01 00:00:00'),
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/payment/sepa', 1, 1, 'PaymentSepaLabelUseBillingData', 'Rechnungs-Adresse in Mandat übernehmen?', '2013-11-01 00:00:00', '2013-11-01 00:00:00'),
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/payment/sepa', 1, 2, 'PaymentSepaLabelUseBillingData', 'Use billing information for SEPA debit mandate?', '2013-11-01 00:00:00', '2013-11-01 00:00:00'),
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/payment/sepa', 1, 1, 'PaymentSepaInfoFields', 'Die mit einem * markierten Felder sind Pflichtfelder.', '2013-11-01 00:00:00', '2013-11-01 00:00:00'),
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/payment/sepa', 1, 2, 'PaymentSepaInfoFields', 'The fields marked with * are required.', '2013-11-01 00:00:00', '2013-11-01 00:00:00');

        INSERT IGNORE INTO `s_core_snippets` (`id`, `namespace`, `shopID`, `localeID`, `name`, `value`, `created`, `updated`) VALUES
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/sepa/email', 1, 1, 'SepaEmailCreditorNumber', 'Gläubiger-Identifikationsnummer:', '2013-11-01 00:00:00', '2013-11-01 00:00:00'),
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/sepa/email', 1, 2, 'SepaEmailCreditorNumber', 'Creditor number:', '2013-11-01 00:00:00', '2013-11-01 00:00:00'),
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/sepa/email', 1, 1, 'SepaEmailMandateReference', 'Mandatsreferenz: <strong>{$data.orderNumber}</strong>', '2013-11-01 00:00:00', '2013-11-01 00:00:00'),
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/sepa/email', 1, 2, 'SepaEmailMandateReference', 'Mandate reference: <strong>{$data.orderNumber}</strong>', '2013-11-01 00:00:00', '2013-11-01 00:00:00'),
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/sepa/email', 1, 1, 'SepaEmailDirectDebitMandate', 'SEPA-Lastschriftmandat', '2013-11-01 00:00:00', '2013-11-01 00:00:00'),
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/sepa/email', 1, 2, 'SepaEmailDirectDebitMandate', 'SEPA direct debit mandate', '2013-11-01 00:00:00', '2013-11-01 00:00:00'),
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/sepa/email', 1, 1, 'SepaEmailBody', 'Ich ermächtige den {$config.sepaCompany}, Zahlungen von meinem Konto mittels Lastschrift einzuziehen. Zugleich weise ich mein Kreditinstitut an, die von dem {$config.sepaCompany} auf mein Konto gezogenen Lastschriften einzulösen.</p><p> Hinweis: Ich kann innerhalb von acht Wochen, beginnend mit dem Belastungsdatum, die Erstattung des belasteten Betrages verlangen. Es gelten dabei die mit meinem Kreditinstitut vereinbarten Bedingungen.', '2013-11-01 00:00:00', '2013-11-01 00:00:00'),
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/sepa/email', 1, 2, 'SepaEmailBody', 'I hereby authorize payments to be made from my account to {$config.sepaCompany} via direct debit. At the same time, I instruct my financial institution to honor the debits drawn from my account.</p><p>Note: I may request reimbursement for the debited amount up to eight weeks following the date of the transfer, in accordance with preexisting terms and conditions set by my bank.', '2013-11-01 00:00:00', '2013-11-01 00:00:00'),
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/sepa/email', 1, 1, 'SepaEmailName', 'Vorname und Name (Kontoinhaber)', '2013-11-01 00:00:00', '2013-11-01 00:00:00'),
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/sepa/email', 1, 2, 'SepaEmailName', "Account holder's name", '2013-11-01 00:00:00', '2013-11-01 00:00:00'),
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/sepa/email', 1, 1, 'SepaEmailAddress', 'Straße und Hausnummer', '2013-11-01 00:00:00', '2013-11-01 00:00:00'),
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/sepa/email', 1, 2, 'SepaEmailAddress', 'Address', '2013-11-01 00:00:00', '2013-11-01 00:00:00'),
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/sepa/email', 1, 1, 'SepaEmailZip', 'Postleitzahl und Ort', '2013-11-01 00:00:00', '2013-11-01 00:00:00'),
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/sepa/email', 1, 2, 'SepaEmailZip', 'Zip code and City', '2013-11-01 00:00:00', '2013-11-01 00:00:00'),
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/sepa/email', 1, 1, 'SepaEmailBankName', 'Kreditinstitut', '2013-11-01 00:00:00', '2013-11-01 00:00:00'),
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/sepa/email', 1, 2, 'SepaEmailBankName', 'Bank', '2013-11-01 00:00:00', '2013-11-01 00:00:00'),
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/sepa/email', 1, 1, 'SepaEmailBic', 'BIC', '2013-11-01 00:00:00', '2013-11-01 00:00:00'),
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/sepa/email', 1, 2, 'SepaEmailBic', 'BIC', '2013-11-01 00:00:00', '2013-11-01 00:00:00'),
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/sepa/email', 1, 1, 'SepaEmailIban', 'IBAN', '2013-11-01 00:00:00', '2013-11-01 00:00:00'),
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/sepa/email', 1, 2, 'SepaEmailIban', 'IBAN', '2013-11-01 00:00:00', '2013-11-01 00:00:00'),
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/sepa/email', 1, 1, 'SepaEmailSignature', 'Datum, Ort und Unterschrift', '2013-11-01 00:00:00', '2013-11-01 00:00:00'),
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/sepa/email', 1, 2, 'SepaEmailSignature', 'Signature (including date and location)', '2013-11-01 00:00:00', '2013-11-01 00:00:00');

        INSERT IGNORE INTO `s_core_snippets` (`id`, `namespace`, `shopID`, `localeID`, `name`, `value`, `created`, `updated`) VALUES
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/payment/sepa', 1, 1, 'PaymentDebitLabelIban', 'IBAN', '2013-11-01 00:00:00', '2013-11-01 00:00:00'),
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/payment/sepa', 1, 2, 'PaymentDebitLabelIban', 'IBAN', '2013-11-01 00:00:00', '2013-11-01 00:00:00'),
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/payment/sepa', 1, 1, 'PaymentDebitLabelBic', 'BIC', '2013-11-01 00:00:00', '2013-11-01 00:00:00'),
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/payment/sepa', 1, 2, 'PaymentDebitLabelBic', 'BIC', '2013-11-01 00:00:00', '2013-11-01 00:00:00'),
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/payment/sepa', 1, 1, 'ErrorIBAN', 'Ungültige IBAN', '2013-11-01 00:00:00', '2013-11-01 00:00:00'),
            (NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/payment/sepa', 1, 2, 'ErrorIBAN', 'Invalid IBAN', '2013-11-01 00:00:00', '2013-11-01 00:00:00');

        INSERT IGNORE INTO `s_core_snippets` (`id`, `namespace`, `shopID`, `localeID`, `name`, `value`, `created`, `updated`)
            SELECT NULL, 'engine/Shopware/Plugins/Default/Core/PaymentMethods/Views/frontend/plugins/payment/sepa', `shopID`, `localeID`, `name`, `value`, '2013-11-01 00:00:00', '2013-11-01 00:00:00'
            FROM `s_core_snippets`
            WHERE `s_core_snippets`.`name` IN ('PaymentDebitLabelBankname', 'PaymentDebitLabelName', 'PaymentDebitInfoFields') AND `s_core_snippets`.`namespace` LIKE 'frontend/plugins/payment/debit';

        INSERT IGNORE INTO s_core_config_mails (name, frommail, fromname, subject, content, contentHTML, isHTML, attachment) VALUES
        ('sORDERSEPAAUTHORIZATION', '{config name=mail}', '{config name=shopName}', 'SEPA Lastschriftmandat', 'Hallo {$paymentInstance.firstName} {$paymentInstance.lastName}, im Anhang finden Sie ein Lastschriftmandat zu Ihrer Bestellung {$paymentInstance.orderNumber}. Bitte senden Sie uns das komplett ausgefüllte Dokument per Fax oder Email zurück.', 'Hallo {$paymentInstance.firstName} {$paymentInstance.lastName}, im Anhang finden Sie ein Lastschriftmandat zu Ihrer Bestellung {$paymentInstance.orderNumber}. Bitte senden Sie uns das komplett ausgefüllte Dokument per Fax oder Email zurück.', '1', '');

        SET @template = (SELECT id FROM s_core_config_mails WHERE name = 'sORDERSEPAAUTHORIZATION');

        DELETE FROM s_core_translations WHERE objecttype = 'config_mails' AND objectkey = @template;

        INSERT INTO `s_core_translations` (`objecttype`, `objectdata`, `objectkey`, `objectlanguage`) VALUES
            ('config_mails', 'a:3:{s:7:"subject";s:25:"SEPA direct debit mandate";s:7:"content";s:275:"Hello {$paymentInstance.firstName} {$paymentInstance.lastName},Attached you will find the direct debit mandate form for your order {$paymentInstance.orderNumber}. Please return the completely filled out document by fax or email. Best regards. The {config name=shopName} team.";s:11:"contentHtml";s:311:"<div>Hello {$paymentInstance.firstName} {$paymentInstance.lastName},<br><br>Attached you will find the direct debit mandate form for your order {$paymentInstance.orderNumber}. Please return the completely filled out document by fax or email.<br/><br/>Best regards,<br/><br/>The {config name=shopName} team</div>";}', @template, '2');

        UPDATE `s_core_translations` SET `objectdata` = 'a:5:{i:4;a:2:{s:11:"description";s:7:"Invoice";s:21:"additionalDescription";s:141:"Payment by invoice. Shopware provides automatic invoicing for all customers on orders after the first, in order to avoid defaults on payment.";}i:2;a:2:{s:11:"description";s:5:"Debit";s:21:"additionalDescription";s:15:"Additional text";}i:3;a:2:{s:11:"description";s:16:"Cash on delivery";s:21:"additionalDescription";s:25:"(including 2.00 Euro VAT)";}i:5;a:2:{s:11:"description";s:15:"Paid in advance";s:21:"additionalDescription";s:57:"The goods are delivered directly upon receipt of payment.";}i:6;a:1:{s:21:"additionalDescription";s:17:"SEPA direct debit";}}'
            WHERE `objectdata` = 'a:4:{i:4;a:2:{s:11:"description";s:7:"Invoice";s:21:"additionalDescription";s:141:"Payment by invoice. Shopware provides automatic invoicing for all customers on orders after the first, in order to avoid defaults on payment.";}i:2;a:2:{s:11:"description";s:5:"Debit";s:21:"additionalDescription";s:15:"Additional text";}i:3;a:2:{s:11:"description";s:16:"Cash on delivery";s:21:"additionalDescription";s:25:"(including 2.00 Euro VAT)";}i:5;a:2:{s:11:"description";s:15:"Paid in advance";s:21:"additionalDescription";s:57:"The goods are delivered directly upon receipt of payment.";}}';


EOD;
        $this->addSql($sql);
    }
}
