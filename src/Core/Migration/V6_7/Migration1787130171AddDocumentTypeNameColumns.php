<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1787130171AddDocumentTypeNameColumns extends MigrationStep
{
    final public const DOCUMENT_TYPE_COLUMN_NAME = 'type_name';

    final public const DOCUMENT_TYPE_TABLES = [
        'document',
        'document_base_config',
        'document_base_config_sales_channel',
    ];

    public function getCreationTimestamp(): int
    {
        return 1787130171;
    }

    public function update(Connection $connection): void
    {
        foreach (self::DOCUMENT_TYPE_TABLES as $table) {
            $this->addColumn(
                $connection,
                $table,
                self::DOCUMENT_TYPE_COLUMN_NAME,
                'VARCHAR(255)'
            );
        }
    }
}
