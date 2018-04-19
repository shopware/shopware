<?php

class Migrations_Migration494 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<SQL
SET @formId = (SELECT id FROM `s_core_config_forms` WHERE `name` = 'Frontend30' LIMIT 1);

INSERT INTO `s_core_config_elements` (`id`, `form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`)
VALUES (NULL, @formId, 'calculateCheapestPriceWithMinPurchase', 'b:0;', 'Mindestabnahme bei der Günstigsten-Preis-Berechnung berücksichtigen', NULL, 'checkbox', '0', '0', '1', NULL, NULL);
SQL;
        $this->addSql($sql);

        $sql = <<<SQL
SET @elementId = (SELECT id FROM `s_core_config_elements` WHERE `name` = 'calculateCheapestPriceWithMinPurchase' LIMIT 1);

INSERT INTO `s_core_config_element_translations` (`id`, `element_id`, `locale_id`, `label`, `description`)
VALUES (NULL, @elementId, '2', 'Consider product minimum order quantity for cheapest price calculation', NULL);
SQL;
        $this->addSql($sql);

    }
}
