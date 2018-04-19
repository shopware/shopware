<?php
class Migrations_Migration312 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
            SET @formId = (SELECT id FROM s_core_config_forms WHERE name = 'Search' LIMIT 1);

            UPDATE s_core_config_form_translations
            SET label = 'Search'
            WHERE locale_id = 2
            AND label = 'Smart Search'
            AND form_id = @formId;
EOD;
        $this->addSql($sql);
    }
}
