<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1542029372RemoveTenant extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1542029372;
    }

    public function update(Connection $connection): void
    {
        $tenantId = $connection->executeQuery('SELECT DISTINCT tenant_id FROM language LIMIT 1')->fetch(FetchMode::COLUMN);

        $tenantAwareTables = $this->getColumns();
        $tables = $connection->executeQuery('SHOW TABLES')->fetchAll(FetchMode::COLUMN);

        $foreignKeys = $this->removeForeignKeys($connection, $tables);
        $this->removeUniqueConstraints($connection);

        foreach ($tables as $table) {
            $tableInstructions = [];

            $columns = $tenantAwareTables[$table] ?? [];
            if ($this->hasTenantColumns($columns)) {
                $this->removeTenantFromPrimaryKey($connection, $table);
            }

            if (empty($columns)) {
                continue;
            }

            foreach ($columns as $column) {
                $modify = 'MODIFY COLUMN `' . $column . '` BINARY(16) NULL';
                if ($tenantId) {
                    $modify .= ' DEFAULT :tenantId';
                }

                $tableInstructions[] = $modify;
            }

            $sql = 'ALTER TABLE `' . $table . '` ' . implode(', ', $tableInstructions);

            $connection->executeUpdate($sql, ['tenantId' => $tenantId]);
        }

        $this->addForeignKeys($connection, $foreignKeys);
        $this->addUniqueConstraints($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        $tables = $this->getColumns();

        foreach ($tables as $table => $columns) {
            $instructions = [];

            foreach ($columns as $column) {
                $instructions[] = 'DROP COLUMN `' . $column . '`';
            }

            $sql = 'ALTER TABLE `' . $table . '` ' . implode(', ', $instructions);

            $connection->executeUpdate($sql);
        }
    }

    private function removeTenantFromPrimaryKey(Connection $connection, string $table): void
    {
        $primaryKeys = $connection->executeQuery('SHOW KEYS FROM `' . $table . '` WHERE Key_name = "PRIMARY"')->fetchAll();

        $primaryKey = [];
        foreach ($primaryKeys as $column) {
            if ($this->isTenantColumn($column['Column_name'])) {
                continue;
            }

            $primaryKey[] = $column['Column_name'];
        }

        $connection->executeUpdate('ALTER TABLE `' . $table . '` DROP PRIMARY KEY, ADD PRIMARY KEY (`' . implode('`,`', $primaryKey) . '`)');
    }

    private function removeForeignKeys(Connection $connection, array $tables): array
    {
        $sql = <<<SQL
SELECT DISTINCT i.TABLE_SCHEMA, i.TABLE_NAME, 
       i.CONSTRAINT_TYPE, i.CONSTRAINT_NAME, 
       k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, k.POSITION_IN_UNIQUE_CONSTRAINT,
       rc.UPDATE_RULE, rc.DELETE_RULE   
FROM information_schema.TABLE_CONSTRAINTS i 
LEFT JOIN information_schema.KEY_COLUMN_USAGE k 
  ON i.CONSTRAINT_NAME = k.CONSTRAINT_NAME 
LEFT JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
  ON rc.TABLE_NAME = i.TABLE_NAME AND rc.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND rc.CONSTRAINT_NAME = i.CONSTRAINT_NAME
WHERE i.TABLE_SCHEMA = :database AND k.CONSTRAINT_SCHEMA = :database AND i.TABLE_NAME = :table AND i.CONSTRAINT_TYPE = 'FOREIGN KEY' 
ORDER BY i.TABLE_NAME, i.CONSTRAINT_NAME, k.POSITION_IN_UNIQUE_CONSTRAINT
SQL;

        $constraints = [];

        foreach ($tables as $table) {
            $data = $connection->executeQuery($sql, ['database' => $connection->getDatabase(), 'table' => $table])->fetchAll();

            if (empty($data)) {
                continue;
            }

            $tableConstraints = [];

            foreach ($data as $row) {
                if ($this->isTenantColumn($row['COLUMN_NAME'])) {
                    continue;
                }

                $tableConstraints[$row['CONSTRAINT_NAME']]['columns'][] = $row['COLUMN_NAME'];
                $tableConstraints[$row['CONSTRAINT_NAME']]['refColumns'][] = $row['REFERENCED_COLUMN_NAME'];
                $tableConstraints[$row['CONSTRAINT_NAME']]['refTable'] = $row['REFERENCED_TABLE_NAME'];
                $tableConstraints[$row['CONSTRAINT_NAME']]['update'] = $row['UPDATE_RULE'];
                $tableConstraints[$row['CONSTRAINT_NAME']]['delete'] = $row['DELETE_RULE'];
            }

            $constraints[$table] = $tableConstraints;

            $foreignKeys = array_keys($tableConstraints);

            if (empty($foreignKeys)) {
                continue;
            }

            $fkInstructions = array_map(function (string $fkName) {
                return 'DROP FOREIGN KEY `' . $fkName . '`';
            }, $foreignKeys);

            $connection->executeQuery('ALTER TABLE `' . $table . '` ' . implode(', ', $fkInstructions));
        }

        return $constraints;
    }

    private function addForeignKeys(Connection $connection, array $constraints): void
    {
        foreach ($constraints as $table => $tableConstraints) {
            $tableInstructions = [];

            foreach ($tableConstraints as $fkName => $fk) {
                $indexColumns = implode('`,`', $fk['columns']);
                $refColumns = implode('`,`', $fk['refColumns']);
                $refTable = $fk['refTable'];

                $deleteAction = $fk['delete'];
                $updateAction = $fk['update'];

                $tableInstructions[] = 'ADD INDEX (`' . $indexColumns . '`)';
                $tableInstructions[] = 'ADD CONSTRAINT `' . $fkName . '` FOREIGN KEY (`' . $indexColumns . '`) REFERENCES `' . $refTable . '` (`' . $refColumns . '`) ON DELETE ' . $deleteAction . ' ON UPDATE ' . $updateAction;
            }

            $connection->executeQuery('ALTER TABLE `' . $table . '` ' . implode(', ', $tableInstructions));
        }
    }

    private function removeUniqueConstraints(Connection $connection): void
    {
        $connection->executeQuery('ALTER TABLE `language` DROP KEY `uniqueLocale`');
        $connection->executeQuery('ALTER TABLE `listing_facet` DROP KEY `unique_identifier`');
        $connection->executeQuery('ALTER TABLE `listing_sorting` DROP KEY `uniqueKey`');
        $connection->executeQuery('ALTER TABLE `locale` DROP KEY `locale`');
        $connection->executeQuery('ALTER TABLE `order` DROP KEY `deep_link_code`');
        $connection->executeQuery('ALTER TABLE `payment_method` DROP KEY `name`');
        $connection->executeQuery('ALTER TABLE `sales_channel` DROP KEY `access_key`');
        $connection->executeQuery('ALTER TABLE `search_document` DROP KEY `language_id`');
        $connection->executeQuery('ALTER TABLE `shipping_method_price` DROP KEY `shipping_method_uuid_quantity_from`');
        $connection->executeQuery('ALTER TABLE `snippet` DROP KEY `tenant_id`');
    }

    private function addUniqueConstraints(Connection $connection): void
    {
        $connection->executeQuery('ALTER TABLE `language` ADD UNIQUE KEY `uniqueLocale` (`locale_id`)');
        $connection->executeQuery('ALTER TABLE `listing_facet` ADD UNIQUE KEY `unique_identifier` (`unique_key`, `version_id`)');
        $connection->executeQuery('ALTER TABLE `listing_sorting` ADD UNIQUE KEY `uniqueKey` (`unique_key`)');
        $connection->executeQuery('ALTER TABLE `order` ADD UNIQUE KEY `deep_link_code` (`deep_link_code`, `version_id`)');
        $connection->executeQuery('ALTER TABLE `payment_method` ADD UNIQUE KEY `name` (`technical_name`, `version_id`)');
        $connection->executeQuery('ALTER TABLE `sales_channel` ADD UNIQUE KEY `access_key` (`access_key`)');
        $connection->executeQuery('ALTER TABLE `search_document` ADD UNIQUE KEY `unique` (`language_id`, `keyword`, `entity`, `entity_id`, `ranking`, `version_id`)');
        $connection->executeQuery('ALTER TABLE `shipping_method_price` ADD UNIQUE KEY `shipping_method_uuid_quantity_from` (`shipping_method_id`,`quantity_from`,`version_id`)');
        $connection->executeQuery('ALTER TABLE `snippet` ADD UNIQUE KEY `tenant_id` (`language_id`,`translation_key`)');
    }

    private function hasTenantColumns(array $columns): bool
    {
        foreach ($columns as $column) {
            if ($this->isTenantColumn($column)) {
                return true;
            }
        }

        return false;
    }

    private function isTenantColumn(string $column): bool
    {
        return (bool) preg_match('#tenant_id$#i', $column);
    }

    private function getColumns(): array
    {
        return [
            'cart' => [
                'tenant_id',
                'currency_tenant_id',
                'shipping_method_tenant_id',
                'payment_method_tenant_id',
                'country_tenant_id',
                'customer_tenant_id',
                'sales_channel_tenant_id',
            ],
            'catalog' => [
                'tenant_id',
            ],
            'catalog_translation' => [
                'catalog_tenant_id',
                'language_tenant_id',
            ],
            'category' => [
                'tenant_id',
                'catalog_tenant_id',
                'parent_tenant_id',
                'media_tenant_id',
            ],
            'category_translation' => [
                'category_tenant_id',
                'language_tenant_id',
                'catalog_tenant_id',
            ],
            'configuration_group' => [
                'tenant_id',
            ],
            'configuration_group_option' => [
                'tenant_id',
                'configuration_group_tenant_id',
                'media_tenant_id',
            ],
            'configuration_group_option_translation' => [
                'configuration_group_option_tenant_id',
                'language_tenant_id',
            ],
            'configuration_group_translation' => [
                'configuration_group_tenant_id',
                'language_tenant_id',
            ],
            'country' => [
                'tenant_id',
            ],
            'country_state' => [
                'tenant_id',
                'country_tenant_id',
            ],
            'country_state_translation' => [
                'country_state_tenant_id',
                'language_tenant_id',
            ],
            'country_translation' => [
                'country_tenant_id',
                'language_tenant_id',
            ],
            'currency' => [
                'tenant_id',
            ],
            'currency_translation' => [
                'currency_tenant_id',
                'language_tenant_id',
            ],
            'customer' => [
                'tenant_id',
                'customer_group_tenant_id',
                'default_payment_method_tenant_id',
                'sales_channel_tenant_id',
                'last_payment_method_tenant_id',
                'default_billing_address_tenant_id',
                'default_shipping_address_tenant_id',
            ],
            'customer_address' => [
                'tenant_id',
                'customer_tenant_id',
                'country_tenant_id',
                'country_state_tenant_id',
            ],
            'customer_group' => [
                'tenant_id',
            ],
            'customer_group_discount' => [
                'tenant_id',
                'customer_group_tenant_id',
            ],
            'customer_group_translation' => [
                'customer_group_tenant_id',
                'language_tenant_id',
            ],
            'discount_surcharge' => [
                'tenant_id',
                'rule_tenant_id',
            ],
            'discount_surcharge_translation' => [
                'discount_surcharge_tenant_id',
                'language_tenant_id',
            ],
            'integration' => [
                'tenant_id',
            ],
            'language' => [
                'tenant_id',
                'parent_tenant_id',
                'locale_tenant_id',
            ],
            'listing_facet' => [
                'tenant_id',
            ],
            'listing_facet_translation' => [
                'listing_facet_tenant_id',
                'language_tenant_id',
            ],
            'listing_sorting' => [
                'tenant_id',
            ],
            'listing_sorting_translation' => [
                'listing_sorting_tenant_id',
                'language_tenant_id',
            ],
            'locale' => [
                'tenant_id',
            ],
            'locale_translation' => [
                'locale_tenant_id',
                'language_tenant_id',
            ],
            'media' => [
                'tenant_id',
                'catalog_tenant_id',
                'user_tenant_id',
            ],
            'media_thumbnail' => [
                'tenant_id',
                'media_tenant_id',
            ],
            'media_translation' => [
                'media_tenant_id',
                'language_tenant_id',
                'catalog_tenant_id',
            ],
            'order' => [
                'tenant_id',
                'order_customer_tenant_id',
                'order_state_tenant_id',
                'payment_method_tenant_id',
                'currency_tenant_id',
                'sales_channel_tenant_id',
                'billing_address_tenant_id',
            ],
            'order_address' => [
                'tenant_id',
                'country_tenant_id',
                'country_state_tenant_id',
            ],
            'order_customer' => [
                'tenant_id',
                'customer_tenant_id',
            ],
            'order_delivery' => [
                'tenant_id',
                'order_tenant_id',
                'shipping_order_address_tenant_id',
                'shipping_method_tenant_id',
                'order_state_tenant_id',
            ],
            'order_delivery_position' => [
                'tenant_id',
                'order_delivery_tenant_id',
                'order_line_item_tenant_id',
            ],
            'order_line_item' => [
                'tenant_id',
                'order_tenant_id',
                'parent_tenant_id',
            ],
            'order_state' => [
                'tenant_id',
            ],
            'order_state_translation' => [
                'order_state_tenant_id',
                'language_tenant_id',
            ],
            'order_transaction' => [
                'tenant_id',
                'order_tenant_id',
                'payment_method_tenant_id',
                'order_transaction_state_tenant_id',
            ],
            'order_transaction_state' => [
                'tenant_id',
            ],
            'order_transaction_state_translation' => [
                'order_transaction_state_tenant_id',
                'language_tenant_id',
            ],
            'payment_method' => [
                'tenant_id',
            ],
            'payment_method_translation' => [
                'payment_method_tenant_id',
                'language_tenant_id',
            ],
            'product' => [
                'tenant_id',
                'catalog_tenant_id',
                'parent_tenant_id',
                'tax_tenant_id',
                'product_manufacturer_tenant_id',
                'product_media_tenant_id',
                'unit_tenant_id',
            ],
            'product_category' => [
                'product_tenant_id',
                'category_tenant_id',
            ],
            'product_category_tree' => [
                'product_tenant_id',
                'category_tenant_id',
            ],
            'product_configurator' => [
                'tenant_id',
                'product_tenant_id',
                'configuration_group_option_tenant_id',
            ],
            'product_datasheet' => [
                'product_tenant_id',
                'configuration_group_option_tenant_id',
            ],
            'product_manufacturer' => [
                'tenant_id',
                'catalog_tenant_id',
                'media_tenant_id',
            ],
            'product_manufacturer_translation' => [
                'product_manufacturer_tenant_id',
                'catalog_tenant_id',
                'language_tenant_id',
            ],
            'product_media' => [
                'tenant_id',
                'catalog_tenant_id',
                'product_tenant_id',
                'media_tenant_id',
            ],
            'product_price_rule' => [
                'tenant_id',
                'rule_tenant_id',
                'product_tenant_id',
                'currency_tenant_id',
            ],
            'product_service' => [
                'tenant_id',
                'product_tenant_id',
                'configuration_group_option_tenant_id',
                'tax_tenant_id',
            ],
            'product_translation' => [
                'product_tenant_id',
                'language_tenant_id',
                'catalog_tenant_id',
            ],
            'product_variation' => [
                'product_tenant_id',
                'configuration_group_option_tenant_id',
            ],
            'rule' => [
                'tenant_id',
            ],
            'sales_channel' => [
                'tenant_id',
                'type_tenant_id',
                'language_tenant_id',
                'currency_tenant_id',
                'payment_method_tenant_id',
                'shipping_method_tenant_id',
                'country_tenant_id',
            ],
            'sales_channel_catalog' => [
                'sales_channel_tenant_id',
                'catalog_tenant_id',
            ],
            'sales_channel_country' => [
                'sales_channel_tenant_id',
                'country_tenant_id',
            ],
            'sales_channel_currency' => [
                'sales_channel_tenant_id',
                'currency_tenant_id',
            ],
            'sales_channel_language' => [
                'sales_channel_tenant_id',
                'language_tenant_id',
            ],
            'sales_channel_payment_method' => [
                'sales_channel_tenant_id',
                'payment_method_tenant_id',
            ],
            'sales_channel_shipping_method' => [
                'sales_channel_tenant_id',
                'shipping_method_tenant_id',
            ],
            'sales_channel_translation' => [
                'sales_channel_tenant_id',
                'language_tenant_id',
            ],
            'sales_channel_type' => [
                'tenant_id',
            ],
            'sales_channel_type_translation' => [
                'sales_channel_type_tenant_id',
                'language_tenant_id',
            ],
            'search_dictionary' => [
                'tenant_id',
                'language_tenant_id',
            ],
            'search_document' => [
                'tenant_id',
                'language_tenant_id',
            ],
            'seo_url' => [
                'tenant_id',
                'sales_channel_tenant_id',
            ],
            'shipping_method' => [
                'tenant_id',
            ],
            'shipping_method_price' => [
                'tenant_id',
                'shipping_method_tenant_id',
            ],
            'shipping_method_translation' => [
                'shipping_method_tenant_id',
                'language_tenant_id',
            ],
            'snippet' => [
                'tenant_id',
                'language_tenant_id',
            ],
            'storefront_api_context' => [
                'tenant_id',
            ],
            'tax' => [
                'tenant_id',
            ],
            'unit' => [
                'tenant_id',
            ],
            'unit_translation' => [
                'unit_tenant_id',
                'language_tenant_id',
            ],
            'user' => [
                'tenant_id',
                'locale_tenant_id',
            ],
            'user_access_key' => [
                'tenant_id',
                'user_tenant_id',
            ],
            'version' => [
                'tenant_id',
            ],
            'version_commit' => [
                'tenant_id',
                'user_tenant_id',
                'integration_tenant_id',
                'version_tenant_id',
            ],
            'version_commit_data' => [
                'tenant_id',
                'version_commit_tenant_id',
                'user_tenant_id',
                'integration_tenant_id',
            ],
        ];
    }
}
