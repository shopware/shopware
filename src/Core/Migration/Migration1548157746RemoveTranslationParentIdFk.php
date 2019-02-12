<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1548157746RemoveTranslationParentIdFk extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1548157746;
    }

    public function update(Connection $connection): void
    {
        foreach (array_keys($this->getTranslationTables()) as $tableName) {
            $this->alterForeignKeys($connection, $tableName);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        foreach ($this->getTranslationTables() as $tableName => $pks) {
            $drop = sprintf('
                ALTER TABLE `%s`
                DROP COLUMN `language_parent_id`',
                $tableName
            );
            $connection->executeQuery($drop);
        }
    }

    protected function getTableKeyAlias(string $tableName): string
    {
        $aliases = [
            'configuration_group_option_translation' => 'cgo_translations',
        ];

        return $aliases[$tableName] ?? $tableName;
    }

    protected function getTranslationTables(): array
    {
        return [
            'category_translation' => [
                'category_id',
                'category_version_id',
            ],
            'configuration_group_option_translation' => [
                'configuration_group_option_id',
            ],
            'configuration_group_translation' => [
                'configuration_group_id',
            ],
            'country_state_translation' => [
                'country_state_id',
            ],
            'country_translation' => [
                'country_id',
            ],
            'currency_translation' => [
                'currency_id',
            ],
            'customer_group_translation' => [
                'customer_group_id',
            ],
            'discount_surcharge_translation' => [
                'discount_surcharge_id',
            ],
            'listing_facet_translation' => [
                'listing_facet_id',
            ],
            'listing_sorting_translation' => [
                'listing_sorting_id',
            ],
            'locale_translation' => [
                'locale_id',
            ],
            'media_translation' => [
                'media_id',
            ],
            'order_state_translation' => [
                'order_state_id',
                'order_state_version_id',
            ],
            'order_transaction_state_translation' => [
                'order_transaction_state_id',
                'order_transaction_state_version_id',
            ],
            'payment_method_translation' => [
                'payment_method_id',
            ],
            'product_manufacturer_translation' => [
                'product_manufacturer_id',
                'product_manufacturer_version_id',
            ],
            'product_translation' => [
                'product_id',
                'product_version_id',
            ],
            'sales_channel_translation' => [
                'sales_channel_id',
            ],
            'sales_channel_type_translation' => [
                'sales_channel_type_id',
            ],
            'shipping_method_translation' => [
                'shipping_method_id',
            ],
            'unit_translation' => [
                'unit_id',
            ],
            'media_folder_translation' => [
                'media_folder_id',
            ],
        ];
    }

    private function alterForeignKeys(Connection $connection, $tableName): void
    {
        $drop = sprintf('
                ALTER TABLE `%s`
                DROP INDEX `fk.%s.language_id`,
                DROP FOREIGN KEY `fk.%s.language_id`,
                DROP INDEX `fk.%s.language_parent_id`,
                DROP FOREIGN KEY `fk.%s.language_parent_id`',
            $tableName,
            $this->getTableKeyAlias($tableName),
            $this->getTableKeyAlias($tableName),
            $this->getTableKeyAlias($tableName),
            $this->getTableKeyAlias($tableName)
        );
        $connection->executeQuery($drop);

        $alterQuery = sprintf('
                ALTER TABLE `%s`
                ADD CONSTRAINT `fk.%s.language_id`
                  FOREIGN KEY (`language_id`)
                  REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ',
            $tableName,
            $this->getTableKeyAlias($tableName)
        );
        $connection->executeQuery($alterQuery);
    }
}
