<?php

class Migrations_Migration710 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql("ALTER TABLE `s_user_billingaddress` DROP fax;");
        $this->addSql("ALTER TABLE `s_order_billingaddress` DROP fax;");
    }
}
