<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\Reversible;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationException;
use Shopware\Core\Framework\Migration\Reversible\Migration;
use Shopware\Core\Framework\Migration\Reversible\MigrationProvider;
use Shopware\Core\Framework\Plugin;
use Shopware\Tests\Unit\Core\Framework\Migration\Reversible\_fixtures\SwagDuplicateTimestamp\Migration\Migration1600000000A;
use Shopware\Tests\Unit\Core\Framework\Migration\Reversible\_fixtures\SwagDuplicateTimestamp\Migration\Migration1600000000B;
use Shopware\Tests\Unit\Core\Framework\Migration\Reversible\_fixtures\SwagDuplicateTimestamp\SwagDuplicateTimestamp;
use Shopware\Tests\Unit\Core\Framework\Migration\Reversible\_fixtures\SwagInvalidTimestamp\Migration\Migration0Invalid;
use Shopware\Tests\Unit\Core\Framework\Migration\Reversible\_fixtures\SwagInvalidTimestamp\SwagInvalidTimestamp;
use Shopware\Tests\Unit\Core\Framework\Migration\Reversible\_fixtures\SwagNoMigrations\SwagNoMigrations;
use Shopware\Tests\Unit\Core\Framework\Migration\Reversible\_fixtures\SwagNotInstantiable\Migration\Migration1700000000NeedsArguments;
use Shopware\Tests\Unit\Core\Framework\Migration\Reversible\_fixtures\SwagNotInstantiable\SwagNotInstantiable;
use Shopware\Tests\Unit\Core\Framework\Migration\Reversible\_fixtures\SwagReversible\Migration\Migration1000000000Second;
use Shopware\Tests\Unit\Core\Framework\Migration\Reversible\_fixtures\SwagReversible\Migration\Migration2000000000First;
use Shopware\Tests\Unit\Core\Framework\Migration\Reversible\_fixtures\SwagReversible\SwagReversible;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MigrationProvider::class)]
class MigrationProviderTest extends TestCase
{
    public function testReturnsNothingWhenMigrationDirectoryIsMissing(): void
    {
        $provider = new MigrationProvider();

        static::assertSame([], $provider->forPlugin($this->plugin(SwagNoMigrations::class)));
    }

    public function testSortsByTimestampAndSkipsLegacyMigrationSteps(): void
    {
        $provider = new MigrationProvider();

        $migrations = $provider->forPlugin($this->plugin(SwagReversible::class));

        // Migration1500000000LegacyStep extends MigrationStep and must not be picked up
        static::assertSame(
            [Migration1000000000Second::class, Migration2000000000First::class],
            array_map(static fn (Migration $migration): string => $migration::class, $migrations)
        );
    }

    public function testCachesResultPerPlugin(): void
    {
        $provider = new MigrationProvider();
        $plugin = $this->plugin(SwagReversible::class);

        static::assertEquals($provider->forPlugin($plugin), $provider->forPlugin($plugin));
    }

    public function testRejectsDuplicateTimestamps(): void
    {
        $provider = new MigrationProvider();

        $this->expectExceptionObject(MigrationException::duplicateMigrationTimestamp(
            'SwagDuplicateTimestamp',
            1600000000,
            Migration1600000000A::class,
            Migration1600000000B::class
        ));

        $provider->forPlugin($this->plugin(SwagDuplicateTimestamp::class));
    }

    public function testRejectsInvalidTimestamps(): void
    {
        $provider = new MigrationProvider();

        $this->expectExceptionObject(
            MigrationException::reversibleMigrationInvalidTimestamp(Migration0Invalid::class, 0)
        );

        $provider->forPlugin($this->plugin(SwagInvalidTimestamp::class));
    }

    public function testRejectsMigrationsWithRequiredConstructorArguments(): void
    {
        $provider = new MigrationProvider();

        $this->expectExceptionObject(
            MigrationException::reversibleMigrationNotInstantiable(Migration1700000000NeedsArguments::class)
        );

        $provider->forPlugin($this->plugin(SwagNotInstantiable::class));
    }

    /**
     * @param class-string<Plugin> $class
     */
    private function plugin(string $class): Plugin
    {
        $directory = \dirname((string) (new \ReflectionClass($class))->getFileName());

        return new $class(true, $directory);
    }
}
