<?php
class Migrations_Migration109 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
SET @formID = (SELECT id FROM s_core_config_forms WHERE `name`='Mail');
SET @localeID = (SELECT id FROM s_core_locales WHERE locale='en_GB');

SET @elementID = (SELECT id FROM s_core_config_elements WHERE form_id=@formID AND `name`='mailer_encoding');
UPDATE s_core_config_elements SET `label`='Encoding der Nachricht', description='8bit, 7bit, base64, binary und quoted-printable' WHERE id=@elementID;
INSERT IGNORE INTO s_core_config_element_translations (element_id, locale_id, label, description) VALUES(@elementID, @localeID, 'Message encoding', '8bit, 7bit, base64, binary or quoted-printable');

SET @elementID = (SELECT id FROM s_core_config_elements WHERE form_id=@formID AND `name`='mailer_hostname');
UPDATE s_core_config_elements SET `label`='Hostname für die Message-ID', description='Wird im Header mittels HELO verwendet. Andernfalls wird der Wert aus SERVER_NAME oder "localhost.localdomain" genutzt.' WHERE id=@elementID;
INSERT IGNORE INTO s_core_config_element_translations (element_id, locale_id, label, description) VALUES(@elementID, @localeID, 'Message ID hostname', 'Will be received in headers on a default HELO string. If not defined, the value returned from SERVER_NAME, "localhost.localdomain" will be used.');

SET @elementID = (SELECT id FROM s_core_config_elements WHERE form_id=@formID AND `name`='mailer_mailer');
UPDATE s_core_config_elements SET `label`='Methode zum Senden der Mail', description='mail, SMTP oder file' WHERE id=@elementID;
INSERT IGNORE INTO s_core_config_element_translations (element_id, locale_id, label, description) VALUES(@elementID, @localeID, 'Sending method', 'mail, SMTP or file');

SET @elementID = (SELECT id FROM s_core_config_elements WHERE form_id=@formID AND `name`='mailer_port');
UPDATE s_core_config_elements SET `label`='Standard Port', description='Setzt den Standard SMTP Server-Port' WHERE id=@elementID;
INSERT IGNORE INTO s_core_config_element_translations (element_id, locale_id, label, description) VALUES(@elementID, @localeID, 'Default port', 'Sets the default SMTP server port.');

SET @elementID = (SELECT id FROM s_core_config_elements WHERE form_id=@formID AND `name`='mailer_smtpsecure');
UPDATE s_core_config_elements SET `label`='Verbindungs Präfix', description='"", ssl, oder tls' WHERE id=@elementID;
INSERT IGNORE INTO s_core_config_element_translations (element_id, locale_id, label, description) VALUES(@elementID, @localeID, 'Connection prefix', '"", ssl, or tls');

SET @elementID = (SELECT id FROM s_core_config_elements WHERE form_id=@formID AND `name`='mailer_host');
UPDATE s_core_config_elements SET label='Mail Host', `description`='Es kann auch ein anderer Port über dieses Muster genutzt werden: [hostname:port] - Bsp.: smtp1.example.com:25' WHERE id=@elementID;
INSERT IGNORE INTO s_core_config_element_translations (element_id, locale_id, label, description) VALUES(@elementID, @localeID, 'Mail host', 'You can also specify a different port by using this format: [hostname:port] - e.g., smtp1.example.com:25');

SET @elementID = (SELECT id FROM s_core_config_elements WHERE form_id=@formID AND `name`='mailer_auth');
UPDATE s_core_config_elements SET `label`='Verbindungs-Authentifizierung', description='plain, login oder crammd5' WHERE id=@elementID;
INSERT IGNORE INTO s_core_config_element_translations (element_id, locale_id, label, description) VALUES(@elementID, @localeID, 'Connection auth', 'plain, login or crammd5');

SET @elementID = (SELECT id FROM s_core_config_elements WHERE form_id=@formID AND `name`='mailer_username');
UPDATE s_core_config_elements SET `label`='SMTP Benutzername' WHERE id=@elementID;
INSERT IGNORE INTO s_core_config_element_translations (element_id, locale_id, label, description) VALUES(@elementID, @localeID, 'SMTP username', NULL);

