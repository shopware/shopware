<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1555413185ProductNumberUnique extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1555413185;
    }

    public function update(Connection $connection): void
    {
        $duplicates = $connection->fetchAll('
          SELECT `product_number`, Count(*) c
          FROM `product`
          GROUP BY `product_number`
          HAVING c > 1;
          '
        );

        foreach ($duplicates as $duplicate) {
            $updateStmt = $connection->prepare('
            UPDATE `product`
            SET `product_number`= HEX(`id`)
            WHERE `product_number` = :product_number
            LIMIT :c
            '
            );
            $updateStmt->bindParam('product_number', $duplicate['product_number']);
            $duplicate['c'] = $duplicate['c'] - 1;
            $updateStmt->bindParam('c', $duplicate['c'], ParameterType::INTEGER);
            $updateStmt->execute();
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->exec('
            ALTER TABLE `product`
            ADD UNIQUE `uniq.product_number__version_id` (`product_number`, `version_id`);
            '
        );
    }
}
