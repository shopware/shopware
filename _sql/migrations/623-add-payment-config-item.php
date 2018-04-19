<?php

class Migrations_Migration623 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<EOD
INSERT INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`)
VALUES ('256', 'always_select_payment', 'b:0;', 'Zahlungsart bei Bestellung immer auswählen', NULL, 'boolean', '0', '0', '1', NULL, NULL, NULL);
EOD;
        $this->addSql($sql);

        $sql = <<<EOD
INSERT INTO `s_core_config_element_translations` (`element_id`, `locale_id`, `label`, `description`)
VALUES ((SELECT id FROM `s_core_config_elements` WHERE name = 'always_select_payment'), '2', 'Always select payment method in checkout', NULL);
EOD;
        $this->addSql($sql);
    }
}
