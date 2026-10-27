<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\RowLoader;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\Doctrine\FakeResultFactory;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RowLoader::class)]
class RowLoaderTest extends TestCase
{
    private Connection&MockObject $connection;

    private RowLoader $loader;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->connection->method('createQueryBuilder')->willReturnCallback(fn (): QueryBuilder => new QueryBuilder($this->connection));
        $this->connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());

        $this->loader = new RowLoader($this->connection);
    }

    public function testTheGivenColumnsOfTheRowsWithTheGivenPrimaryKeysAreLoaded(): void
    {
        $rows = [['id' => 'a', 'stock' => '1']];

        $this->connection->expects($this->once())
            ->method('executeQuery')
            ->with(
                'SELECT `id`, `stock` FROM `product` WHERE `id` = :param1 AND `version_id` = :param2 OR `id` = :param3 AND `version_id` = :param4',
                ['param1' => 'a', 'param2' => 'live', 'param3' => 'b', 'param4' => 'live'],
            )
            ->willReturn(FakeResultFactory::createResult($rows, $this->connection));

        static::assertSame($rows, $this->loader->load('product', [
            ['id' => 'a', 'version_id' => 'live'],
            ['id' => 'b', 'version_id' => 'live'],
        ], ['id', 'stock']));
    }

    public function testAllColumnsAreLoadedWhenNoneAreGiven(): void
    {
        $this->connection->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT * FROM `product` WHERE `id` = :param1')
            ->willReturn(FakeResultFactory::createResult([], $this->connection));

        $this->loader->load('product', [['id' => 'a']]);
    }

    public function testNothingIsLoadedWithoutPrimaryKeys(): void
    {
        $this->connection->expects($this->never())->method('executeQuery');

        static::assertSame([], $this->loader->load('product', [], ['id']));
    }
}
