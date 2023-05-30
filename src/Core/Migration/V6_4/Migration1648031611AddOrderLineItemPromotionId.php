<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1648031611AddOrderLineItemPromotionId extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1648031611;
    }

    public function update(Connection $connection): void
    {
        $columns = array_column($connection->fetchAllAssociative('SHOW COLUMNS FROM `order_line_item`'), 'Field');

        if (\in_array('promotion_id', $columns, true)) {
            return;
        }

        $connection->executeStatement('ALTER TABLE `order_line_item` ADD `promotion_id` binary(16) NULL AFTER `product_version_id`');
        $connection->executeStatement('ALTER TABLE `order_line_item` ADD CONSTRAINT `fk.order_line_item.promotion_id` FOREIGN KEY (`promotion_id`) REFERENCES `promotion` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
        $connection->executeStatement(\sprintf('UPDATE IGNORE `order_line_item` SET `promotion_id` = UNHEX(JSON_UNQUOTE(JSON_EXTRACT(`payload`, \'$.promotionId\'))) WHERE type = \'%s\'', PromotionProcessor::LINE_ITEM_TYPE));
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
