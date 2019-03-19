<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1552946222RemoveListing extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1552946222;
    }

    public function update(Connection $connection): void
    {
        // nth
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeQuery('DROP TABLE `listing_sorting_translation`;');
        $connection->executeQuery('DROP TABLE `listing_sorting`;');
        $connection->executeQuery('DROP TABLE `listing_facet_translation`;');
        $connection->executeQuery('DROP TABLE `listing_facet`;');
    }
}
