<?php

class Migrations_Migration761 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
SET @parent = (SELECT id FROM s_core_menu WHERE name='Performance' and pluginID is NULL and class = 'sprite-bin-full settings--performance' LIMIT 1);
EOD;

        $this->addSql($sql);
        $sql = <<<'EOD'
INSERT INTO `s_core_menu` (`id`, `parent`, `name`, `onclick`, `class`, `position`, `active`, `pluginID`, `controller`, `shortcut`, `action`) VALUES 
(NULL, @parent, 'Performance', NULL, 'sprite-bin-full settings--performance', '2', '1', NULL, 'Performance', NULL, 'Index');
EOD;
        $this->addSql($sql);
    }
}
