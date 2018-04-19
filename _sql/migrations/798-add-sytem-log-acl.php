<?php

class Migrations_Migration798 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql("SET @resourceId = (SELECT `id` FROM `s_core_acl_resources` WHERE `name` = 'log');");

        $this->addSql('INSERT INTO `s_core_acl_privileges` (`resourceID`, `name`) VALUES (@resourceId, \'system\');');
    }
}
