<?php

class Migrations_Migration903 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->fetchContentMenuId();
        $this->addNewMenuEntry();
    }

    private function fetchContentMenuId()
    {
        $sql = <<<SQL
SET @parentId = (
  SELECT id
  FROM s_core_menu
  WHERE name like "Inhalte"
  AND controller like "Content"
  LIMIT 1
);
SQL;
        $this->addSql($sql);
    }

    private function addNewMenuEntry()
    {
        $sql = <<<'EOD'
INSERT INTO `s_core_menu` (`id`, `parent`, `name`, `onclick`, `class`, `position`, `active`, `pluginID`, `controller`, `shortcut`, `action`)
VALUES (NULL, @parentId, 'Import/Export', NULL, 'sprite-arrow-circle-double-135 contents--import-export', '3', '1', NULL, 'PluginManager', NULL, 'ImportExport');
EOD;
        $this->addSql($sql);
    }
}
