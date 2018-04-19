<?php
class Migrations_Migration602 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'SQL'
INSERT INTO `s_core_menu` (`parent`, `hyperlink`, `name`, `onclick`, `style`, `class`, `position`, `active`, `pluginID`, `resourceID`, `controller`, `shortcut`, `action`) VALUES
(23, '', 'Premium Plugins', NULL, NULL, 'sprite-star settings--premium-plugins', 0, 1, 56, NULL, 'PluginManager', NULL, 'PremiumPlugins');
SQL;

        $this->addSql($sql);
    }
}


