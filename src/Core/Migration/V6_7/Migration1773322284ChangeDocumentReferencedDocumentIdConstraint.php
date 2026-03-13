<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1773322284ChangeDocumentReferencedDocumentIdConstraint extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1773322284;
    }

    public function update(Connection $connection): void
    {
        /** @phpstan-ignore shopware.dropStatement (FK is directly added again so dropping the FK is no issue for blue green) */
        $this->dropForeignKeyIfExists(
            $connection,
            'document',
            'fk.document.referenced_document_id'
        );

        $connection->executeStatement('
            ALTER TABLE `document`
            ADD CONSTRAINT `fk.document.referenced_document_id`
            FOREIGN KEY (`referenced_document_id`)
            REFERENCES `document` (`id`)
            ON DELETE SET NULL ON UPDATE CASCADE;
        ');
    }
}