SET @elementID = (SELECT id FROM s_core_config_elements WHERE form_id=@formID AND `name`='mailer_password');
UPDATE s_core_config_elements SET `label`='SMTP Passwort' WHERE id=@elementID;
INSERT IGNORE INTO s_core_config_element_translations (element_id, locale_id, label, description) VALUES(@elementID, @localeID, 'SMTP password', NULL);


SET @formID = (SELECT id FROM s_core_config_forms WHERE label='InputFilter');
SET @elementID = (SELECT id FROM s_core_config_elements WHERE form_id=@formID AND `name`='own_filter');
INSERT IGNORE INTO s_core_config_element_translations (element_id, locale_id, label) VALUES(@elementID, @localeID, 'Own filter');

SET @formID = (SELECT id FROM s_core_config_forms WHERE label='Anmeldung / Registrierung');
SET @elementID = (SELECT id FROM s_core_config_elements WHERE form_id=@formID AND `name`='accountPasswordCheck');
INSERT IGNORE INTO s_core_config_element_translations (element_id, locale_id, label) VALUES(@elementID, @localeID, 'Check current password at password-change requests');

SET @formID = (SELECT id FROM s_core_config_forms WHERE label='Newsletter');
SET @elementID = (SELECT id FROM s_core_config_elements WHERE form_id=@formID AND `name`='MailCampaignsPerCall');
INSERT IGNORE INTO s_core_config_element_translations (element_id, locale_id, label) VALUES(@elementID, @localeID, 'Number of mails sent per call');

UPDATE s_core_config_element_translations SET description = 'Remind the customer about the review after purchase' WHERE label='Automatically reminder customer to submit reviews';

SET @formID = (SELECT id FROM s_core_config_forms WHERE `name`='LastArticles');
SET @elementID = (SELECT id FROM s_core_config_elements WHERE form_id=@formID AND `name`='show');
UPDATE s_core_config_element_translations SET `label`='Display recently viewed items' WHERE element_id=@elementID AND label='Show';

SET @formID = (SELECT id FROM s_core_config_forms WHERE `name`='AdvancedMenu');
SET @elementID = (SELECT id FROM s_core_config_elements WHERE form_id=@formID AND `name`='show');
UPDATE s_core_config_element_translations SET `label`='Activate expandable menu in storefront' WHERE element_id=@elementID AND label='Show';

SET @formID = (SELECT id FROM s_core_config_forms WHERE `name`='Compare');
SET @elementID = (SELECT id FROM s_core_config_elements WHERE form_id=@formID AND `name`='show');
UPDATE s_core_config_element_translations SET `label`='Display item comparison' WHERE element_id=@elementID AND label='Show';

SET @formID = (SELECT id FROM s_core_config_forms WHERE `name`='TagCloud');
SET @elementID = (SELECT id FROM s_core_config_elements WHERE form_id=@formID AND `name`='show');
UPDATE s_core_config_element_translations SET `label`='Display tag cloud' WHERE element_id=@elementID AND label='Show';


