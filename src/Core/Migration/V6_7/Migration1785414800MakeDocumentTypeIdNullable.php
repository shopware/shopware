<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1785414800MakeDocumentTypeIdNullable extends MigrationStep
{
    private const TABLE = 'document';

    private const COLUMN = 'document_type_id';

    public function getCreationTimestamp(): int
    {
        return 1785414800;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::getColumnOfTable($connection, self::TABLE, self::COLUMN)->isNotNull) {
            return;
        }

        $connection->executeStatement('ALTER TABLE `document` MODIFY `document_type_id` BINARY(16) NULL');
    }
}
