<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1543240218UseJsonDataTypes extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1543240218;
    }

    public function update(Connection $connection): void
    {
        foreach ($this->getColumns() as $table => $columns) {
            $instructions = [];
            $sql = sprintf('ALTER TABLE `%s` ', $table);

            foreach ($columns as $column => $nullable) {
                $instructions[] = sprintf('MODIFY COLUMN `%s` JSON %s', $column, $nullable);
                $instructions[] = sprintf('ADD CONSTRAINT `json.%s` CHECK (JSON_VALID(`%s`))', $column, $column);
            }

            $sql .= implode(',', $instructions);

            $connection->executeQuery($sql);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function getColumns(): array
    {
        return [
            'sales_channel_type' => [
                'screenshot_urls' => 'NULL',
            ],
            'listing_facet' => [
                'payload' => 'NOT NULL',
            ],
            'listing_sorting' => [
                'payload' => 'NOT NULL',
            ],
            'rule' => [
                'payload' => 'NULL',
            ],
            'storefront_api_context' => [
                'payload' => 'NOT NULL',
            ],
            'discount_surcharge' => [
                'filter_rule' => 'NOT NULL',
            ],
            'payment_method' => [
                'risk_rules' => 'NULL',
            ],
            'cart' => [
                'cart' => 'NOT NULL',
            ],
            'order_transaction' => [
                'amount' => 'NOT NULL',
                'details' => 'NULL',
            ],
            'media' => [
                'meta_data' => 'NULL',
            ],
            'category' => [
                'sorting_ids' => 'NULL',
                'facet_ids' => 'NULL',
            ],
            'product' => [
                'category_tree' => 'NULL',
                'variation_ids' => 'NULL',
                'datasheet_ids' => 'NULL',
                'price' => 'NULL',
                'listing_prices' => 'NULL',
                'blacklist_ids' => 'NULL',
                'whitelist_ids' => 'NULL',
            ],
            'product_configurator' => [
                'price' => 'NULL',
                'prices' => 'NULL',
            ],
            'product_service' => [
                'price' => 'NULL',
                'prices' => 'NULL',
            ],
            'product_price_rule' => [
                'price' => 'NOT NULL',
            ],
            'order_line_item' => [
                'payload' => 'NULL',
                'price_definition' => 'NULL',
                'price' => 'NULL',
            ],
            'rule_condition' => [
                'value' => 'NULL',
            ],
            'version_commit_data' => [
                'entity_id' => 'NOT NULL',
                'payload' => 'NOT NULL',
            ],
            'sales_channel' => [
                'configuration' => 'NULL',
            ],
        ];
    }
}
