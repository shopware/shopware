<?php
class Migrations_Migration454 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = "
UPDATE s_core_menu SET name = 'Theme Manager' WHERE controller = 'Theme' and name = 'Theme Manager 2.0'
        ";
        $this->addSql($sql);

    }
}
