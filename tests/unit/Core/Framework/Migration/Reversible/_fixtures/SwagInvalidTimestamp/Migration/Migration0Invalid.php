<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\Reversible\_fixtures\SwagInvalidTimestamp\Migration;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\Reversible\DownMigrationContext;
use Shopware\Core\Framework\Migration\Reversible\Migration;
use Shopware\Core\Framework\Migration\Reversible\UpMigrationContext;

/**
 * @internal
 */
#[Package('framework')]
class Migration0Invalid extends Migration
{
    public function getCreationTimestamp(): int
    {
        return 0;
    }

    public function up(UpMigrationContext $context): void
    {
    }

    public function down(DownMigrationContext $context): void
    {
    }
}
