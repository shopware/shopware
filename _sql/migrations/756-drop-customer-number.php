<?php

class Migrations_Migration756 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql("ALTER TABLE `s_user_billingaddress` DROP `customernumber`;");
    }
}
