<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Storage;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Storage\MySQLKeyValueStorage;

/**
 * @internal
 */
#[CoversClass(MySQLKeyValueStorage::class)]
class MySQLKeyValueStorageTest extends TestCase
{
    /**
     * @var Connection&MockObject
     */
    private Connection $connectionMock;

    private MySQLKeyValueStorage $keyValueStorage;

    protected function setUp(): void
    {
        $this->connectionMock = $this->createMock(Connection::class);
        $this->keyValueStorage = new MySQLKeyValueStorage($this->connectionMock);
    }

    public function testHas(): void
    {
        $this->connectionMock->expects(static::once())->method('fetchAllKeyValue')->willReturn([
            'key-1' => 'value-1',
            'key-2' => null,
        ]);

        static::assertTrue($this->keyValueStorage->has('key-1'));
        static::assertTrue($this->keyValueStorage->has('key-2'));
        static::assertFalse($this->keyValueStorage->has('key-3'));
    }

    public function testGet(): void
    {
        $this->connectionMock->expects(static::once())->method('fetchAllKeyValue')->willReturn([
            'key-1' => 'value-1',
            'key-2' => null,
        ]);

        static::assertEquals('value-1', $this->keyValueStorage->get('key-1', 'default'));
        static::assertNull($this->keyValueStorage->get('key-2'));
        static::assertEquals('default', $this->keyValueStorage->get('key-2', 'default'));
        static::assertEquals('default', $this->keyValueStorage->get('key-3', 'default'));
    }

    public function testRemove(): void
    {
        $this->connectionMock->expects(static::once())->method('delete')->with('app_config', [
            '`key`' => 'key-1',
        ]);

        $this->keyValueStorage->remove('key-1');
    }

    public function testSet(): void
    {
        $this->connectionMock->expects(static::once())->method('executeStatement')->with('REPLACE INTO `app_config` (`key`, `value`) VALUES (:key, :value)', [
            'key' => 'key-1',
            'value' => 'value-1',
        ]);

        $this->keyValueStorage->set('key-1', 'value-1');
    }

    public function testGetAfterRemoving(): void
    {
        $this->connectionMock->expects(static::once())->method('fetchAllKeyValue')->willReturn([
            'key-1' => 'value-1',
        ]);

        $this->connectionMock->expects(static::once())->method('delete')->with('app_config', [
            '`key`' => 'key-1',
        ]);

        static::assertTrue($this->keyValueStorage->has('key-1'));

        $this->keyValueStorage->remove('key-1');

        static::assertEquals('default', $this->keyValueStorage->get('key-1', 'default'));
    }

    public function testGetAfterSet(): void
    {
        $this->connectionMock->expects(static::exactly(2))->method('fetchAllKeyValue')->willReturnOnConsecutiveCalls(
            ['key-1' => 'value-1'],
            ['key-1' => null],
        );

        $this->connectionMock->expects(static::once())->method('executeStatement')->with('REPLACE INTO `app_config` (`key`, `value`) VALUES (:key, :value)', [
            'key' => 'key-1',
            'value' => null,
        ]);

        static::assertTrue($this->keyValueStorage->has('key-1'));
        static::assertEquals('value-1', $this->keyValueStorage->get('key-1'));

        $this->keyValueStorage->set('key-1', null);

        static::assertTrue($this->keyValueStorage->has('key-1'));
        static::assertNull($this->keyValueStorage->get('key-1'));
    }
}
