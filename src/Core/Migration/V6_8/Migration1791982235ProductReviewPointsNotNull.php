<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1791982235ProductReviewPointsNotNull extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1791982235;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'product_review', 'points')
            || TableHelper::getColumnOfTable($connection, 'product_review', 'points')->isNotNull
        ) {
            return;
        }

        $connection->executeStatement('UPDATE `product_review` SET `points` = 0 WHERE `points` IS NULL');

        $this->executeDdlStatement(
            $connection,
            'ALTER TABLE `product_review` MODIFY COLUMN `points` DOUBLE NOT NULL'
        );
    }
}
