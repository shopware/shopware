<?php

class Migrations_Migration481 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<SQL
UPDATE s_core_config_elements
SET form_id = (SELECT id FROM `s_core_config_forms` WHERE `name`='Frontend33' LIMIT 1)
WHERE name = 'vatcheckrequired';
SQL;
        $this->addSql($sql);

        $sql = <<<SQL
DELETE form, form_trans, elem, elem_trans
FROM s_core_config_forms form
LEFT JOIN s_core_config_form_translations form_trans ON form.id = form_trans.form_id
LEFT JOIN s_core_config_elements elem ON form.id = elem.form_id
LEFT JOIN s_core_config_element_translations elem_trans ON elem.id = elem_trans.element_id
WHERE form.name = 'Frontend101';
SQL;
        $this->addSql($sql);
    }
}
