<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntitySearcher;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntitySearcher::class)]
class EntitySearcherTest extends TestCase
{
    private EntityDefinition $definition;

    protected function setUp(): void
    {
        $this->definition = new class extends EntityDefinition {
            public function getEntityName(): string
            {
                return 'test_entity';
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection([
                    (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
                ]);
            }
        };

        new StaticDefinitionInstanceRegistry(
            [$this->definition],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class)
        );
    }

    #[DataProvider('lastPageProvider')]
    public function testExactTotalCountSkipsTheCountQueryOnTheLastPage(?int $offset, ?int $limit, int $rows, int $expectedTotal): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeQuery')
            ->willReturn($this->createIdResult($rows));

        $criteria = new Criteria();
        $criteria->setOffset($offset);
        $criteria->setLimit($limit);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $searcher = $this->createSearcher($connection);

        $result = $searcher->search($this->definition, $criteria, Context::createDefaultContext());

        static::assertSame($expectedTotal, $result->getTotal());
        static::assertCount($rows, $result->getIds());
    }

    /**
     * @return iterable<string, array{?int, ?int, int, int}>
     */
    public static function lastPageProvider(): iterable
    {
        // A page that is not filled up is the last one, so the total is offset + fetched rows.
        yield 'partial page after some full pages' => [4, 4, 2, 6];
        yield 'partial first page' => [0, 25, 6, 6];
        yield 'unpaginated criteria' => [null, null, 6, 6];
        // Without an offset an empty page proves that nothing matches at all.
        yield 'empty first page' => [0, 25, 0, 0];
        yield 'empty unpaginated criteria' => [null, null, 0, 0];
    }

    #[DataProvider('notLastPageProvider')]
    public function testExactTotalCountFallsBackToTheCountQuery(?int $offset, int $limit, int $rows, bool $scored = false, bool $grouped = false, bool $toManyJoin = false): void
    {
        $statements = [];
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $connection->expects($this->exactly(2))
            ->method('executeQuery')
            ->willReturnCallback(function (string $sql) use (&$statements, $rows): Result {
                $statements[] = $sql;

                return \count($statements) === 1 ? $this->createIdResult($rows) : $this->createCountResult(42);
            });

        $criteria = new Criteria();
        $criteria->setOffset($offset);
        $criteria->setLimit($limit);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        if ($grouped) {
            $criteria->addGroupField(new FieldGrouping('name'));
        }

        $searcher = $this->createSearcher($connection, scored: $scored, toManyJoin: $toManyJoin);

        $result = $searcher->search($this->definition, $criteria, Context::createDefaultContext());

        static::assertSame(42, $result->getTotal());
        static::assertSame($scored || $grouped || $toManyJoin, str_contains($statements[0], 'COUNT(*) OVER()'));
        static::assertStringStartsWith('SELECT COUNT(*) FROM', $statements[1]);
    }

    /**
     * @return iterable<string, array{0: ?int, 1: int, 2: int, 3?: bool, 4?: bool, 5?: bool}>
     */
    public static function notLastPageProvider(): iterable
    {
        // A filled up page may or may not be the last one, so the total has to be counted.
        // Without a score, group fields or a to-many join, the search query does not select a window total for it.
        yield 'full first page' => [0, 2, 2];
        yield 'full page after some full pages' => [4, 2, 2];
        // An empty page behind an offset says nothing about the total, it can be anything up to that offset.
        yield 'empty page past the end' => [10, 2, 0];
        yield 'empty page past the end of a scored search' => [10, 2, 0, true];
        yield 'empty page past the end of a grouped search' => [10, 2, 0, false, true];
        yield 'empty page past the end of a search with a to-many join' => [10, 2, 0, false, false, true];
    }

    /**
     * @param array<string, string> $row
     * @param array<string, string> $expectedData
     */
    #[DataProvider('windowTotalProvider')]
    public function testExactTotalCountIsReadFromTheSearchQuery(bool $scored, bool $grouped, bool $toManyJoin, array $row, array $expectedData): void
    {
        $id = Uuid::randomHex();
        $statements = [];
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $connection->expects($this->once())
            ->method('executeQuery')
            ->willReturnCallback(function (string $sql) use (&$statements, $id, $row): Result {
                $statements[] = $sql;

                return $this->createRowResult([['id' => Uuid::fromHexToBytes($id), ...$row]]);
            });

        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        if ($grouped) {
            $criteria->addGroupField(new FieldGrouping('name'));
        }

        $result = $this->createSearcher($connection, scored: $scored, toManyJoin: $toManyJoin)
            ->search($this->definition, $criteria, Context::createDefaultContext());

        static::assertSame(42, $result->getTotal());
        static::assertSame([$id], $result->getIds());
        static::assertSame(['id' => $id, ...$expectedData], $result->getDataOfId($id));
        static::assertStringContainsString('COUNT(*) OVER()', $statements[0]);
        static::assertSame($scored, str_contains($statements[0], 'counted.`_score`'));
    }

    /**
     * @return iterable<string, array{bool, bool, bool, array<string, string>, array<string, string>}>
     */
    public static function windowTotalProvider(): iterable
    {
        yield 'scored search' => [true, false, false, ['_score' => '300', '_total' => '42'], ['_score' => '300']];
        yield 'grouped search' => [false, true, false, ['_total' => '42'], []];
        yield 'scored grouped search' => [true, true, false, ['_score' => '300', '_total' => '42'], ['_score' => '300']];
        yield 'search with a to-many join' => [false, false, true, ['_total' => '42'], []];
    }

    public function testScoredSearchWithoutExactTotalCountReadsNoWindowTotal(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeQuery')
            ->willReturn($this->createIdResult(2));

        $criteria = new Criteria();
        $criteria->setLimit(2);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NONE);

        $result = $this->createSearcher($connection, scored: true)
            ->search($this->definition, $criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
    }

    private function createSearcher(Connection $connection, bool $scored = false, bool $toManyJoin = false): EntitySearcher
    {
        $criteriaQueryBuilder = static::createStub(CriteriaQueryBuilder::class);
        $criteriaQueryBuilder->method('build')->willReturnCallback(
            function (QueryBuilder $query) use ($scored, $toManyJoin): QueryBuilder {
                $query->from(EntityDefinitionQueryHelper::escape('test_entity'));

                if ($toManyJoin) {
                    $query->addState(EntityDefinitionQueryHelper::HAS_TO_MANY_JOIN);
                }

                if ($scored) {
                    $query->addSelect('1 as _score');
                    $query->addOrderBy(Criteria::SCORE_FIELD, 'DESC');
                    $query->addOrderBy('`test_entity`.`name`', 'ASC');
                    $query->addState(Criteria::SCORE_FIELD);
                }

                return $query;
            }
        );

        return new EntitySearcher(
            $connection,
            static::createStub(EntityDefinitionQueryHelper::class),
            $criteriaQueryBuilder
        );
    }

    private function createIdResult(int $rows): Result
    {
        $ids = [];
        for ($i = 0; $i < $rows; ++$i) {
            $ids[] = ['id' => Uuid::fromHexToBytes(Uuid::randomHex())];
        }

        return $this->createRowResult($ids);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function createRowResult(array $rows): Result
    {
        $result = static::createStub(Result::class);
        $result->method('fetchAllAssociative')->willReturn($rows);

        return $result;
    }

    private function createCountResult(int $total): Result
    {
        $result = static::createStub(Result::class);
        $result->method('fetchOne')->willReturn($total);

        return $result;
    }
}
