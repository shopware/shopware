<?php

class Migrations_Migration745 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql('ALTER TABLE `s_user_shippingaddress` ADD `title` VARCHAR(100) NULL DEFAULT NULL AFTER `additional_address_line2`;');
    }
}
