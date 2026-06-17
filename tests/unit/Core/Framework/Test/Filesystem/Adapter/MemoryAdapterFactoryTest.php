<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Test\Filesystem\Adapter;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\Filesystem\Adapter\MemoryAdapterFactory;

/**
 * @internal
 */
#[CoversClass(MemoryAdapterFactory::class)]
class MemoryAdapterFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        // The factory tracks created adapters in a static registry; start from a clean slate so this
        // test is isolated from any other test that used the memory filesystem.
        MemoryAdapterFactory::resetInstances();
    }

    protected function tearDown(): void
    {
        MemoryAdapterFactory::resetInstances();
    }

    #[TestDox('create() produces an in-memory adapter advertised as the "memory" type')]
    public function testCreateProducesInMemoryAdapter(): void
    {
        $factory = new MemoryAdapterFactory();

        static::assertSame('memory', $factory->getType());
        static::assertInstanceOf(InMemoryFilesystemAdapter::class, $factory->create([]));
    }

    #[TestDox('clearInstancesMemory() wipes files written to every adapter the factory created')]
    public function testClearInstancesMemoryWipesWrittenFiles(): void
    {
        $factory = new MemoryAdapterFactory();
        $filesystem = new Filesystem($factory->create([]));

        $filesystem->write('testFile', 'testContent');
        $filesystem->write('public/testFile', 'testContent');
        $beforeClear = $filesystem->listContents('', true)->toArray();
        static::assertNotEmpty($beforeClear);

        MemoryAdapterFactory::clearInstancesMemory();

        $afterClear = $filesystem->listContents('', true)->toArray();
        static::assertEmpty($afterClear);
    }

    #[TestDox('clearInstancesMemory() is a no-op when no adapter has been created')]
    public function testClearInstancesMemoryWithoutAdaptersDoesNotFail(): void
    {
        MemoryAdapterFactory::clearInstancesMemory();

        static::expectNotToPerformAssertions();
    }

    #[TestDox('resetInstances() stops tracking previously created adapters')]
    public function testResetInstancesStopsTrackingAdapters(): void
    {
        $factory = new MemoryAdapterFactory();
        $filesystem = new Filesystem($factory->create([]));

        MemoryAdapterFactory::resetInstances();

        // The adapter is no longer tracked, so a subsequent clear must leave its contents untouched.
        $filesystem->write('testFile', 'testContent');
        MemoryAdapterFactory::clearInstancesMemory();

        static::assertNotEmpty($filesystem->listContents('', true)->toArray());
    }
}
