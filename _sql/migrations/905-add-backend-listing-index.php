<?php

class Migrations_Migration905 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql("ALTER TABLE `s_order` ADD INDEX (`ordernumber`,`status`)");
        $this->addSql("ALTER TABLE `s_order` ADD INDEX (`invoice_amount`);");
    }
}
