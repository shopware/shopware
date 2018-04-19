<?php
class Migrations_Migration416 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->removeDummyPlugins();
        $this->removeCapabilityDummy();
        $this->removeDummyDownloadUrl();
    }

    private function removeDummyPlugins()
    {
        $sql = <<<SQL
DELETE
FROM s_core_plugins
WHERE capability_dummy = 1
AND installation_date IS NULL;
SQL;
        $this->addSql($sql);
    }

    private function removeCapabilityDummy()
    {
        $sql = <<<SQL
ALTER TABLE s_core_plugins
DROP capability_dummy;
SQL;
        $this->addSql($sql);
    }

    private function removeDummyDownloadUrl()
    {
        $sql = <<<SQL
DELETE element, translation, value
FROM s_core_config_forms form
LEFT JOIN s_core_config_elements element ON element.form_id = form.id
LEFT JOIN s_core_config_element_translations translation ON translation.element_id = element.id
LEFT JOIN s_core_config_values value ON value.element_id = element.id
WHERE form.name='StoreApi'
AND element.name='DummyPluginUrl';
SQL;
        $this->addSql($sql);
    }
}
