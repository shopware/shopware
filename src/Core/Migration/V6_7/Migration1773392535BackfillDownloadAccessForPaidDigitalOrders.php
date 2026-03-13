<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1773392535BackfillDownloadAccessForPaidDigitalOrders extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1773392535;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<'SQL'
            UPDATE `order_line_item_download` `download`
            INNER JOIN `order_line_item` `line_item`
                ON `line_item`.`id` = `download`.`order_line_item_id`
                AND `line_item`.`version_id` = `download`.`order_line_item_version_id`
            SET
                `download`.`access_granted` = 1,
                `download`.`updated_at` = :updatedAt
            WHERE `download`.`access_granted` = 0
              AND JSON_UNQUOTE(JSON_EXTRACT(`line_item`.`payload`, '$.productType')) = :productType
              AND EXISTS (
                  SELECT 1
                  FROM `order_transaction` `transaction`
                  INNER JOIN `state_machine_state` `state`
                      ON `state`.`id` = `transaction`.`state_id`
                  INNER JOIN `state_machine` `state_machine`
                      ON `state_machine`.`id` = `state`.`state_machine_id`
                  WHERE `transaction`.`order_id` = `line_item`.`order_id`
                    AND `transaction`.`order_version_id` = `line_item`.`order_version_id`
                    AND `state_machine`.`technical_name` = 'order_transaction.state'
                    AND `state`.`technical_name` = 'paid'
              )
            SQL,
            [
                'productType' => ProductDefinition::TYPE_DIGITAL,
                'updatedAt' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
            ]
        );
    }
}
