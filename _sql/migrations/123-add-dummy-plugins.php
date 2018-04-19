<?php
class Migrations_Migration123 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
ALTER TABLE s_core_plugins ADD capability_dummy INT(1) NOT NULL DEFAULT 0 AFTER capability_enable;

SET @formId = (SELECT id FROM s_core_config_forms WHERE name='StoreApi');
INSERT IGNORE INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`) VALUES
(@formId, 'DummyPluginUrl', 's:71:"http://store.shopware.de/downloads/free/plugin/%name%/version/%version%";', 'Dummyplugin Download Url', NULL, 'text', 0, 0, 1);
EOD;

        $this->addSql($sql);
    }
}
