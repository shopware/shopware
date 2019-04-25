<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1553247876AddProductTagIds extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1553247876;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('ALTER TABLE product
	                       ADD tag_ids JSON NULL,
                           ADD CONSTRAINT `json.product.tag_ids` CHECK (JSON_VALID(`tag_ids`));
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // nth
    }
}
