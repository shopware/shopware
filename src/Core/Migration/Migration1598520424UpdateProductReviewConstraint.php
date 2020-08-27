<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1598520424UpdateProductReviewConstraint extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1598520424;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            ALTER TABLE `product_review`
            DROP FOREIGN KEY `fk.product_review.customer_id`
        ');

        $connection->exec('
            ALTER TABLE `product_review`
            ADD CONSTRAINT `fk.product_review.customer_id`
                FOREIGN KEY (`customer_id`)
                REFERENCES `customer` (`id`)
                ON DELETE SET NULL
                ON UPDATE CASCADE
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
