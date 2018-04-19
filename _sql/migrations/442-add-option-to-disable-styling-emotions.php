<?php
class Migrations_Migration442 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
SET @parent = (SELECT id FROM `s_library_component` WHERE `cls`='html-text-element' LIMIT 1);
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
INSERT IGNORE INTO `s_library_component_field` (`componentID`, `name`, `x_type`, `value_type`, `field_label`, `support_text`, `help_title`, `help_text`, `store`, `display_field`, `value_field`, `default_value`, `allow_blank`, `position`)
VALUES (@parent, 'needsNoStyling', 'checkbox', '', 'Kein Styling hinzufügen', 'Definiert, dass kein weiteres Layout-Styling hinzugefügt wird.', '', '', '', '', '', '0', '0', 10);
EOD;
        $this->addSql($sql);

    }
}