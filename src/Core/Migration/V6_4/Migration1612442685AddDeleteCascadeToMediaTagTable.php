<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('core')]
class Migration1612442685AddDeleteCascadeToMediaTagTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1612442685;
    }

    public function update(Connection $connection): void
    {
        $this->dropForeignKeyIfExists($connection, 'media_tag', 'fk.media_tag.id');
        $connection->executeStatement('ALTER TABLE `media_tag` ADD CONSTRAINT `fk.media_tag.media_id` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
