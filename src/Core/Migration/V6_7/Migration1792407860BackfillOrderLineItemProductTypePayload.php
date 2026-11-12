<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1792407860BackfillOrderLineItemProductTypePayload extends MigrationStep
{
    private const BATCH_SIZE = 1000;

    private const WITHOUT_PRODUCT_TYPE = '`type` IN (\'product\', \'custom\')
        AND (JSON_CONTAINS(`states`, \'"is-download"\') OR JSON_CONTAINS(`states`, \'"is-physical"\'))
        AND NOT JSON_CONTAINS_PATH(`payload`, \'one\', \'$.productType\')';

    public function getCreationTimestamp(): int
    {
        return 1792407860;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'order_line_item', 'states')) {
            return;
        }

        $lastId = '';

        do {
            $ids = $connection->fetchFirstColumn(
                'SELECT `id` FROM `order_line_item`
                WHERE `id` > :lastId AND ' . self::WITHOUT_PRODUCT_TYPE . '
                ORDER BY `id`
                LIMIT :limit',
                ['lastId' => $lastId, 'limit' => self::BATCH_SIZE],
                ['lastId' => ParameterType::BINARY, 'limit' => ParameterType::INTEGER]
            );

            if ($ids === []) {
                return;
            }

            $connection->executeStatement(
                'UPDATE `order_line_item`
                SET `payload` = JSON_SET(
                    `payload`,
                    \'$.productType\',
                    IF(JSON_CONTAINS(`states`, \'"is-download"\'), \'digital\', \'physical\')
                )
                WHERE `id` IN (:ids) AND ' . self::WITHOUT_PRODUCT_TYPE,
                ['ids' => $ids],
                ['ids' => ArrayParameterType::BINARY]
            );

            $lastId = end($ids);
        } while (\count($ids) === self::BATCH_SIZE);
    }
}
