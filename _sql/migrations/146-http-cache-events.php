<?php
class Migrations_Migration146 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
SET @plugin_id = (SELECT id FROM s_core_plugins WHERE name='HttpCache');

INSERT IGNORE INTO `s_core_subscribes` (`subscribe`, `type`, `listener`, `pluginID`, `position`) VALUES ('Shopware_Plugins_HttpCache_InvalidateCacheId', '0', 'Shopware_Plugins_Core_HttpCache_Bootstrap::onInvalidateCacheId', @plugin_id, '0');
INSERT IGNORE INTO `s_core_subscribes` (`subscribe`, `type`, `listener`, `pluginID`, `position`) VALUES ('Enlight_Controller_Action_PreDispatch', '0', 'Shopware_Plugins_Core_HttpCache_Bootstrap::onPreDispatch', @plugin_id, '0');
INSERT IGNORE INTO `s_core_subscribes` (`subscribe`, `type`, `listener`, `pluginID`, `position`) VALUES ('Shopware\\Models\\Blog\\Blog::postPersist', '0', 'Shopware_Plugins_Core_HttpCache_Bootstrap::onPostPersist', @plugin_id, '0');
INSERT IGNORE INTO `s_core_subscribes` (`subscribe`, `type`, `listener`, `pluginID`, `position`) VALUES ('Shopware\\Models\\Blog\\Blog::postUpdate', '0', 'Shopware_Plugins_Core_HttpCache_Bootstrap::onPostPersist', @plugin_id, '0');

DROP TABLE s_cache_log;
EOD;
        $this->addSql($sql);
    }
}
