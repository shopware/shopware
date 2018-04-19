<?php

class Migrations_Migration786 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
SET @plugin_id = (SELECT id FROM s_core_plugins WHERE name='HttpCache');
EOD;

        $this->addSql($sql);

        $sql = <<<'EOD'
INSERT IGNORE INTO `s_core_subscribes` (`subscribe`, `type`, `listener`, `pluginID`, `position`) VALUES
('Shopware\\Models\\Article\\Price::postPersist', '0', 'Shopware_Plugins_Core_HttpCache_Bootstrap::onPostPersist', @plugin_id, '0'),
('Shopware\\Models\\Article\\Price::postUpdate', '0', 'Shopware_Plugins_Core_HttpCache_Bootstrap::onPostPersist', @plugin_id, '0'),
('Shopware\\Models\\Article\\Detail::postPersist', '0', 'Shopware_Plugins_Core_HttpCache_Bootstrap::onPostPersist', @plugin_id, '0'),
('Shopware\\Models\\Article\\Detail::postUpdate', '0', 'Shopware_Plugins_Core_HttpCache_Bootstrap::onPostPersist', @plugin_id, '0');
EOD;

        $this->addSql($sql);
    }
}
