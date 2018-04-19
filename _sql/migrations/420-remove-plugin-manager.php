<?php
class Migrations_Migration420 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->fetchPluginId();
        $this->deleteListeners();
        $this->deleteMenuItem();
        $this->deletePlugin();
    }

    private function fetchPluginId()
    {
        $sql = <<<SQL
SET @pluginId = (
  SELECT id
  FROM s_core_plugins
  WHERE name LIKE "PluginManager"
  AND author LIKE "shopware AG"
  LIMIT 1
);
SQL;
        $this->addSql($sql);
    }

    private function deleteListeners()
    {
        $this->addSql(
            "DELETE FROM s_core_subscribes WHERE pluginID = @pluginId"
        );
    }

    private function deleteMenuItem()
    {
        $this->addSql(
            "DELETE FROM s_core_menu WHERE pluginID = @pluginId"
        );
    }

    private function deletePlugin()
    {
        $this->addSql(
            "DELETE FROM s_core_plugins WHERE id = @pluginId"
        );
    }
}
