<?php
class Migrations_Migration406 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {

        $sql = <<<'EOD'
UPDATE `s_core_plugins` SET `label`='Erweitertes Menü' WHERE `name`='AdvancedMenu';
EOD;

        $this->addSql($sql);

        $statement = $this->connection->query("SELECT DISTINCT `id` FROM `s_core_plugins` WHERE `name`='AdvancedMenu' AND `installation_date` IS NOT NULL");

        $result = $statement->fetch(PDO::FETCH_NUM);

        if (empty($result)) {
            return;
        }

        $sql = <<<'EOD'
SET @pluginID = (SELECT DISTINCT `id` FROM `s_core_plugins` WHERE `name`='AdvancedMenu');
EOD;

        $this->addSql($sql);

        $sql = <<<'EOD'
INSERT IGNORE INTO `s_core_subscribes` (`subscribe`, `listener`, `pluginID`) VALUES
('Theme_Compiler_Collect_Plugin_Javascript', 'Shopware_Plugins_Frontend_AdvancedMenu_Bootstrap::onCollectJavascriptFiles', @pluginID),
('Theme_Compiler_Collect_Plugin_Less', 'Shopware_Plugins_Frontend_AdvancedMenu_Bootstrap::onCollectLessFiles', @pluginID);
EOD;

        $this->addSql($sql);

        $sql = <<<'EOD'
UPDATE `s_core_subscribes` SET `subscribe`='Enlight_Controller_Action_PostDispatchSecure_Frontend' WHERE `pluginID`=@pluginID AND `subscribe`='Enlight_Controller_Action_PostDispatch';
EOD;

        $this->addSql($sql);

        $sql = <<<'EOD'
INSERT IGNORE INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `type`, `required`, `position`, `scope`, `options`) VALUES
((SELECT DISTINCT `id` FROM `s_core_config_forms` WHERE `plugin_id`=@pluginID),
'columnAmount',
'i:2;',
'Breite des Teasers',
'select',
0,
0,
0,
'a:1:{s:5:"store";a:5:{i:0;a:2:{i:0;i:0;i:1;s:2:"0%";}i:1;a:2:{i:0;i:1;i:1;s:3:"25%";}i:2;a:2:{i:0;i:2;i:1;s:3:"50%";}i:3;a:2:{i:0;i:3;i:1;s:3:"75%";}i:4;a:2:{i:0;i:4;i:1;s:4:"100%";}}}'
);
EOD;

        $this->addSql($sql);
    }
}