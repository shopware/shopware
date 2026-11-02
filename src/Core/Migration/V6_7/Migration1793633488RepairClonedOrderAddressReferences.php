<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
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
        $liveVersion = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $deliveries = $connection->fetchAllAssociative(
            'SELECT `delivery`.`id`, `delivery`.`shipping_order_address_id` AS `referenced_id`, (
                SELECT `own`.`id` FROM `order_address` `own`
                WHERE `own`.`order_id` = `delivery`.`order_id`
                    AND `own`.`order_version_id` = `delivery`.`order_version_id`
                    AND `own`.`version_id` = :live
                    AND ' . $sameContent . '
                ORDER BY `own`.`id`
                LIMIT 1
            ) AS `own_id`
            FROM `order_delivery` `delivery`
            INNER JOIN `order_address` `referenced`
                ON `referenced`.`id` = `delivery`.`shipping_order_address_id`
                AND `referenced`.`version_id` = `delivery`.`shipping_order_address_version_id`
            WHERE `delivery`.`version_id` = :live
                AND `referenced`.`order_id` <> `delivery`.`order_id`',
            ['live' => $liveVersion]
        );

        foreach ($deliveries as $delivery) {
            if ($delivery['own_id'] === null) {
                continue;
            }

            $connection->executeStatement(
                'UPDATE `order_delivery` SET `shipping_order_address_id` = :own
                WHERE `id` = :id AND `version_id` = :live AND `shipping_order_address_id` = :referenced',
                ['own' => $delivery['own_id'], 'id' => $delivery['id'], 'live' => $liveVersion, 'referenced' => $delivery['referenced_id']]
            );
        }

        $orders = $connection->fetchAllAssociative(
            'SELECT `order`.`id`, `order`.`billing_address_id` AS `referenced_id`, (
                SELECT `own`.`id` FROM `order_address` `own`
                WHERE `own`.`order_id` = `order`.`id`
                    AND `own`.`order_version_id` = `order`.`version_id`
                    AND `own`.`version_id` = :live
                    AND ' . $sameContent . '
                ORDER BY `own`.`id`
                LIMIT 1
            ) AS `own_id`
            FROM `order`
            INNER JOIN `order_address` `referenced`
                ON `referenced`.`id` = `order`.`billing_address_id`
                AND `referenced`.`version_id` = `order`.`billing_address_version_id`
            WHERE `order`.`version_id` = :live
                AND `referenced`.`order_id` <> `order`.`id`',
            ['live' => $liveVersion]
        );

        foreach ($orders as $order) {
            if ($order['own_id'] === null) {
                continue;
            }

            $connection->executeStatement(
                'UPDATE `order` SET `billing_address_id` = :own
                WHERE `id` = :id AND `version_id` = :live AND `billing_address_id` = :referenced',
                ['own' => $order['own_id'], 'id' => $order['id'], 'live' => $liveVersion, 'referenced' => $order['referenced_id']]
            );
        }
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
