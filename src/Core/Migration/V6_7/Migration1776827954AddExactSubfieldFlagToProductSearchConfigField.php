<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1776827954AddExactSubfieldFlagToProductSearchConfigField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1776827954;
    }

    public function update(Connection $connection): void
    {
        if (!$this->columnExists($connection, 'product_search_config_field', 'use_exact_subfield')) {
            $connection->executeStatement('
                ALTER TABLE `product_search_config_field`
                ADD COLUMN `use_exact_subfield` TINYINT(1) NOT NULL DEFAULT 0
            ');
        }

        // Identifier fields (productNumber, ean, manufacturerNumber) opt in alongside the
        // long-form text fields. Without the exact subfield, a query like `5,5` is expanded
        // by `word_delimiter_graph`'s `catenate_all` into `5,5 OR 55` and then matches every
        // productNumber whose tokens happen to include `55` (UUIDs, supplier SKUs, etc.).
        // The exact subfield is keyword-style — only the literal lowercased token matches —
        // so the high-boost path correctly drops phantom matches and keeps the legitimate
        // `5,5`-in-name hit on top.
        $connection->executeStatement(
            'UPDATE `product_search_config_field`
                SET `use_exact_subfield` = 1
              WHERE `field` IN (:fields)',
            ['fields' => ['name', 'customSearchKeywords', 'productNumber', 'ean', 'manufacturerNumber']],
            ['fields' => ArrayParameterType::STRING]
        );
    }
}
