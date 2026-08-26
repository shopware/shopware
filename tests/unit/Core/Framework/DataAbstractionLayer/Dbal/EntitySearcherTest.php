<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
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
    public function testExactTotalCountFallsBackToTheCountQuery(?int $offset, int $limit, int $rows): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))
            ->method('executeQuery')
            ->willReturnOnConsecutiveCalls($this->createIdResult($rows), $this->createCountResult(42));

        $criteria = new Criteria();
        $criteria->setOffset($offset);
        $criteria->setLimit($limit);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $searcher = $this->createSearcher($connection);

        $result = $searcher->search($this->definition, $criteria, Context::createDefaultContext());

        static::assertSame(42, $result->getTotal());
    }

    /**
     * @return iterable<string, array{?int, int, int}>
     */
    public static function notLastPageProvider(): iterable
    {
        // A filled up page may or may not be the last one, so the total has to be counted.
        yield 'full first page' => [0, 2, 2];
        yield 'full page after some full pages' => [4, 2, 2];
        // An empty page behind an offset says nothing about the total, it can be anything up to that offset.
        yield 'empty page past the end' => [10, 2, 0];
    }

    private function createSearcher(Connection $connection): EntitySearcher
    {
        $criteriaQueryBuilder = static::createStub(CriteriaQueryBuilder::class);
        $criteriaQueryBuilder->method('build')->willReturnCallback(
            function (QueryBuilder $query): QueryBuilder {
                $query->from(EntityDefinitionQueryHelper::escape('test_entity'));

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

        $result = static::createStub(Result::class);
        $result->method('fetchAllAssociative')->willReturn($ids);

        return $result;
    }

    private function createCountResult(int $total): Result
    {
        $result = static::createStub(Result::class);
        $result->method('fetchOne')->willReturn($total);

        return $result;
    }
}
