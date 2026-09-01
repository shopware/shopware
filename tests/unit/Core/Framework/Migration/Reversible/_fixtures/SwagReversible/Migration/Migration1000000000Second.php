<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\Reversible\_fixtures\SwagReversible\Migration;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\Reversible\DownMigrationContext;
use Shopware\Core\Framework\Migration\Reversible\Migration;
use Shopware\Core\Framework\Migration\Reversible\UpMigrationContext;

/**
 * Deliberately named so that it sorts before Migration2000000000First alphabetically, while its
 * timestamp orders it first. Proves the provider sorts by timestamp, not by filename.
 *
 * @internal
 */
#[Package('framework')]
class Migration1000000000Second extends Migration
{
    public function getCreationTimestamp(): int
    {
        return 1000000000;
    }

    public function up(UpMigrationContext $context): void
    {
    }

    public function down(DownMigrationContext $context): void
    {
    }
}
