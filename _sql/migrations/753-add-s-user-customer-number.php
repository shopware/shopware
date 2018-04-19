<?php

class Migrations_Migration753 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql("ALTER TABLE `s_user` ADD `customernumber` VARCHAR(30) NULL DEFAULT NULL;");
    }
}
