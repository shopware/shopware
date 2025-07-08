<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\NumberRange\ValueGenerator;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementSqlStorage;

/**
 * @internal
 */
class IncrementSqlStorageTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IncrementSqlStorage $storage;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->storage = static::getContainer()->get(IncrementSqlStorage::class);

        $this->connection = static::getContainer()->get(Connection::class);

        $this->connection->executeStatement('DELETE FROM `number_range_state`');
    }

    public function testReserveReturnsIncrementIfStartOfPatternIsLowerThenTheIncrement(): void
    {
        $config = [
            'id' => Uuid::randomHex(),
            'start' => 5,
            'pattern' => 'n',
        ];

        $this->storage->set($config['id'], 10);

        static::assertSame(11, $this->storage->reserve($config));
        static::assertSame(12, $this->storage->reserve($config));
    }

    public function testReserveReturnsWithoutStart(): void
    {
        $config = [
            'id' => Uuid::randomHex(),
            'start' => null,
            'pattern' => 'n',
        ];

        $this->storage->set($config['id'], 10);

        static::assertSame(11, $this->storage->reserve($config));
        static::assertSame(12, $this->storage->reserve($config));
    }

    public function testReserveReturnsWithoutStartAndUnset(): void
    {
        $config = [
            'id' => Uuid::randomHex(),
            'start' => null,
            'pattern' => 'n',
        ];

        static::assertSame(1, $this->storage->reserve($config));
        static::assertSame(2, $this->storage->reserve($config));
    }

    public function testReserveReturnsStartValueIfItIsHigherThanCurrentIncrement(): void
    {
        $config = [
            'id' => Uuid::randomHex(),
            'start' => 10,
            'pattern' => 'n',
        ];

        $this->storage->set($config['id'], 5);

        static::assertSame(10, $this->storage->reserve($config));
        static::assertSame(11, $this->storage->reserve($config));
    }

    public function testReserveReturnsStartValueIfNoValueIsSet(): void
    {
        $config = [
            'id' => Uuid::randomHex(),
            'start' => 10,
            'pattern' => 'n',
        ];

        static::assertSame(10, $this->storage->reserve($config));
        static::assertSame(11, $this->storage->reserve($config));
    }

    public function testPreviewIfValueIsNotSetAndNoStart(): void
    {
        $config = [
            'id' => Uuid::randomHex(),
            'start' => null,
            'pattern' => 'n',
        ];

        static::assertSame(1, $this->storage->preview($config));
        static::assertSame(1, $this->storage->preview($config));
    }

    public function testPreviewWillReturnStartValueIfNoValueIsSet(): void
    {
        $config = [
            'id' => Uuid::randomHex(),
            'start' => 10,
            'pattern' => 'n',
        ];

        static::assertSame(10, $this->storage->preview($config));
        static::assertSame(10, $this->storage->preview($config));
    }

    public function testPreviewWillReturnStartValueIfItHigherThanCurrentIncrementValue(): void
    {
        $config = [
            'id' => Uuid::randomHex(),
            'start' => 10,
            'pattern' => 'n',
        ];

        $this->storage->set($config['id'], 5);

        static::assertSame(10, $this->storage->preview($config));
        static::assertSame(10, $this->storage->preview($config));
    }

    public function testPreviewWillReturnNextValueIfIncrementIsHigherThanStartValue(): void
    {
        $config = [
            'id' => Uuid::randomHex(),
            'start' => 10,
            'pattern' => 'n',
        ];

        $this->storage->set($config['id'], 15);

        static::assertSame(16, $this->storage->preview($config));
        static::assertSame(16, $this->storage->preview($config));
    }

    public function testSetAndList(): void
    {
        $states = [
            Uuid::randomHex() => 10,
            Uuid::randomHex() => 5,
        ];

        static::assertEmpty($this->storage->list());

        foreach ($states as $id => $value) {
            $this->storage->set($id, $value);
        }

        static::assertSame($states, $this->storage->list());
    }
}
