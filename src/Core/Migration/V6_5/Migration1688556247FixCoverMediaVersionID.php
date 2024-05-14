<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1688556247FixCoverMediaVersionID extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1688556247;
    }

    public function update(Connection $connection): void
    {
        do {
            $stmt = $connection->executeQuery('UPDATE product SET product_media_version_id = 0x0fa91ce3e96a4bc2be4bd9ce752c3425 WHERE product_media_id IS NOT NULL AND product_media_version_id IS NULL LIMIT 100');
        } while ($stmt->rowCount() > 0);
    }
}
