<?php

class Migrations_Migration455 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        if ($modus === self::MODUS_INSTALL) {
            return;
        }

        $statement = $this->getConnection()->prepare("SELECT id FROM s_core_plugins WHERE name = 'HttpCache' AND active = 1 LIMIT 1;");
        $statement->execute();
        $data = $statement->fetchAll();

        if (empty($data)) {
            return;
        }

        $sql = "SET @pluginID = (SELECT id FROM s_core_plugins WHERE name = 'HttpCache' LIMIT 1);";
        $this->addSql($sql);
        
        $sql = <<<'SQL'
INSERT IGNORE INTO `s_core_subscribes` (`subscribe`, `type`, `listener`, `pluginID`, `position`) VALUES
('Shopware\\Models\\Article\\Detail::postUpdate', 0, 'Shopware_Plugins_Core_HttpCache_Bootstrap::onPostPersist', @pluginID, 0);
SQL;

        $this->addSql($sql);
        
        $sql = <<<'SQL'
INSERT IGNORE INTO `s_core_subscribes` (`subscribe`, `type`, `listener`, `pluginID`, `position`) VALUES
('Shopware\\Models\\Article\\Detail::postPersist', 0, 'Shopware_Plugins_Core_HttpCache_Bootstrap::onPostPersist', @pluginID, 0);
SQL;

        $this->addSql($sql);
    }

}
