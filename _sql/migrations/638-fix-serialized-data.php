<?php

class Migrations_Migration638 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql('UPDATE s_core_config_elements SET value = CONCAT(value, ";") WHERE value LIKE "s%" AND value NOT LIKE "%;";');
        $this->addSql('UPDATE s_core_config_values SET value = CONCAT(value, ";") WHERE value LIKE "s%" AND value NOT LIKE "%;";');
    }
}
