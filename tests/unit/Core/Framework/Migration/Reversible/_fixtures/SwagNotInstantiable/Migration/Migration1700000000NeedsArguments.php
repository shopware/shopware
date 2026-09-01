<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\Reversible\_fixtures\SwagNotInstantiable\Migration;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\Reversible\DownMigrationContext;
use Shopware\Core\Framework\Migration\Reversible\Migration;
use Shopware\Core\Framework\Migration\Reversible\UpMigrationContext;

/**
 * The provider must reject this: it cannot be instantiated without arguments.
 *
 * @internal
 */
#[Package('framework')]
class Migration1700000000NeedsArguments extends Migration
{
    public function __construct(private readonly int $timestamp)
    {
    }

    public function getCreationTimestamp(): int
    {
        return $this->timestamp;
    }

    public function up(UpMigrationContext $context): void
    {
    }

    public function down(DownMigrationContext $context): void
    {
    }
}
