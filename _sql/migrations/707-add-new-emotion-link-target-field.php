<?php

class Migrations_Migration707 extends Shopware\Framework\Migration\AbstractMigration
{
    /**
     * @param string $modus
     * @return void
     */
    public function up($modus)
    {
        $this->addSql("SET @componentID = (SELECT `id` FROM `s_library_component` WHERE `x_type` = 'emotion-components-banner' LIMIT 1);");

        $sql = <<<'EOD'
INSERT IGNORE INTO `s_library_component_field` (`componentID`, `name`, `x_type`, `field_label`, `allow_blank`, `position`) VALUES
(@componentID, 'banner_link_target', 'emotion-components-fields-link-target', 'Link-Ziel', 1, 48);
EOD;
        $this->addSql($sql);
    }
}
