<?php

class Migrations_Migration782 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql("ALTER TABLE `s_emarketing_partner` ADD INDEX `idcode` (`idcode`);");
    }
}
