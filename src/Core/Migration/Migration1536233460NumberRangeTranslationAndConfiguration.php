<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536233460NumberRangeTranslationAndConfiguration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233460;
    }

    public function update(Connection $connection): void
    {
        // implement update
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
