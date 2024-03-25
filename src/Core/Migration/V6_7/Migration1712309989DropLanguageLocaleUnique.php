<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('core')]
class Migration1712309989DropLanguageLocaleUnique extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1712309989;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `language` DROP INDEX `uniq.translation_code_id`');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
