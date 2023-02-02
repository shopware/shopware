<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1617953690AddCustomFieldToProductReview extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1617953690;
    }

    public function update(Connection $connection): void
    {
        $featureColumn = $connection->fetchColumn(
            'SHOW COLUMNS FROM `product_review` WHERE `Field` LIKE :column;',
            ['column' => 'custom_fields']
        );

        if ($featureColumn === false) {
            $connection->executeUpdate(
                'ALTER TABLE `product_review`
                ADD COLUMN `custom_fields` JSON NULL AFTER `comment`,
                ADD CONSTRAINT `json.product_review.custom_fields` CHECK (JSON_VALID(`custom_fields`));'
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
