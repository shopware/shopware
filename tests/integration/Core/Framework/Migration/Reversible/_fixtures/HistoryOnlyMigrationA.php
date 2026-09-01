<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Migration\Reversible\_fixtures;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\Reversible\DownMigrationContext;
use Shopware\Core\Framework\Migration\Reversible\Migration;
use Shopware\Core\Framework\Migration\Reversible\UpMigrationContext;

/**
 * Only used as a real class name in state store tests, never discovered or executed. Deliberately
 * outside any plugin's Migration directory so the provider does not pick it up.
 *
 * @internal
 */
#[Package('framework')]
class HistoryOnlyMigrationA extends Migration
{
    public function getCreationTimestamp(): int
    {
        return 1910000000;
    }

    public function up(UpMigrationContext $context): void
    {
    }

    public function down(DownMigrationContext $context): void
    {
    }
}
