<?php

class Migrations_Migration728 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        // create fields in s_user
        $sql = <<<SQL
        ALTER TABLE `s_user`
            ADD `title` varchar(100) NULL,
            ADD `salutation` varchar(30) NULL AFTER `title`,
            ADD `firstname` varchar(255) NULL AFTER `salutation`,
            ADD `lastname` varchar(255) NULL AFTER `firstname`,
            ADD `birthday` date NULL AFTER `lastname`;
SQL;
        $this->addSql($sql);
    }
}
