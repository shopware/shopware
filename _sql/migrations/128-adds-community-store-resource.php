<?php
class Migrations_Migration128 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
INSERT INTO `s_core_subscribes` (`subscribe`, `type`, `listener`, `pluginID`) VALUES
('Enlight_Bootstrap_InitResource_CommunityStore', '0', 'Shopware_Plugins_Core_PluginManager_Bootstrap::onInitCommunityStore', (SELECT `id` FROM `s_core_plugins` WHERE `name`='PluginManager'));
EOD;

        $this->addSql($sql);
    }
}
