<?php

class Migrations_Migration754 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql("ALTER TABLE `s_order_billingaddress` CHANGE `customernumber` `customernumber` VARCHAR(30) NULL DEFAULT NULL;");
    }
}
