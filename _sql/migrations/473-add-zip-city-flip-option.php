<?php
class Migrations_Migration473 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
        SET @form = (SELECT id FROM s_core_config_forms WHERE name = 'Frontend33' LIMIT 1);
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
            INSERT IGNORE INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`) VALUES
            (@form, 'showZipBeforeCity', 'b:1;', 'PLZ vor dem Stadtfeld anzeigen', 'Legt fest ob die PLZ vor oder nach der Stadt angezeigt werden soll. Nur für Shopware 5 Themes.', 'checkbox', 0, 0, 1, NULL, NULL, 'a:0:{}');
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        SET @elementId = (SELECT id FROM `s_core_config_elements` WHERE `name` = 'showZipBeforeCity' LIMIT 1);
EOD;
        $this->addSql($sql);

        $sql = <<<'EOD'
        INSERT IGNORE INTO `s_core_config_element_translations` (`element_id`, `locale_id`, `label`, `description`)
        VALUES (@elementId, '2', 'Show zip code field before city field', 'Determines if the zip code field should be shown before or after the the city field. Only applicable for Shopware 5 themes');
EOD;
        $this->addSql($sql);
    }
}


