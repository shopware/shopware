<?php
class Migrations_Migration500 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'SQL'
INSERT IGNORE INTO `s_core_subscribes` (`subscribe`, `type`, `listener`, `pluginID`, `position`) VALUES
('Enlight_Controller_Front_DispatchLoopShutdown', 0, 'Shopware_Plugins_Core_System_Bootstrap::onDispatchLoopShutdown', 10, 0);
SQL;

        $this->addSql($sql);
    }
}


