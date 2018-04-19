<?php
class Migrations_Migration428 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<SQL
DELETE element, translation, value
FROM s_core_config_forms form
LEFT JOIN s_core_config_elements element ON element.form_id = form.id
LEFT JOIN s_core_config_element_translations translation ON translation.element_id = element.id
LEFT JOIN s_core_config_values value ON value.element_id = element.id
WHERE form.name = "Frontend79" AND element.name='deactivatenoinstock';
SQL;
        $this->addSql($sql);
    }
}


