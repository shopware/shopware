<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\Reversible;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\Reversible\ExecutedMigration;
use Shopware\Tests\Unit\Core\Framework\Migration\Reversible\_fixtures\RecordingMigrationA;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ExecutedMigration::class)]
class ExecutedMigrationTest extends TestCase
{
    public function testExposesTheClassAndCreationTimestamp(): void
    {
        $migration = new ExecutedMigration(RecordingMigrationA::class, 1787465993);

        static::assertSame(RecordingMigrationA::class, $migration->class);
        static::assertSame(1787465993, $migration->creationTimestamp);
    }
}
