<?php

class Migrations_Migration709 extends Shopware\Framework\Migration\AbstractMigration
{
    /**
     * @param string $modus
     * @return void
     */
    public function up($modus)
    {
        $this->changeConfusingVatLabel();
        $this->createDefaultShippingBillingRelations();
        $this->createAddressTable();
        $this->removeCompanySalutation();
    }

    private function changeConfusingVatLabel()
    {
        $sql = <<<SQL
UPDATE
  s_core_config_elements origin
JOIN
  s_core_config_element_translations translation
  ON origin.id = translation.element_id AND translation.locale_id = 2
SET
  origin.label = 'USt-IdNr. für Firmenkunden als Pflichtfeld markieren',
  translation.label = 'Mark VAT ID number as required for company customers'
WHERE
  origin.name = 'vatcheckrequired'
SQL;
        $this->addSql($sql);
    }

    private function createDefaultShippingBillingRelations()
    {
        $sql = <<<SQL
ALTER TABLE `s_user`
ADD `default_billing_address_id` int(11) DEFAULT NULL,
ADD `default_shipping_address_id` int(11) DEFAULT NULL AFTER `default_billing_address_id`,
ADD INDEX `default_billing_address_id` (`default_billing_address_id`),
ADD INDEX `default_shipping_address_id` (`default_shipping_address_id`);
SQL;

        $this->addSql($sql);
    }

    private function createAddressTable()
    {
        $sql = <<<SQL
CREATE TABLE `s_user_addresses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `company` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `department` varchar(35) COLLATE utf8_unicode_ci DEFAULT NULL,
  `salutation` varchar(30) COLLATE utf8_unicode_ci NOT NULL,
  `title` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `firstname` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `lastname` varchar(60) COLLATE utf8_unicode_ci NOT NULL,
  `street` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `zipcode` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `city` varchar(70) COLLATE utf8_unicode_ci NOT NULL,
  `country_id` int(11) NOT NULL,
  `state_id` int(11) DEFAULT NULL,
  `ustid` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `phone` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `additional_address_line1` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `additional_address_line2` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `original_type` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `original_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `country_id` (`country_id`),
  KEY `state_id` (`state_id`),
  CONSTRAINT `s_user_addresses_ibfk_1` FOREIGN KEY (`country_id`) REFERENCES `s_core_countries` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `s_user_addresses_ibfk_2` FOREIGN KEY (`state_id`) REFERENCES `s_core_countries_states` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `s_user_addresses_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `s_user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
SQL;

        $this->addSql($sql);
    }

    private function removeCompanySalutation()
    {
        $this->addSql("UPDATE `s_user_billingaddress` SET salutation = 'mr' WHERE salutation = 'company';");
        $this->addSql("UPDATE `s_user_shippingaddress` SET salutation = 'mr' WHERE salutation = 'company';");
        $this->addSql("UPDATE `s_order_billingaddress` SET salutation = 'mr' WHERE salutation = 'company';");
        $this->addSql("UPDATE `s_order_shippingaddress` SET salutation = 'mr' WHERE salutation = 'company';");
    }
}
