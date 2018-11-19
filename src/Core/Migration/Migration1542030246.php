<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1542030246 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1542030246;
    }

    public function update(Connection $connection): void
    {

        $versionAwareTables = $this->getColumns();

        $tables = $connection->executeQuery('SHOW TABLES')->fetchAll(FetchMode::COLUMN);

        $constraints = $this->removeContraints($connection, $tables);

        foreach ($tables as $table) {
            $instructions = [];

            $columns = $versionAwareTables[$table] ?? [];
            if ($this->hasVersionColumns($columns)) {
                $this->removeVersionFromPrimaryKey($connection, $table, $versionAwareTables);
            }

            if (empty($columns)) {
                continue;
            }

            foreach ($columns as $column) {
                $modify = 'MODIFY COLUMN `' . $column . '` BINARY(16) NULL';

                $instructions[] = $modify;
            }

            $sql = 'ALTER TABLE `' . $table . '` ' . implode(', ', $instructions);
            $connection->executeUpdate($sql);
        }

        $this->addIndexes($connection, $constraints);

        $this->addConstraints($connection, $constraints);

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

    private function getColumns(): array
    {
        return [
            'cart' => [
                'version_id',
                'currency_version_id',
                'shipping_method_version_id',
                'payment_method_version_id',
                'country_version_id',
                'customer_version_id',

            ],
            'category' => [
                'media_version_id',

            ],
            'configuration_group' => [
                'version_id',
            ],
            'configuration_group_translation' => [
                'configuration_group_version_id',
            ],
            'configuration_group_option' => [
                'version_id',
                'configuration_group_version_id',
                'media_version_id'
            ],
            'configuration_group_option_translation' => [
                'configuration_group_option_version_id',
            ],
            'country' => [
                'version_id',
            ],
            'country_translation' => [
                'country_version_id',
            ],
            'country_state' => [
                'version_id',
                'country_version_id'
            ],
            'country_state_translation' => [
                'country_state_version_id'
            ],
            'currency' => [
                'version_id',
            ],
            'currency_translation' => [
                'currency_version_id',
            ],
            'customer' => [
                'version_id',
                'customer_group_version_id',
                'default_payment_method_version_id',
                'last_payment_method_version_id',
            ],
            'customer_address' => [
                'version_id',
                'customer_version_id',
                'country_version_id',
                'country_state_version_id'
            ],
            'customer_group' => [
                'version_id',
            ],
            'customer_group_translation' => [
                'customer_group_version_id'
            ],
            'customer_group_discount' => [
                'version_id',
                'customer_group_version_id'
            ],
            'listing_facet' => [
                'version_id',
            ],
            'listing_facet_translation' => [
                'listing_facet_version_id',
            ],
            'listing_sorting' => [
                'version_id',
            ],
            'listing_sorting_translation' => [
                'listing_sorting_version_id',
            ],
            'media' => [
                'version_id',
            ],
            'media_translation' => [
                'media_version_id',
            ],
            'media_thumbnail' => [
                'version_id',
                'media_version_id'
            ],
            'order' => [
                'payment_method_version_id',
                'currency_version_id',
            ],
            'order_address' => [
                'country_version_id',
                'country_state_version_id'
            ],
            'order_delivery' => [
                'shipping_method_version_id'
            ],
            'order_transaction' => [
                'payment_method_version_id',
            ],
            'order_customer' => [
                'customer_version_id',
            ],
            'payment_method' => [
                'version_id',
            ],
            'payment_method_translation' => [
                'payment_method_version_id',
            ],
            'product' => [
                'tax_version_id',
                'unit_version_id'
            ],
            'product_media' => [
                'media_version_id',
            ],
            'product_price_rule' => [
                'currency_version_id',
            ],
            'product_datasheet' => [
                'configuration_group_option_version_id',
            ],
            'product_variation' => [
                'configuration_group_option_version_id',
            ],
            'product_configurator' => [
                'configuration_group_option_version_id',
            ],
            'product_service' => [
                'configuration_group_option_version_id',
                'tax_version_id'
            ],
            'product_manufacturer' => [
                'media_version_id',
            ],
            'search_document' => [
                'version_id',
            ],
            'search_dictionary' => [
                'version_id',
            ],
            'sales_channel' => [
                'currency_version_id',
                'payment_method_version_id',
                'shipping_method_version_id',
                'country_version_id'
            ],
            'sales_channel_country' => [
                'country_version_id',
            ],
            'sales_channel_currency' => [
                'currency_version_id',
            ],
            'sales_channel_payment_method' => [
                'payment_method_version_id',
            ],
            'sales_channel_shipping_method' => [
                'shipping_method_version_id',
            ],
            'seo_url' => [
                'version_id',
                'foreign_key_version_id'
            ],
            'shipping_method' => [
                'version_id',
            ],
            'shipping_method_translation' => [
                'shipping_method_version_id',
            ],
            'shipping_method_price' => [
                'version_id',
                'shipping_method_version_id',
            ],
            'tax' => [
                'version_id',
            ],
            'unit' => [
                'version_id',
            ],
            'unit_translation' => [
                'unit_version_id',

            ]
        ];
    }

    private function removeVersionFromPrimaryKey(Connection $connection, string $table, array $versionAwareTables): void
    {
        $primaryKeys = $connection->executeQuery('SHOW KEYS FROM `' . $table . '` WHERE Key_name = "PRIMARY"')->fetchAll();

        $primaryKey = [];
        foreach ($primaryKeys as $column) {
            if (
                $this->isVersionColumn($column['Column_name']) &&
                in_array($column['Column_name'], $versionAwareTables[$table])
            ) {
                continue;
            }

            $primaryKey[] = $column['Column_name'];
        }

        $connection->executeUpdate('ALTER TABLE `' . $table . '` DROP PRIMARY KEY, ADD PRIMARY KEY (`' . implode('`,`', $primaryKey) . '`)');
    }

    private function removeContraints(Connection $connection, array $tables): array
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
            $versionAwareTables = $this->getColumns();

            foreach ($data as $row) {
                if (
                    $this->isVersionColumn($row['COLUMN_NAME']) &&
                    isset($versionAwareTables[$row['REFERENCED_TABLE_NAME']]) &&
                    is_array($versionAwareTables[$row['REFERENCED_TABLE_NAME']]) &&
                    in_array('version_id', $versionAwareTables[$row['REFERENCED_TABLE_NAME']])
                ) {
                    continue;
                }

                $tableConstraints[$row['CONSTRAINT_NAME']]['columns'][] = $row['COLUMN_NAME'];
                $tableConstraints[$row['CONSTRAINT_NAME']]['refColumns'][] = $row['REFERENCED_COLUMN_NAME'];
                $tableConstraints[$row['CONSTRAINT_NAME']]['refTable'] = $row['REFERENCED_TABLE_NAME'];
                $tableConstraints[$row['CONSTRAINT_NAME']]['update'] = $row['UPDATE_RULE'];
                $tableConstraints[$row['CONSTRAINT_NAME']]['delete'] = $row['DELETE_RULE'];
            }

            foreach ($tableConstraints as $fkName => $fk) {
                $connection->executeQuery('ALTER TABLE `' . $table . '` DROP FOREIGN KEY `' . $fkName . '`');
            }

            $constraints[$table] = $tableConstraints;
        }

        return $constraints;
    }

    private function addIndexes(Connection $connection, array $constraints): void
    {
        foreach ($constraints as $table => $tableConstraints) {
            foreach ($tableConstraints as $fkName => $fk) {
                $indexColumns = implode('`,`', $fk['columns']);

                $result = $connection->fetchAll("SHOW COLUMNS FROM `$table` LIKE 'id'");
                if (
                    !empty($result)
                ) {
                    $create = <<<SQL
        ALTER TABLE `$table`
        ADD INDEX (`$indexColumns`),
        ADD INDEX (`id`, `tenant_id`)
SQL;
                    $connection->executeQuery($create);
                } else {
                    $create = <<<SQL
        ALTER TABLE `$table`
        ADD INDEX (`$indexColumns`)
SQL;
                    $connection->executeQuery($create);
                }
            }
        }
    }

    private function addConstraints(Connection $connection, array $constraints): void
    {
        foreach ($constraints as $table => $tableConstraints) {
            foreach ($tableConstraints as $fkName => $fk) {
                $indexColumns = implode('`,`', $fk['columns']);
                $refColumns = implode('`,`', $fk['refColumns']);
                $refTable = $fk['refTable'];

                $deleteAction = $fk['delete'];
                $updateAction = $fk['update'];
                $result = $connection->fetchAll("SHOW COLUMNS FROM `$refTable` LIKE 'id'");
                if (
                !empty($result)
                )
if (!empty($result) && !in_array($refTable, ['order_state', 'order_transaction', 'order_transaction_state']) ) {
    $create = <<<SQL
        ALTER TABLE `$table`
        ADD FOREIGN KEY `$fkName` (`$indexColumns`) REFERENCES `$refTable` (`$refColumns`) ON DELETE $deleteAction ON UPDATE $updateAction
SQL;
    $connection->executeQuery($create);
}
            }
        }
    }

    private function hasVersionColumns(array $columns): bool
    {
        foreach ($columns as $column) {
            if ($this->isVersionColumn($column)) {
                return true;
            }
        }

        return false;
    }

    private function isVersionColumn(string $column): bool
    {
        return (bool) preg_match('#version_id$#i', $column);
    }
}
