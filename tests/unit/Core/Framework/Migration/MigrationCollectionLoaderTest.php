<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationException;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MigrationCollectionLoader::class)]
class MigrationCollectionLoaderTest extends TestCase
{
    use EnvTestBehaviour;

    /**
     * @param MigrationCollectionLoader::VERSION_SELECTION_* $mode
     */
    #[TestDox('getLastSafeMajorVersion: $_dataName')]
    #[DataProvider('provideVersionSelections')]
    public function testGetLastSafeMajorVersion(string $version, string $mode, int $expectedMajor): void
    {
        static::assertSame($expectedMajor, $this->createLoader()->getLastSafeMajorVersion($version, $mode));
    }

    public function testFeatureAllDoesNotChangeMigrationVersionSelection(): void
    {
        $this->setEnvVars(['FEATURE_ALL' => '1']);

        static::assertSame(5, $this->createLoader()->getLastSafeMajorVersion('6.5.2', MigrationCollectionLoader::VERSION_SELECTION_ALL));
        static::assertSame(4, $this->createLoader()->getLastSafeMajorVersion('6.5.2', MigrationCollectionLoader::VERSION_SELECTION_BLUE_GREEN));
    }

    public function testMajorFlagDoesNotChangeMigrationVersionSelection(): void
    {
        Feature::registerFeature('v6.8.0.0', ['default' => false]);
        $this->setEnvVars(['V6_8_0_0' => '1']);

        static::assertSame(7, $this->createLoader()->getLastSafeMajorVersion('6.7.2'));
    }

    public function testGetLastSafeMajorVersionRejectsUnknownMode(): void
    {
        $this->expectExceptionObject(MigrationException::invalidVersionSelectionMode('yolo'));

        /** @phpstan-ignore argument.type (deliberately invalid mode to hit the guard) */
        $this->createLoader()->getLastSafeMajorVersion('6.5.2', 'yolo');
    }

    public function testCollectRejectsUnknownSource(): void
    {
        $this->expectExceptionObject(MigrationException::unknownMigrationSource('does-not-exist'));

        $this->createLoader()->collect('does-not-exist');
    }

    public function testCollectAndCollectAllUseTheSourceNames(): void
    {
        $loader = $this->createLoader(
            new MigrationSource('core'),
            new MigrationSource('core.V6_3'),
        );

        static::assertSame('core.V6_3', $loader->collect('core.V6_3')->getName());
        static::assertSame(['core', 'core.V6_3'], array_keys($loader->collectAll()));
    }

    public function testCollectAllForVersionMergesAllMajorsUpToTheSafeOne(): void
    {
        $loader = $this->createLoader(
            new MigrationSource('core'),
            new MigrationSource('core.V6_3'),
            new MigrationSource('core.V6_4'),
            new MigrationSource('core.V6_5'),
            new MigrationSource('core.V6_6'),
        );

        // '6.5.2' in blue-green mode → safe major 4 → V6_3 + V6_4 (+ core), V6_5/V6_6 excluded
        $collection = $loader->collectAllForVersion('6.5.2', MigrationCollectionLoader::VERSION_SELECTION_BLUE_GREEN);

        static::assertSame('allForVersion', $collection->getName());
    }

    public function testCollectAllForVersionRequiresTheMajorSources(): void
    {
        $loader = $this->createLoader(new MigrationSource('core'));

        $this->expectExceptionObject(MigrationException::unknownMigrationSource('core.V6_3'));

        $loader->collectAllForVersion('6.5.2');
    }

    /**
     * @return \Generator<string, array{0: string, 1: MigrationCollectionLoader::VERSION_SELECTION_*, 2: int}>
     */
    public static function provideVersionSelections(): \Generator
    {
        yield 'all mode returns the current major' => ['6.5.2', MigrationCollectionLoader::VERSION_SELECTION_ALL, 5];
        yield 'all mode ignores the minor' => ['6.5.0', MigrationCollectionLoader::VERSION_SELECTION_ALL, 5];
        yield 'safe mode returns the penultimate major' => ['6.5.2', MigrationCollectionLoader::VERSION_SELECTION_SAFE, 3];
        yield 'blue-green on a later minor returns the previous major' => ['6.5.2', MigrationCollectionLoader::VERSION_SELECTION_BLUE_GREEN, 4];
        yield 'blue-green on the first minor goes one major further back' => ['6.5.0', MigrationCollectionLoader::VERSION_SELECTION_BLUE_GREEN, 3];
        yield 'all mode selects the 6.8 namespace for a 6.8 version' => ['6.8.0.0', MigrationCollectionLoader::VERSION_SELECTION_ALL, 8];
        yield 'safe mode excludes the previous major for a 6.8 version' => ['6.8.0.0', MigrationCollectionLoader::VERSION_SELECTION_SAFE, 6];
        yield 'blue-green excludes two majors on the first 6.8 minor' => ['6.8.0.0', MigrationCollectionLoader::VERSION_SELECTION_BLUE_GREEN, 6];
        yield 'blue-green includes the previous major on a later 6.8 minor' => ['6.8.1.0', MigrationCollectionLoader::VERSION_SELECTION_BLUE_GREEN, 7];
    }

    private function createLoader(MigrationSource ...$sources): MigrationCollectionLoader
    {
        return new MigrationCollectionLoader(
            static::createStub(Connection::class),
            static::createStub(MigrationRuntime::class),
            new NullLogger(),
            $sources,
        );
    }
}