UPDATE s_core_config_form_translations SET label='Basic information' WHERE label='Master data';
UPDATE s_core_config_form_translations SET label='Country areas' WHERE label='Country-Areas';
UPDATE s_core_config_form_translations SET label='Shop page groups' WHERE label='Shop pages-groups';
UPDATE s_core_config_form_translations SET label='Input filter' WHERE label='InputFilter';
UPDATE s_core_config_form_translations SET label='Items' WHERE label='Product';
UPDATE s_core_config_form_translations SET label='Item numbers' WHERE label='Product numbers';
UPDATE s_core_config_form_translations SET label='Item open text fields' WHERE label='Product open text fields';
UPDATE s_core_config_form_translations SET label='Customer reviews' WHERE label='Product evaluations';
UPDATE s_core_config_form_translations SET label='Recently viewed items' WHERE label='Product process';
UPDATE s_core_config_form_translations SET label='Item comparison' WHERE label='Product comparison';
UPDATE s_core_config_form_translations SET label='Categories / lists' WHERE label='Categories/lists';
UPDATE s_core_config_form_translations SET label='Top seller / novelties' WHERE label='Top seller/novelties';
UPDATE s_core_config_form_translations SET label='Cross selling / item details' WHERE label='Cross Selling/Product details';
UPDATE s_core_config_form_translations SET label='Shopping cart / item details' WHERE label='Shopping cart/aticle details';
UPDATE s_core_config_form_translations SET label='Login / registration' WHERE label='Login/registration';
UPDATE s_core_config_form_translations SET label='Smart Search' WHERE label='Search';
UPDATE s_core_config_form_translations SET label='Discounts / surcharges' WHERE label='Discounts/surcharges';
UPDATE s_core_config_form_translations SET label='Email settings' WHERE label='e-mail settings';
UPDATE s_core_config_form_translations SET label='Shipping costs module' WHERE label='Shipping costs-module';
UPDATE s_core_config_form_translations SET label='SEO / router settings' WHERE label='SEO/Router settings';
UPDATE s_core_config_form_translations SET label='Item recommendations' WHERE label='Product recommendations';
UPDATE s_core_config_form_translations SET label='Additional settings' WHERE label='Further settings';

