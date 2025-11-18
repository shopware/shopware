<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(QueryBuilder::class)]
class QueryBuilderTest extends TestCase
{
    private QueryBuilder $queryBuilder;

    protected function setUp(): void
    {
        $driver = $this->createMock(Driver::class);
        $driver->method('getDatabasePlatform')->willReturn(new MySQLPlatform());

        $this->queryBuilder = new QueryBuilder(new Connection([], $driver));
    }

    /**
     * @return array<non-empty-string, list<non-empty-string>>
     */
    public static function provideTitlesLookingLikeParameters(): array
    {
        return [
            'named parameter' => ['my :title'],
            'positional parameter' => ['my title ?'],
        ];
    }

    #[DataProvider('provideTitlesLookingLikeParameters')]
    public function testCriteriaTitleLookingLikeParameter(string $title): void
    {
        $this->queryBuilder->select('LOWER(HEX(id))')
            ->from('product_manufacturer')
            ->where('product_manufacturer.id = UNHEX(:id)')
            ->setParameter('id', Uuid::randomHex())
            ->setTitle($title);

        $result = $this->queryBuilder->executeQuery()->fetchOne();
        static::assertNotFalse($result);

        $sql = $this->queryBuilder->getSQL();
        $matches = [];
        preg_match('/-- (.+)\n/', $sql, $matches);
        static::assertSame($title, $matches[1]);
    }
}
