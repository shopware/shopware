<?php
class Migrations_Migration218 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        // remove old plugins
        $this->deletePluginByName('BenchmarkEvents');
        $this->deletePluginByName('Benchmark');

        // remove debug plugin to have a clean reinstallation (ordered form elements etc.)
        $this->deletePluginByName('Debug');

        // insert debug plugin into plugin manager
        $sql = <<<'EOD'
INSERT IGNORE INTO `s_core_plugins` (`namespace`, `name`, `label`, `source`, `description`, `description_long`, `active`, `added`, `installation_date`, `update_date`, `refresh_date`, `author`, `copyright`, `license`, `version`, `support`, `changes`, `link`, `store_version`, `store_date`, `capability_update`, `capability_install`, `capability_enable`, `capability_dummy`, `update_source`, `update_version`) VALUES ('Core', 'Debug', 'Debug', 'Default', NULL, NULL, '0', '2014-01-17 09:19:05', NULL, NULL, '2014-01-17 09:19:07', 'shopware AG', 'Copyright © shopware AG', NULL, '1.0.0', NULL, NULL, NULL, NULL, NULL, '1', '1', '1', '0', NULL, NULL);
EOD;
        $this->addSql($sql);
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
