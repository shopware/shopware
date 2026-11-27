<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal\Common;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\LastIdQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LastIdQuery::class)]
class LastIdQueryTest extends TestCase
{
    public function testUuidCursorPreservesNumericBinaryKeysAndSurvivesEmptyBatches(): void
    {
        $id = '31323334353637383930313233343536';
        $binary = Uuid::fromHexToBytes($id);
        $result = static::createStub(Result::class);
        // Numeric ASCII bytes become an integer array key in PHP.
        $result->method('fetchAllKeyValue')->willReturnOnConsecutiveCalls([$binary => $id], []);
        $query = $this->getMockBuilder(QueryBuilder::class)
            ->setConstructorArgs([static::createStub(Connection::class)])
            ->onlyMethods(['executeQuery'])
            ->getMock();
        $query->expects($this->exactly(2))->method('executeQuery')->willReturn($result);
        $query->setParameter('lastId', '', ParameterType::BINARY);
        $iterator = new LastIdQuery($query);

        static::assertSame([$id], array_values($iterator->fetch()));
        static::assertSame(['offset' => $id], $iterator->getOffset());
        static::assertSame($binary, $query->getParameter('lastId'));
        static::assertSame(ParameterType::BINARY, $query->getParameterType('lastId'));
        static::assertSame([], $iterator->fetch());
        static::assertSame(['offset' => $id], $iterator->getOffset());
    }

    public function testAutoIncrementCursorUsesLastKeyAndSurvivesEmptyBatches(): void
    {
        $result = static::createStub(Result::class);
        $result->method('fetchAllKeyValue')->willReturnOnConsecutiveCalls([12 => 'first', 27 => 'second'], []);
        $query = $this->getMockBuilder(QueryBuilder::class)
            ->setConstructorArgs([static::createStub(Connection::class)])
            ->onlyMethods(['executeQuery'])
            ->getMock();
        $query->expects($this->exactly(2))->method('executeQuery')->willReturn($result);
        $query->setParameter('lastId', 0, ParameterType::INTEGER);
        $iterator = new LastIdQuery($query);

        static::assertSame(['offset' => null], $iterator->getOffset());
        static::assertSame([12 => 'first', 27 => 'second'], $iterator->fetch());
        static::assertSame(['offset' => 27], $iterator->getOffset());
        static::assertSame(27, $query->getParameter('lastId'));
        static::assertSame(ParameterType::INTEGER, $query->getParameterType('lastId'));
        static::assertSame([], $iterator->fetch());
        static::assertSame(['offset' => 27], $iterator->getOffset());
        static::assertSame($query, $iterator->getQuery());
    }
}
