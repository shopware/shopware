<?php

class Migrations_Migration482 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<SQL
DELETE FROM `s_emotion_templates` WHERE `name` = 'Horizontales Scrolling';
DELETE FROM `s_emotion_grid` WHERE `name` = 'Horizontales Scrolling';
SQL;
        $this->addSql($sql);
    }
}
