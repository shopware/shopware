<?php
class Migrations_Migration133 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
        SET @formId = (SELECT id FROM `s_core_config_forms` WHERE `name` = 'Esd' LIMIT 1);
        INSERT IGNORE INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `description`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`)
        VALUES (@formId, 'downloadAvailablePaymentStatus', 'a:1:{i:0;i:12;}', 'Download freigeben bei Zahlstatus', 'Definieren Sie hier die Zahlstatus bei dem ein Download des ESD-Artikels möglich ist.', 'select', '1', '3', '0', NULL, NULL, 'a:4:{s:5:"store";s:18:"base.PaymentStatus";s:12:"displayField";s:11:"description";s:10:"valueField";s:2:"id";s:11:"multiSelect";b:1;}');

        SET @elementId = (SELECT id FROM `s_core_config_elements` WHERE `name` = 'downloadAvailablePaymentStatus' LIMIT 1);
        INSERT IGNORE INTO `s_core_config_element_translations` (`element_id`, `locale_id`, `label`, `description`)
        VALUES (@elementId, '2', ' Release download with payment status', 'Define the payment status in which a download of ESD items is possible.');
EOD;
        $this->addSql($sql);
    }
}
