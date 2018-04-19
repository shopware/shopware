<?php

class Migrations_Migration478 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = "SET @componentId = (SELECT `id` FROM `s_library_component` WHERE `x_type` = 'emotion-components-banner' LIMIT 1);";
        $this->addSql($sql);

        $sql = <<<EOD
INSERT INTO `s_library_component_field` (`componentId`, `name`, `x_type`, `field_label`, `allow_blank`, `position`)
VALUES ( @componentId, 'title', 'textfield', 'Title Text', 1, 50 );
EOD;

        $this->addSql($sql);
    }
}