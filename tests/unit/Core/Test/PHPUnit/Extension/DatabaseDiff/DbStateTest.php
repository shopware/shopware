<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\PHPUnit\Extension\DatabaseDiff;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Test\PHPUnit\Extension\DatabaseDiff\DbState;

/**
 * @internal
 */
#[CoversClass(DbState::class)]
class DbStateTest extends TestCase
{
    private Connection&MockObject $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
    }

    public function testRememberCurrentDbState(): void
    {
        $dbState = new DbState($this->connection);

        $this->connection
            ->expects(static::once())
            ->method('fetchAllAssociative')
            ->willReturn([['table1'], ['table2']]);

        $this->connection
            ->expects(static::exactly(2))
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls(10, 20);

        $dbState->rememberCurrentDbState();

        static::assertEquals(['table1' => 10, 'table2' => 20], $dbState->tableCounts);
    }

    public function testGetDiff(): void
    {
        $dbState = new DbState($this->connection);

        $dbState->tableCounts = ['table1' => 10, 'table2' => 20];

        $this->connection
            ->expects(static::once())
            ->method('fetchAllAssociative')
            ->willReturn([['table1'], ['table3']]);

        $this->connection
            ->expects(static::exactly(2))
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls(15, 30);

        $diff = $dbState->getDiff();

        static::assertEquals(['added' => ['table3'], 'deleted' => ['table2'], 'changed' => ['table1' => 5]], $diff);
    }
}
