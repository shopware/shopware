<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\NumberRange\ValueGenerator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\NumberRange\NumberRangeException;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\AbstractIncrementStorage;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementStorageRegistry;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(IncrementStorageRegistry::class)]
class IncrementStorageRegistryTest extends TestCase
{
    private IncrementStorageRegistry $registry;

    private AbstractIncrementStorage&Stub $mainStorage;

    private AbstractIncrementStorage&Stub $secondaryStorage;

    protected function setUp(): void
    {
        $this->mainStorage = static::createStub(AbstractIncrementStorage::class);
        $this->secondaryStorage = static::createStub(AbstractIncrementStorage::class);

        $this->registry = new IncrementStorageRegistry(
            new ServiceLocator([
                'main' => fn () => $this->mainStorage,
                'secondary' => fn () => $this->secondaryStorage,
            ]),
            'main'
        );
    }

    public function testGetDefaultStorage(): void
    {
        $storage = $this->registry->getStorage();
        static::assertSame($this->mainStorage, $storage);
    }

    public function testGetNamedStorage(): void
    {
        $storage = $this->registry->getStorage('secondary');
        static::assertSame($this->secondaryStorage, $storage);
    }

    public function testGetUnknownStorageThrows(): void
    {
        static::expectExceptionObject(NumberRangeException::incrementStorageNotFound('foo', ['main', 'secondary']));
        $this->registry->getStorage('foo');
    }

    public function testMigrate(): void
    {
        $sourceValues = [
            'k1' => 0,
            'k2' => 15,
        ];

        // The one test asserting an interaction gets its own mock; the shared property stays a stub.
        $mainStorage = $this->createMock(AbstractIncrementStorage::class);
        $mainStorage->expects($this->once())
            ->method('list')
            ->willReturn($sourceValues);

        $registry = new IncrementStorageRegistry(
            new ServiceLocator([
                'main' => fn () => $mainStorage,
                'secondary' => fn () => $this->secondaryStorage,
            ]),
            'main'
        );

        $targetValues = [];
        $this->secondaryStorage->method('set')
            ->willReturnCallback(static function (string $configurationId, int $value) use (&$targetValues): void {
                $targetValues[$configurationId] = $value;
            });

        $registry->migrate('main', 'secondary');
        static::assertSame($sourceValues, $targetValues);
    }

    public function testMigrateWithUnknownFromStorageThrows(): void
    {
        static::expectExceptionObject(NumberRangeException::incrementStorageNotFound('foo', ['main', 'secondary']));
        $this->registry->migrate('foo', 'secondary');
    }

    public function testMigrateWithUnknownToStorageThrows(): void
    {
        static::expectExceptionObject(NumberRangeException::incrementStorageNotFound('foo', ['main', 'secondary']));
        $this->registry->migrate('main', 'foo');
    }
}
