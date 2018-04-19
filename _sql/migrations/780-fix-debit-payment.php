<?php

class Migrations_Migration780 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql("UPDATE `s_core_paymentmeans` SET `table` = '' WHERE `name` = 'debit' AND `table` = 's_user_debit';");
    }
}
