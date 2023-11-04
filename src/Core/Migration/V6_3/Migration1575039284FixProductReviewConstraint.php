<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1575039284FixProductReviewConstraint extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1575039284;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `product_review` DROP FOREIGN KEY `fk.product_review.customer_id`
        ');

        $connection->executeStatement('
            ALTER TABLE `product_review`
            ADD CONSTRAINT `fk.product_review.customer_id`
            FOREIGN KEY (`customer_id`)
            REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
        ');

        $connection->executeStatement('
            ALTER TABLE `product_review` DROP FOREIGN KEY `fk.product_review.language_id`
        ');

        $connection->executeStatement('
            ALTER TABLE `product_review`
            ADD CONSTRAINT `fk.product_review.language_id`
            FOREIGN KEY (`language_id`)
            REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
