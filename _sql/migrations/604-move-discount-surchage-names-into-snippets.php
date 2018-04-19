<?php

class Migrations_Migration604 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->hideBasicSettings();

        if ($modus === self::MODUS_INSTALL) {
            return;
        }

        $this->migrateCoreSettingsToSnippets();
    }

    private function hideBasicSettings()
    {
        $this->addSql(
            "UPDATE `s_core_config_elements` SET form_id = 0 WHERE `name` IN (
                'discountname', 'paymentSurchargeAbsolute',
                'paymentsurchargeadd', 'paymentsurchargedev',
                'shippingdiscountname', 'surchargename', 'vouchername'
            )"
        );
    }

    private function migrateCoreSettingsToSnippets()
    {
        $statement = $this->getConnection()->query(
            "SELECT
                s_core_config_elements.name,
                s_core_config_values.shop_id as shop_id,
                s_core_shops.locale_id as locale_id,
                s_core_config_values.value
            FROM `s_core_config_values`
            LEFT JOIN s_core_shops ON s_core_config_values.shop_id = s_core_shops.id
            LEFT JOIN s_core_config_elements ON s_core_config_values.element_id = s_core_config_elements.id
            WHERE s_core_config_elements.`name` IN (
                'discountname', 'paymentSurchargeAbsolute',
                'paymentsurchargeadd', 'paymentsurchargedev',
                'shippingdiscountname', 'surchargename', 'vouchername'
            )"
        );
        $data = $statement->fetchAll();

        if (empty($data)) {
            return;
        }

        $statement = $this->getConnection()->prepare(
            "INSERT INTO `s_core_snippets` (`namespace`, `shopID`, `localeID`, `name`, `value`, `created`, `updated`, `dirty`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE `value` = ?, dirty = 1, `updated` = ?;"
        );

        foreach ($data as $configValue) {
            if (
                empty($configValue['name'])
                || empty($configValue['value'])
                || empty($configValue['shop_id'])
                || empty($configValue['locale_id'])
            ) {
                continue;
            }

            $configValue['value'] = unserialize($configValue['value']);
            $configValue['name'] = $this->getSnippetName($configValue['name']);

            $dateString = date('Y-m-d H:i:s', time());
            $values = [
                'backend/static/discounts_surcharges',
                $configValue['shop_id'],
                $configValue['locale_id'],
                $configValue['name'],
                $configValue['value'],
                $dateString,
                $dateString,
                1,
                $configValue['value'],
                $dateString
            ];

            $statement->execute($values);
        }
    }

    private function getSnippetName($configName)
    {
        $matches = [
            'discountname' => 'discount_name',
            'paymentSurchargeAbsolute' => 'payment_surcharge_absolute',
            'paymentsurchargeadd' => 'payment_surcharge_add',
            'paymentsurchargedev' => 'payment_surcharge_dev',
            'shippingdiscountname' => 'shipping_discount_name',
            'surchargename' => 'surcharge_name',
            'vouchername' => 'voucher_name'
        ];

        return array_key_exists($configName, $matches) ? $matches[$configName] : null;
    }
}
