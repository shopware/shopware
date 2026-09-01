<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\Reversible\_fixtures\SwagDuplicateTimestamp\Migration;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\Reversible\DownMigrationContext;
use Shopware\Core\Framework\Migration\Reversible\Migration;
use Shopware\Core\Framework\Migration\Reversible\UpMigrationContext;

/**
 * @internal
 */
#[Package('framework')]
class Migration1600000000A extends Migration
{
    public function getCreationTimestamp(): int
    {
        return 1600000000;
    }

    public function up(UpMigrationContext $context): void
    {
    }

    public function down(DownMigrationContext $context): void
    {
    }
}
