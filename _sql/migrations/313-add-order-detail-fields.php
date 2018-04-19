<?php
class Migrations_Migration313 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql('
            ALTER TABLE `s_order_details`
            ADD `ean` VARCHAR(255) NULL DEFAULT NULL ,
            ADD `unit` VARCHAR(255) NULL DEFAULT NULL ;
        ');
    }
}
