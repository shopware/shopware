<?php

class Migrations_Migration427 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<SQL
        SET @formId = (SELECT id FROM s_core_config_forms WHERE name = 'Frontend79' LIMIT 1);
SQL;
        $this->addSql($sql);

        $sql = <<<SQL
            INSERT IGNORE INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`) VALUES
            (@formId, 'hideNoInStock', 'b:0;', 'Abverkaufsartikel ohne Lagerbestand nicht anzeigen', null, 'checkbox', 0, 0, 0);
SQL;
        $this->addSql($sql);

        $sql = <<<SQL
        SET @elementId = (SELECT id FROM `s_core_config_elements` WHERE `name` = 'hideNoInStock' LIMIT 1);
SQL;
        $this->addSql($sql);

        $sql = <<<SQL
        INSERT IGNORE INTO `s_core_config_element_translations` (`element_id`, `locale_id`, `label`)
        VALUES (@elementId, '2', 'Do not show on sale products that are out of stock ');
SQL;
        $this->addSql($sql);
    }
}
