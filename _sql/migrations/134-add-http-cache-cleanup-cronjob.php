<?php
class Migrations_Migration134 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
SET @plugin_id = (SELECT id FROM s_core_plugins WHERE name='HttpCache');

INSERT IGNORE INTO `s_crontab` (`name`, `action`, `data`, `next`, `start`, `interval`, `active`, `end`, `inform_template`, `inform_mail`, `pluginID`)
VALUES ('HTTP Cache löschen', 'ClearHttpCache', '', CONCAT(CURDATE() + INTERVAL 1 DAY, ' 03:00:00'), NULL , '86400', '1', CONCAT(CURDATE() + INTERVAL 1 DAY, ' 03:00:00'), '', '', @plugin_id);

INSERT IGNORE INTO `s_core_subscribes` (`subscribe`, `type`, `listener`,`pluginID`, `position`)
VALUES ('Shopware_CronJob_ClearHttpCache', '0', 'Shopware_Plugins_Core_HttpCacheBootstrap::onClearHttpCache', @plugin_id, '0');
EOD;
        $this->addSql($sql);
    }
}