UPDATE s_core_config_element_translations SET label='Category buffer time' WHERE label='Categories buffering time';
UPDATE s_core_config_element_translations SET label='Price buffer time' WHERE label='Prices buffering time';
UPDATE s_core_config_element_translations SET label='Top seller buffer time' WHERE label='Top seller buffering time';
UPDATE s_core_config_element_translations SET label='Manufacturer buffer time' WHERE label='Manufacturer buffering time';
UPDATE s_core_config_element_translations SET label='Item detail buffer time' WHERE label='Product detail buffering time';
UPDATE s_core_config_element_translations SET label='Country list buffer time' WHERE label='Country list buffering time';
UPDATE s_core_config_element_translations SET label='Translation buffer time' WHERE label='Translations buffering time';
UPDATE s_core_config_element_translations SET label='Warning:  This may negatively affect performance!' WHERE description='Warning: This might have negative effects on your performance!';
UPDATE s_core_config_element_translations SET label='Log errors in database' WHERE label='Write error in database';
UPDATE s_core_config_element_translations SET label='Notify shop owners' WHERE label='Send error to shop owner';
UPDATE s_core_config_element_translations SET label='Activate Remote File Inclusion protection' WHERE label='Activate RemoteFileInclusion-protection';
UPDATE s_core_config_element_translations SET label='Customer reviews must be approved' WHERE label='Product evaluations neey,d to be unlocked';
UPDATE s_core_config_element_translations SET label='Deactivate customer reviews' WHERE label='Deactivate product evaluations%';
UPDATE s_core_config_element_translations SET label='Size of display' WHERE label='Size of preview picture';
UPDATE s_core_config_element_translations SET label='Number of days items are displayed' WHERE label='Storage period in days';
UPDATE s_core_config_element_translations SET label='Maximum number of items to display' WHERE label='Number of products in process (recently viewed)';
UPDATE s_core_config_element_translations SET label='Number of tiers' WHERE label='Number of levels';
UPDATE s_core_config_element_translations SET label='Maximum number of items to be compared' WHERE label='Maximum number of products to be compared';
UPDATE s_core_config_element_translations SET label='Number of ranks' WHERE label='Number of steps';
UPDATE s_core_config_element_translations SET label='Time period (in days) considered' WHERE label='Considered time in days';
UPDATE s_core_config_element_translations SET label='Items per page' WHERE label='Products per page';
UPDATE s_core_config_element_translations SET label='Maximum number of pages per category' WHERE label='Categories max. number of pages';
UPDATE s_core_config_element_translations SET label='Selection of items per page' WHERE label='Selection of products per page';
UPDATE s_core_config_element_translations SET label='Available template categories' WHERE label='Available templates categories';
UPDATE s_core_config_element_translations SET label='Jump to detail if only one item is available' WHERE label='Jump to detail if only one product is available';
UPDATE s_core_config_element_translations SET label='Number of days items are considered new' WHERE label='Mark products as new (days)';
UPDATE s_core_config_element_translations SET label='Number of days considered for top sellers' WHERE label='Mark products as top seller';
UPDATE s_core_config_element_translations SET label='Number of items displayed as novelties' WHERE label='Number of products shown under novelties';
UPDATE s_core_config_element_translations SET label='Number of similar items for cross selling' WHERE label='Number of similar products for cross selling';
UPDATE s_core_config_element_translations SET label='Number of items "customers also bought"' WHERE label='Number of customers also bought"products cross selling"';
UPDATE s_core_config_element_translations SET label='Number of automatically determined similar products (detail page)' WHERE label='Number of automatically determined similar products (detail page)';
UPDATE s_core_config_element_translations SET label='Maximum number of items selectable via pull-down menu' WHERE label='Max. number of selectable products/products via pulldown menu';
UPDATE s_core_config_element_translations SET label='Text for unavailable items' WHERE label='Text for non-available products';
UPDATE s_core_config_element_translations SET label='Minimum shopping cart value for offering individual requests' WHERE label='Min. shopping cart value from which option of individual request is offered';
UPDATE s_core_config_element_translations SET label='Check stock in real time on detail page' WHERE label='Check stock level on detail page in real time';
UPDATE s_core_config_element_translations SET label='Maximum number of variants per item' WHERE label='Max. number of configurator variants per product';
UPDATE s_core_config_element_translations SET label='Deactivate out-of-stock items' WHERE label='Deactivate sales products without stock level';
UPDATE s_core_config_element_translations SET label='Display main items in bundles' WHERE label='Show main products in bundles';
UPDATE s_core_config_element_translations SET label='Display inventory shortages in shopping cart' WHERE label='Show in shopping cart if stock level is undershot';
UPDATE s_core_config_element_translations SET label='Available templates for detail page' WHERE label='Available templates detail page';
UPDATE s_core_config_element_translations SET label='Available templates for blog detail page' WHERE label='Available templates blog detail page';
UPDATE s_core_config_element_translations SET label='Minimum password length (registration)' WHERE label='Min. lenth of password (registration)';
UPDATE s_core_config_element_translations SET label='Standard payment method ID (registration)' WHERE label='Standard payment method (Id) (registration)';
UPDATE s_core_config_element_translations SET label='Standard recipient group ID for registered customers (system / newsletter)' WHERE label='Standard group of recipients (ID) for registered customers (System/newsletter)';
UPDATE s_core_config_element_translations SET label='Generate customer numbers automatically' WHERE label='Shopware generates customer numbers';
UPDATE s_core_config_element_translations SET label='Deactivate AGB terms checkbox on checkout page' WHERE label='Deactivate terms-checkbox on checkout page.';
UPDATE s_core_config_element_translations SET label='Default payment method ID' WHERE label='Fallback payment type (ID)';
UPDATE s_core_config_element_translations SET label='Data protection conditions must be accepted via checkbox' WHERE label='Data protection regulations need to be accepted over checkbox';
UPDATE s_core_config_element_translations SET label='Deactivate "no customer account"' WHERE label='deactivate No customer account';
UPDATE s_core_config_element_translations SET label='Maximum search term length' WHERE label='Max. length of search term';
UPDATE s_core_config_element_translations SET label='Number of live search results' WHERE label='Number of results live search';
UPDATE s_core_config_element_translations SET label='Maximum distance allowed for string matching (%)' WHERE label='Max. distance for fuzzy search (percentage)';
UPDATE s_core_config_element_translations SET label='Last update (dd.mm.yyyy)' WHERE label='Date of the last update';
UPDATE s_core_config_element_translations SET label='Minimum relevance for top items (%)' WHERE label='Min. relevance for top products (percentage)';
UPDATE s_core_config_element_translations SET label='Maximum distance allowed for partial names (%)' WHERE label='Max. distance for partial names (percentage)';
UPDATE s_core_config_element_translations SET label='Vouchers designated as' WHERE label='Designation vouchers';
UPDATE s_core_config_element_translations SET label='Discounts designated as' WHERE label='Designation discounts';
UPDATE s_core_config_element_translations SET label='Shortages designated as' WHERE label='Designation of reduced quantities';
UPDATE s_core_config_element_translations SET label='Surcharges on payment methods designated as' WHERE label='Designation for percental surcharge on payment method';
UPDATE s_core_config_element_translations SET label='Order number for discounts' WHERE label='Discounts order number';
UPDATE s_core_config_element_translations SET label='Order  number for shortages' WHERE label='Reduced quantities order number';
UPDATE s_core_config_element_translations SET label='Order number for deduction dispatch rule' WHERE label='Deduction dispatch rule (order number)';
UPDATE s_core_config_element_translations SET label='Deduction dispatch rule designated as' WHERE label='Deduction dispatch rules (designation)';
UPDATE s_core_config_element_translations SET label='All-inclusive surcharges on payment methods designated as' WHERE label='Lump sum for payment method (description)';
UPDATE s_core_config_element_translations SET label='Order number for all-inclusive surcharges on payment methods designated as' WHERE label='Lump sum for payment method (order number)';
UPDATE s_core_config_element_translations SET label='Send registration confirmation to shop owner in CC' WHERE label='Send registration confirmation to shop owner in CC.';
UPDATE s_core_config_element_translations SET label='Double opt in for newsletter subscriptions' WHERE label='Double-opt-in for newsletter subscriptions';
UPDATE s_core_config_element_translations SET label='Double opt in for customer reviews' WHERE label='Double-opt-in for product evaluations';
UPDATE s_core_config_element_translations SET label='Order status - Changes to CC addresses' WHERE label='Order status - Chnages CC address';
UPDATE s_core_config_element_translations SET label='Block orders with no available shipping type' WHERE label='Block order with no available shipping type';
UPDATE s_core_config_element_translations SET label='Redirect to starting page in case of unavailable categories / items' WHERE label='Redirect to starting page in case of non-available categories/products ';
UPDATE s_core_config_element_translations SET label='Prepare meta description of categories / items' WHERE label='Prepare meta description od products/categories';
UPDATE s_core_config_element_translations SET label='SEO nofollow queries' WHERE label='SEO-Nofollow-Querys';
UPDATE s_core_config_element_translations SET label='SEO nofollow viewports' WHERE label='SEO-Nofollow Viewports';
UPDATE s_core_config_element_translations SET label='Remove unnecessary blank spaces or line breaks' WHERE label='Remove needless blank spaces or line breaks';
UPDATE s_core_config_element_translations SET label='Query aliases' WHERE label='Query-Aliase';
UPDATE s_core_config_element_translations SET label='SEO follow backlinks' WHERE label='SEO-Follow Backlinks';
UPDATE s_core_config_element_translations SET label='Use SEO canonical tags' WHERE label='Use SEO Canonical Tags';
UPDATE s_core_config_element_translations SET label='Last update (dd.mm.yyyy)' WHERE label='Date of last update';
UPDATE s_core_config_element_translations SET label='SEO URLs caching timetable' WHERE label='SEO-URLs caching time table';
UPDATE s_core_config_element_translations SET label='SEO URLs item template' WHERE label='SEO URLs product template';
UPDATE s_core_config_element_translations SET label='SEO URLs blog template' WHERE label='SEO-URLs blog template';
UPDATE s_core_config_element_translations SET label='SEO URLs landing page template' WHERE label='SEO-URLs landingpage template';
UPDATE s_core_config_element_translations SET label='Display "customers also bought" recommendations' WHERE label='Show Customers-also-bought-recommendation';
UPDATE s_core_config_element_translations SET label='Number of items per page in the list' WHERE label='Number of products per page in the list';
UPDATE s_core_config_element_translations SET label='Maximum number of pages in the list' WHERE label='Max. number of pages in the list.';
UPDATE s_core_config_element_translations SET label='Display "customers also viewed" recommendations' WHERE label='Show customers-also-viewed-recommendation';
UPDATE s_core_config_element_translations SET label='Number of items per page in the list' WHERE label='Number of products par page in the list';
UPDATE s_core_config_element_translations SET label='Display shop cancellation policy' WHERE label='Shop cancellation policy';
UPDATE s_core_config_element_translations SET label='Display newsletter registration' WHERE label='Show newsletter registration';
UPDATE s_core_config_element_translations SET label='Display bank detail notice' WHERE label='Show bank detail notice';
UPDATE s_core_config_element_translations SET label='Display further notices' WHERE label='Show further notices';
UPDATE s_core_config_element_translations SET label='Display further options' WHERE label='Show further options';
UPDATE s_core_config_element_translations SET label='Display "free with purchase" items' WHERE label='Show premium products';
UPDATE s_core_config_element_translations SET label='Display country descriptions' WHERE label='Show country descriptions';
UPDATE s_core_config_element_translations SET label='Display information for net orders' WHERE label='Show information for net orders';
UPDATE s_core_config_element_translations SET label='Display item details in modal box' WHERE label='Show product details in modal box';
UPDATE s_core_config_element_translations SET label='Shopping cart header background color' WHERE label='Background color of shopping cart-header';
UPDATE s_core_config_element_translations SET label='Shopping cart header text color' WHERE label='Text color of shopping cart header';
UPDATE s_core_config_element_translations SET label='Google Analytics ID' WHERE label='Google Analytics-ID';
UPDATE s_core_config_element_translations SET label='Google Conversion ID' WHERE label='Google Conversion-ID';
UPDATE s_core_config_element_translations SET label='Anonymous IP address' WHERE label='Anonymise IP address';
UPDATE s_core_config_element_translations SET label='Confirm customer email addresses', description='Customers must enter email addresses twice, in order to avoid typing mistakes.' WHERE label='E-mail address must be entered twice.';
UPDATE s_core_config_element_translations SET label='Hide "add to shopping cart" option if item is out-of-stock', description='Customers can choose to be informed per email when an item is "now in stock".' WHERE label='Hide shopping cart with e-mail notification';
UPDATE s_core_config_element_translations SET label='Automatically reminder customer to submit reviews', description='' WHERE label='Send automatical reminder of product evaluation';
UPDATE s_core_config_element_translations SET label='Days to wait before sending reminder', description='' WHERE label='Days until the reminder e-mail will be sent';

