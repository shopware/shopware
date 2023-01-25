<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1564475053RemoveSaveDocumentsConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1564475053;
    }

    public function update(Connection $connection): void
    {
        $connection->delete('system_config', [
            'configuration_key' => 'core.saveDocuments',
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
