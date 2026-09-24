<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1787229827MakeDocumentOrderOptional extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1787229827;
    }

    public function update(Connection $connection): void
    {
        /** @phpstan-ignore shopware.dropStatement (FK is directly added again so dropping the FK is no issue for blue green) */
        $this->dropForeignKeyIfExists($connection, 'document', 'fk.document.order_id');

        $connection->executeStatement('
            ALTER TABLE `document`
                MODIFY COLUMN `order_id` BINARY(16) NULL,
                MODIFY COLUMN `order_version_id` BINARY(16) NULL;
        ');

        $connection->executeStatement('
            ALTER TABLE `document`
                ADD CONSTRAINT `fk.document.order_id`
                FOREIGN KEY (`order_id`, `order_version_id`)
                REFERENCES `order` (`id`, `version_id`)
                ON DELETE RESTRICT ON UPDATE CASCADE;
        ');
    }
}
