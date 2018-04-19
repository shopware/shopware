<?php

class Migrations_Migration496 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<SQL
SET @formId = (SELECT id FROM `s_core_config_forms` WHERE `name` = 'Frontend30' LIMIT 1);

INSERT INTO `s_core_config_elements` (`id`, `form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`)
VALUES (NULL, @formId, 'useLastGraduationForCheapestPrice', 'b:0;', 'Staffelpreise in der Günstigsten Preis Berechnung berücksichtigen', NULL, 'checkbox', '0', '0', '1', NULL, NULL);
SQL;
        $this->addSql($sql);

        $sql = <<<SQL
SET @elementId = (SELECT id FROM `s_core_config_elements` WHERE `name` = 'useLastGraduationForCheapestPrice' LIMIT 1);

INSERT INTO `s_core_config_element_translations` (`id`, `element_id`, `locale_id`, `label`, `description`)
VALUES (NULL, @elementId, '2', 'Consider product graduatation for cheapest price calculation', NULL);
SQL;
        $this->addSql($sql);

    }
}
