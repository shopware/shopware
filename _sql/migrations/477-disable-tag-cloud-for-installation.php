<?php
class Migrations_Migration477 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        if ($modus !== self::MODUS_INSTALL) {
            return;
        }
        $this->fetchPluginId();
        $this->deleteConfigElements();
        $this->deleteListeners();
        $this->uninstallPlugin();
    }

    private function fetchPluginId()
    {
        $sql = <<<SQL
SET @pluginId = (
  SELECT id
  FROM s_core_plugins
  WHERE name LIKE "TagCloud"
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

    private function uninstallPlugin()
    {
        $this->addSql(
            "UPDATE s_core_plugins SET active = 0, installation_date = NULL, update_date = NULL WHERE id = @pluginId"
        );
    }

    private function deleteConfigElements()
    {
        $sql = <<<SQL
DELETE form, element, translation, value
FROM s_core_config_forms form
LEFT JOIN s_core_config_elements element ON element.form_id = form.id
LEFT JOIN s_core_config_element_translations translation ON translation.element_id = element.id
LEFT JOIN s_core_config_values value ON value.element_id = element.id
WHERE form.plugin_id = @pluginId
SQL;
        $this->addSql($sql);
    }
}
