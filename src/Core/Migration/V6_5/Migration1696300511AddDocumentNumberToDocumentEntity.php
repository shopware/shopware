<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1696300511AddDocumentNumberToDocumentEntity extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1696300511;
    }

    public function update(Connection $connection): void
    {
        if ($this->columnExists($connection, 'document', 'document_number')) {
            return;
        }

        $connection->executeStatement(
            'ALTER TABLE document ADD COLUMN document_number VARCHAR(255) GENERATED ALWAYS AS (
                JSON_UNQUOTE(JSON_EXTRACT(`config`, "$.documentNumber"))
            ) STORED;',
        );

        $connection->executeStatement('CREATE INDEX `idx.document.document_number` ON `document` (`document_number`)');
    }
}
