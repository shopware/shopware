<?php

class Migrations_Migration463 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<SQL
UPDATE `s_library_component`
SET `x_type` = 'emotion-components-html-video'
WHERE `name` = 'HTML5 Video-Element'
AND `template` = 'component_video';
SQL;
        $this->addSql($sql);
    }
}
