<?php
class Migrations_Migration203 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
SET @plugin_id = (SELECT id FROM s_core_plugins WHERE name='HttpCache');
INSERT IGNORE INTO `s_core_subscribes` (`subscribe`, `type`, `listener`, `pluginID`, `position`) VALUES ('Shopware_Plugins_HttpCache_ClearCache', '0', 'Shopware_Plugins_Core_HttpCache_Bootstrap::onClearCache', @plugin_id, '0');
EOD;
        $this->addSql($sql);
    }
}
