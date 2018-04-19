<?php
class Migrations_Migration387 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
            SET @formId = (SELECT id FROM s_core_config_forms WHERE name = 'Frontend79' LIMIT 1);

            INSERT IGNORE INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`) VALUES
                (@formId, 'basketShowCalculation', 'b:1;', 'Versandkostenberechnung im Warenkob anzeigen', 'Bei aktivierter Einstellung wird ein Versandkostenrechner auf der Warenkorbseite dargestellt. Diese Funktion ist nur für nicht angemeldete Kunden verfügbar.', 'boolean', 0, 0, 1, NULL, NULL);

            SET @elementId = (SELECT id FROM s_core_config_elements WHERE name = 'basketShowCalculation' LIMIT 1);

            INSERT INTO `s_core_config_element_translations` (`element_id`, `locale_id`, `label`, `description`)
                VALUES (@elementId, 2, 'Show shipping fee calculation in shopping cart', 'If enabled, a shipping cost calculator will be displayed in the cart page. This is only available for customers who haven''t logged in');
EOD;
        $this->addSql($sql);
    }
}



