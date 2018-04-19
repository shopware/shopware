<?php

class Migrations_Migration785 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->updateHelpMenuPosition();

        $sql = <<<'EOD'
SELECT id FROM s_core_menu WHERE name = "Connect" LIMIT 1;
EOD;
        $menuId = $this->connection->query($sql)->fetch();
        if ($menuId) {
            return;
        }

        $this->addConnectMenu();
    }

    private function updateHelpMenuPosition()
    {
        $sql = <<<'EOD'
    UPDATE `s_core_menu` SET `position`= 999 WHERE `name` = '' AND `class` = 'ico question_frame shopware-help-menu';
EOD;
        $this->addSql($sql);
    }

    private function addConnectMenu()
    {
        $sql = <<<'EOD'
INSERT INTO `s_core_menu` (`id`, `parent`, `name`, `onclick`, `class`, `position`, `active`, `pluginID`, `controller`, `shortcut`, `action`)
VALUES (NULL, NULL, 'Connect', NULL, 'shopware-connect', '0', '1', NULL, NULL, NULL, NULL);
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
SET @parent = (SELECT id FROM `s_core_menu` WHERE `name` = 'Connect');

INSERT INTO `s_core_menu` (`id`, `parent`, `name`, `onclick`, `class`, `position`, `active`, `pluginID`, `controller`, `shortcut`, `action`)
VALUES (NULL, @parent, 'Einstieg', NULL, 'sprite-mousepointer-click', '0', '1', NULL, 'PluginManager', NULL, 'ShopwareConnect');
EOD;
        $this->addSql($sql);
    }
}
