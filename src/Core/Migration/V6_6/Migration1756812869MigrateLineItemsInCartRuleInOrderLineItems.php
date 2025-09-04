<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1756812869MigrateLineItemsInCartRuleInOrderLineItems extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1756812869;
    }

    public function update(Connection $connection): void
    {
        // migrate the rule condition types in order line items
        $updateLimit = 1000;

        do {
            $ids = $connection->fetchFirstColumn(
                'SELECT `id` FROM `order_line_item` WHERE JSON_VALUE(price_definition, \'$.filter\') LIKE \'%"cartLineItemsInCart"%\' LIMIT :limit',
                ['limit' => $updateLimit],
                ['limit' => ParameterType::INTEGER]
            );

            if (empty($ids)) {
                break;
            }

            $connection->executeStatement(
                'UPDATE `order_line_item` SET `price_definition` = REPLACE(`price_definition`, \'"cartLineItemsInCart"\', \'"cartLineItem"\') WHERE `id` IN (:ids)',
                ['ids' => $ids],
                ['ids' => ArrayParameterType::BINARY]
            );
        } while (\count($ids) === $updateLimit);
    }
}
