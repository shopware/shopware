<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Copies of an order made through the clone API kept the delivery and billing address of the source order.
 * Each such reference is pointed to the copy's own address, but only when that address has the same content,
 * so what the order shows does not change. Copies without such an address are left as they are.
 *
 * @internal
 */
#[Package('checkout')]
class Migration1793633488RepairClonedOrderAddressReferences extends MigrationStep
{
    private const BATCH_SIZE = 1000;

    /**
     * Every column that makes up what an order address shows; ids, the order reference and timestamps are left out
     */
    private const CONTENT_COLUMNS = [
        'country_id',
        'country_state_id',
        'company',
        'department',
        'salutation_id',
        'title',
        'first_name',
        'last_name',
        'street',
        'zipcode',
        'city',
        'vat_id',
        'phone_number',
        'additional_address_line1',
        'additional_address_line2',
        'custom_fields',
    ];

    public function getCreationTimestamp(): int
    {
        return 1793633488;
    }

    public function update(Connection $connection): void
    {
        $sameContent = $this->sameContentCondition($connection);

        $this->repair($connection, $sameContent, 'order_delivery', 'shipping_order_address_id', 'shipping_order_address_version_id', 'order_id', 'order_version_id');
        $this->repair($connection, $sameContent, 'order', 'billing_address_id', 'billing_address_version_id', 'id', 'version_id');
    }

    /**
     * Walks the live rows of the table in batches and points each address reference to another order to the identical address of the own order
     */
    private function repair(
        Connection $connection,
        string $sameContent,
        string $table,
        string $addressColumn,
        string $addressVersionColumn,
        string $orderColumn,
        string $orderVersionColumn,
    ): void {
        $liveVersion = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $lastId = '';

        do {
            /** @var list<string> $ids */
            $ids = $connection->fetchFirstColumn(
                \sprintf('SELECT `id` FROM `%s` WHERE `version_id` = :live AND `id` > :lastId ORDER BY `id` LIMIT :limit', $table),
                ['live' => $liveVersion, 'lastId' => $lastId, 'limit' => self::BATCH_SIZE],
                ['live' => ParameterType::BINARY, 'lastId' => ParameterType::BINARY, 'limit' => ParameterType::INTEGER]
            );

            if ($ids === []) {
                return;
            }

            $references = $connection->fetchAllAssociative(
                \sprintf(
                    'SELECT `referencing`.`id`, `referencing`.`%1$s` AS `referenced_id`, (
                        SELECT `own`.`id` FROM `order_address` `own`
                        WHERE `own`.`order_id` = `referencing`.`%3$s`
                            AND `own`.`order_version_id` = `referencing`.`%4$s`
                            AND `own`.`version_id` = :live
                            AND %5$s
                        ORDER BY `own`.`id`
                        LIMIT 1
                    ) AS `own_id`
                    FROM `%6$s` `referencing`
                    INNER JOIN `order_address` `referenced`
                        ON `referenced`.`id` = `referencing`.`%1$s`
                        AND `referenced`.`version_id` = `referencing`.`%2$s`
                    WHERE `referencing`.`id` IN (:ids)
                        AND `referencing`.`version_id` = :live
                        AND `referenced`.`order_id` <> `referencing`.`%3$s`',
                    $addressColumn,
                    $addressVersionColumn,
                    $orderColumn,
                    $orderVersionColumn,
                    $sameContent,
                    $table
                ),
                ['ids' => $ids, 'live' => $liveVersion],
                ['ids' => ArrayParameterType::BINARY]
            );

            foreach ($references as $reference) {
                if ($reference['own_id'] === null) {
                    continue;
                }

                $connection->executeStatement(
                    \sprintf('UPDATE `%1$s` SET `%2$s` = :own WHERE `id` = :id AND `version_id` = :live AND `%2$s` = :referenced', $table, $addressColumn),
                    ['own' => $reference['own_id'], 'id' => $reference['id'], 'live' => $liveVersion, 'referenced' => $reference['referenced_id']]
                );
            }

            $lastId = end($ids);
        } while (\count($ids) === self::BATCH_SIZE);
    }

    /**
     * Compares the content columns that exist on this system, a later major may have dropped some of them
     */
    private function sameContentCondition(Connection $connection): string
    {
        $conditions = [];
        foreach (self::CONTENT_COLUMNS as $column) {
            if (TableHelper::columnExists($connection, 'order_address', $column)) {
                $conditions[] = \sprintf('`own`.`%1$s` <=> `referenced`.`%1$s`', $column);
            }
        }

        return implode(' AND ', $conditions);
    }
}
