<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\ImportExport\ImportExportProfileTranslationDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;
use Shopware\Core\Migration\Traits\Translations;

/**
 * @internal
 */
#[Package('core')]
class Migration1627983652AddOrderExportProfile extends MigrationStep
{
    use ImportTranslationsTrait;

    public function getCreationTimestamp(): int
    {
        return 1627983652;
    }

    public function update(Connection $connection): void
    {
        $id = Uuid::randomBytes();

        $connection->insert('import_export_profile', [
            'id' => $id,
            'name' => 'Default orders',
            'system_default' => 1,
            'source_entity' => 'order',
            'file_type' => 'text/csv',
            'delimiter' => ';',
            'enclosure' => '"',
            'type' => 'export',
            // TODO: add required fields for mappings
            'mapping' => json_encode([
                ['key' => 'id', 'mappedKey' => 'id'],
                ['key' => 'orderNumber', 'mappedKey' => 'order_number'],
                ['key' => 'salesChannelId', 'mappedKey' => 'sales_channel_id'],
                ['key' => 'orderCustomer.firstName', 'mappedKey' => 'customer_firstname'],
                ['key' => 'orderCustomer.lastName', 'mappedKey' => 'customer_lastname'],
                ['key' => 'orderCustomer.email', 'mappedKey' => 'customer_email'],
                ['key' => 'billingAddress.street', 'mappedKey' => 'billing_address_street'],
                ['key' => 'billingAddress.zipcode', 'mappedKey' => 'billing_address_zipcode'],
                ['key' => 'billingAddress.city', 'mappedKey' => 'billing_address_city'],
                ['key' => 'billingAddress.company', 'mappedKey' => 'billing_address_company'],
                ['key' => 'billingAddress.department', 'mappedKey' => 'billing_address_department'],
                ['key' => 'billingAddress.countryId', 'mappedKey' => 'billing_address_country_id'],
                ['key' => 'billingAddress.countryStateId', 'mappedKey' => 'billing_address_country_state_id'],
                ['key' => 'deliveries.shippingOrderAddress.street', 'mappedKey' => 'shipping_address_street'],
                ['key' => 'deliveries.shippingOrderAddress.zipcode', 'mappedKey' => 'shipping_address_zipcode'],
                ['key' => 'deliveries.shippingOrderAddress.city', 'mappedKey' => 'shipping_address_city'],
                ['key' => 'deliveries.shippingOrderAddress.company', 'mappedKey' => 'shipping_address_company'],
                ['key' => 'deliveries.shippingOrderAddress.department', 'mappedKey' => 'shipping_address_department'],
                ['key' => 'deliveries.shippingOrderAddress.countryId', 'mappedKey' => 'shipping_address_country_id'],
                ['key' => 'deliveries.shippingOrderAddress.countryStateId', 'mappedKey' => 'shipping_address_country_state_id'],
                ['key' => 'amountTotal', 'mappedKey' => 'amount_total'],
                ['key' => 'stateId', 'mappedKey' => 'order_state_id'],
                ['key' => 'lineItems', 'mappedKey' => 'line_items'],
                ['key' => 'orderDateTime', 'mappedKey' => 'order_date_time'],
            ]),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $translations = new Translations(
            [
                'import_export_profile_id' => $id,
                'label' => 'Standardprofil Bestellungen',
            ],
            [
                'import_export_profile_id' => $id,
                'label' => 'Default orders',
            ]
        );

        $this->importTranslation(ImportExportProfileTranslationDefinition::ENTITY_NAME, $translations, $connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
