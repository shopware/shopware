<?php

class Migrations_Migration456 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $statement = $this->getConnection()->prepare("SELECT id FROM s_core_plugins WHERE `name` = 'HttpCache' AND installation_date IS NOT NULL LIMIT 1;");
        $statement->execute();
        $data = $statement->fetchAll();

        if (empty($data)) {
            return;
        }

        $sql = "SET @pluginID = (SELECT id FROM s_core_plugins WHERE `name` = 'HttpCache' AND installation_date IS NOT NULL LIMIT 1);";
        $this->addSql($sql);

        $sql = <<<'SQL'
INSERT IGNORE INTO `s_core_subscribes` (`subscribe`, `type`, `listener`, `pluginID`, `position`) VALUES
('Shopware\\Models\\Emotion\\Emotion::postUpdate', 0, 'Shopware_Plugins_Core_HttpCache_Bootstrap::onPostPersist', @pluginID, 0);
SQL;

        $this->addSql($sql);

        $sql = <<<'SQL'
INSERT IGNORE INTO `s_core_subscribes` (`subscribe`, `type`, `listener`, `pluginID`, `position`) VALUES
('Shopware\\Models\\Emotion\\Emotion::postPersist', 0, 'Shopware_Plugins_Core_HttpCache_Bootstrap::onPostPersist', @pluginID, 0);
SQL;

        $this->addSql($sql);
    }

}