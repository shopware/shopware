<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1545119776AddTranslationParentId extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1545119776;
    }

    public function update(Connection $connection): void
    {
        // fix pk key order in product_translation
        $connection->executeQuery('
            ALTER TABLE `product_translation`
            DROP PRIMARY KEY,
            ADD PRIMARY KEY (`product_id`, `product_version_id`, `language_id`)
        ');

        // fix order_state_translation pk: add order_state_version_id
        $connection->executeQuery('
            ALTER TABLE `order_state_translation` 
            DROP PRIMARY KEY,
            ADD PRIMARY KEY (`order_state_id`, `order_state_version_id`, `language_id`)
        ');

        // fix order_transaction_state_translation pk: add order_transaction_state_version_id
        $connection->executeQuery('
            ALTER TABLE `order_transaction_state_translation`
            DROP PRIMARY KEY,
            ADD PRIMARY KEY (`order_transaction_state_id`, `order_transaction_state_version_id`, `language_id`)
        ');

        // there needs to be an index on all columns that are referenced by a foreign key
        $connection->executeQuery('
            ALTER TABLE `language`
            ADD KEY `idx.language.language_id_parent_language_id` (`id`, `parent_id`)
        ');

        foreach ($this->getTranslationTables() as $tableName => $pks) {
            $this->updateTranslationTable($tableName, $pks, $connection);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    protected function getTableKeyAlias(string $tableName): string
    {
        $aliases = [
            'configuration_group_option_translation' => 'cgo_translations',
        ];

        return $aliases[$tableName] ?? $tableName;
    }

    protected function updateTranslationTable(string $tableName, array $pks, Connection $connection): void
    {
        $drop = sprintf('
            ALTER TABLE `%s`
            DROP INDEX `fk.%s.language_id`,
            DROP FOREIGN KEY `fk.%s.language_id`',
            $tableName,
            $this->getTableKeyAlias($tableName),
            $this->getTableKeyAlias($tableName)
        );
        $connection->executeQuery($drop);

        $columnString = implode(', ', $pks);
        $addQuery = sprintf('
            ALTER TABLE `%s`
            ADD COLUMN `language_parent_id` binary(16) NULL,
            ADD CONSTRAINT `fk.%s.language_id`
              FOREIGN KEY (`language_id`, `language_parent_id`)
              REFERENCES `language` (`id`, `parent_id`) ON DELETE CASCADE ON UPDATE CASCADE,
            ADD CONSTRAINT `fk.%s.language_parent_id` 
              FOREIGN KEY (%s, language_parent_id)
              REFERENCES %s (%s, language_id) ON DELETE CASCADE ON UPDATE NO ACTION',
            $tableName,
            $this->getTableKeyAlias($tableName),
            $this->getTableKeyAlias($tableName),
            $columnString,
            $tableName,
            $columnString
        );

        $connection->executeQuery($addQuery);
    }

    protected function getTranslationTables(): array
    {
        return [
            'catalog_translation' => [
                'catalog_id',
            ],
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
        ];
    }
}
