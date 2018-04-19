<?php

class Migrations_Migration700 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql("ALTER TABLE `s_filter_values` DROP `value_numeric`;");
        $this->addSql("ALTER TABLE `s_filter_options` DROP `default`;");
    }
}
