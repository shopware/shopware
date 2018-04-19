<?php
class Migrations_Migration395 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql("SET @formId = (SELECT id FROM s_core_config_forms WHERE name LIKE 'TrustedShop' LIMIT 1)");

        $this->addSql("
            DELETE FROM s_core_config_element_translations
            WHERE element_id =
            (SELECT id FROM s_core_config_elements WHERE form_id = @formId)"
        );

        $this->addSql("DELETE FROM s_core_config_elements WHERE form_id = @formId");

        $this->addSql("DELETE FROM s_core_config_form_translations WHERE form_id = @formId");
        $this->addSql("DELETE FROM s_core_config_forms WHERE id = @formId");

    }
}
