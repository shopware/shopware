<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\Reversible\_fixtures\SwagReversible\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * A legacy MigrationStep living in the same directory. The reversible provider must skip it.
 *
 * @internal
 */
#[Package('framework')]
class Migration1500000000LegacyStep extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1500000000;
    }

    public function update(Connection $connection): void
    {
    }
}
