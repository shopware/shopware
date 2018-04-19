<?php

class Migrations_Migration462 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql("ALTER TABLE `s_emotion` ADD `position` INT NULL DEFAULT 1");
    }
}
