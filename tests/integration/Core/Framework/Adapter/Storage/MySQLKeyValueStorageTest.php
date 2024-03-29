<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Storage;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\Adapter\Storage\MySQLKeyValueStorage;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
#[CoversClass(MySQLKeyValueStorage::class)]
class MySQLKeyValueStorageTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private AbstractKeyValueStorage $keyValueStorage;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->keyValueStorage = new MySQLKeyValueStorage($this->connection);
    }

    public function testSet(): void
    {
        $this->keyValueStorage->set('key-1', 'value-1');
        $this->keyValueStorage->set('key-2', null);
        $this->keyValueStorage->set('key-3', ['a' => 'b']);

        $value = $this->connection->fetchAllKeyValue('SELECT `key`, `value` FROM `app_config` WHERE `key` IN (:keys) ORDER BY `key` ASC', [
            'keys' => ['key-1', 'key-2', 'key-3'],
        ], [
            'keys' => ArrayParameterType::STRING,
        ]);

        static::assertEquals([
            'key-1' => 'value-1',
            'key-2' => '',
            'key-3' => json_encode(['a' => 'b']),
        ], $value);
    }

    #[Depends('testSet')]
    public function testGet(): void
    {
        $this->keyValueStorage->set('key-1', 'value-1');
        $this->keyValueStorage->set('key-2', null);

        static::assertEquals('value-1', $this->keyValueStorage->get('key-1', 'default'));
        static::assertEquals('', $this->keyValueStorage->get('key-2'));
        static::assertEquals('', $this->keyValueStorage->get('key-2', 'default'));
        static::assertEquals('default', $this->keyValueStorage->get('key-3', 'default'));
    }

    #[Depends('testSet')]
    public function testHas(): void
    {
        $this->keyValueStorage->set('key-1', 'value-1');
        $this->keyValueStorage->set('key-2', '');

        static::assertTrue($this->keyValueStorage->has('key-1'));
        static::assertTrue($this->keyValueStorage->has('key-2'));
        static::assertFalse($this->keyValueStorage->has('key-3'));
    }

    #[Depends('testSet')]
    public function testRemove(): void
    {
        $this->keyValueStorage->set('key-1', 'value-1');

        $this->keyValueStorage->remove('key-1');

        static::assertFalse($this->keyValueStorage->has('key-1'));
    }
}
