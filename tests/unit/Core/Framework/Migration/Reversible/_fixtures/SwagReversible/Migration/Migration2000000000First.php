<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\Reversible\_fixtures\SwagReversible\Migration;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\Reversible\DownMigrationContext;
use Shopware\Core\Framework\Migration\Reversible\Migration;
use Shopware\Core\Framework\Migration\Reversible\UpMigrationContext;

/**
 * @internal
 */
#[Package('framework')]
class Migration2000000000First extends Migration
{
    /**
     * @var list<string>
     */
    public static array $calls = [];

    public function getCreationTimestamp(): int
    {
        return 2000000000;
    }

    public function up(UpMigrationContext $context): void
    {
        self::$calls[] = 'up';
    }

    public function down(DownMigrationContext $context): void
    {
        self::$calls[] = 'down';
    }
}
