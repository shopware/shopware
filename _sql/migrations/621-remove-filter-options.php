<?php

class Migrations_Migration621 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<EOD
DELETE s_core_config_elements, s_core_config_element_translations, s_core_config_values
FROM s_core_config_elements
LEFT JOIN s_core_config_element_translations ON s_core_config_element_translations.element_id = s_core_config_elements.id
LEFT JOIN s_core_config_values ON s_core_config_values.element_id = s_core_config_elements.id
WHERE s_core_config_elements.form_id = 0 AND name = "propertySorting"
EOD;
        $this->addSql($sql);

        $sql = <<<EOD
DELETE s_core_config_elements, s_core_config_element_translations, s_core_config_values
FROM s_core_config_elements
LEFT JOIN s_core_config_element_translations ON s_core_config_element_translations.element_id = s_core_config_elements.id
LEFT JOIN s_core_config_values ON s_core_config_values.element_id = s_core_config_elements.id
WHERE s_core_config_elements.form_id = 0 AND name = "displayFiltersOnDetailPage"
EOD;
        $this->addSql($sql);
    }
}