UPDATE s_core_config_element_translations SET label='Customer reviews must be approved' WHERE label='Product evaluations need to be unlocked';
UPDATE s_core_config_element_translations SET label='Item detail buffer time' WHERE label='Product detail page buffering time';
UPDATE s_core_config_element_translations SET label='Automatic item number suggestions' WHERE label='Automatical suggestion of product number';
UPDATE s_core_config_element_translations SET label='Prefix for automatically generated item numbers' WHERE label='Prefix für automatically generated product number';
UPDATE s_core_config_element_translations SET label='Require country with shipping address' WHERE label='Check country with shipping address';

INSERT IGNORE INTO s_core_translations (objecttype, objectdata, objectkey, objectlanguage) VALUES ('config_payment', 'a:4:{i:4;a:2:{s:11:"description";s:7:"Invoice";s:21:"additionalDescription";s:141:"Payment by invoice. Shopware provides automatic invoicing for all customers on orders after the first, in order to avoid defaults on payment.";}i:2;a:2:{s:11:"description";s:5:"Debit";s:21:"additionalDescription";s:15:"Additional text";}i:3;a:2:{s:11:"description";s:16:"Cash on delivery";s:21:"additionalDescription";s:25:"(including 2.00 Euro VAT)";}i:5;a:2:{s:11:"description";s:15:"Paid in advance";s:21:"additionalDescription";s:57:"The goods are delivered directly upon receipt of payment.";}}', 1, @localeID);
EOD;

        $this->addSql($sql);
    }
}
