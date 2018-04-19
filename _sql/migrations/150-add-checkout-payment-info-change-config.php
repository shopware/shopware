<?php
class Migrations_Migration150 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
        SET @parent = (SELECT id FROM s_core_config_forms WHERE name = 'Checkout' LIMIT 1);

        INSERT IGNORE INTO `s_core_config_elements`
        (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`)
        VALUES
        (@parent, 'paymentEditingInCheckoutPage', 'b:0;', 'Lastschriftdaten im Checkout editierbar', NULL, 'boolean', 0, 1, 1, NULL, NULL, NULL);

        SET @elementOne = (SELECT id FROM s_core_config_elements WHERE name = 'paymentEditingInCheckoutPage' LIMIT 1);

        INSERT IGNORE INTO `s_core_config_element_translations` (`id`, `element_id`, `locale_id`, `label`, `description`)
        VALUES
        (NULL, @elementOne, '2', 'Allow payment details editing on checkout page', NULL);
EOD;
        $this->addSql($sql);
    }
}
