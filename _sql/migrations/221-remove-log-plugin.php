<?php
class Migrations_Migration221 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->deletePluginByName('Log');
    }

    public function deletePluginByName($name)
    {
        $sql = <<<EOD
DELETE p, s, cf, ce, cev, cet
FROM s_core_plugins p
LEFT JOIN s_core_subscribes s
    ON p.id = s.pluginID
LEFT JOIN s_core_config_forms cf
    ON p.id = cf.plugin_id
LEFT JOIN s_core_config_elements ce
    on cf.id = ce.form_id
LEFT JOIN s_core_config_values cev
    on ce.id = cev.element_id
LEFT JOIN s_core_config_element_translations cet
    on ce.id = cet.element_id
WHERE p.name LIKE '$name'
EOD;

        $this->addSql($sql);
    }
}
