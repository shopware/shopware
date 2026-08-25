<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;

/**
 * @internal
 */
#[CoversClass(QueryBuilder::class)]
class QueryBuilderTest extends TestCase
{
    public function testCriteriaTitleWithControlCharactersStaysInTheSqlComment(): void
    {
        $driver = static::createStub(Driver::class);
        $driver->method('getDatabasePlatform')->willReturn(new MySQLPlatform());

        $queryBuilder = new QueryBuilder(new Connection([], $driver));
        $queryBuilder->select('id')
            ->from('product_manufacturer')
            ->setTitle("first\0\r\nsecond");

        $sql = $queryBuilder->getSQL();

        static::assertStringStartsWith('-- first   second' . \PHP_EOL, $sql);
        static::assertSame(1, substr_count($sql, \PHP_EOL));
    }
}
