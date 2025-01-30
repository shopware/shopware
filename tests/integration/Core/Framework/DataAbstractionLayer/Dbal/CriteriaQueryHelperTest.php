<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Exception\InvalidSortingDirectionException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class CriteriaQueryHelperTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testInvalidSortingDirection(): void
    {
        $context = Context::createDefaultContext();
        /** @var EntityRepository $taxRepository */
        $taxRepository = static::getContainer()->get('tax.repository');

        $criteria = new Criteria();

        $criteria->addSorting(new FieldSorting('rate', 'invalid direction'));

        static::expectException(InvalidSortingDirectionException::class);
        $taxRepository->search($criteria, $context);
    }

    public function testDoNotSortByScoreAutomaticallyIfNoScoreQueryOrSearchTermIsSet(): void
    {
        $productDefinition = static::getContainer()->get(ProductDefinition::class);
        $queryMock = $this->createMock(QueryBuilder::class);
        $queryMock
            ->expects(static::never())
            ->method('addOrderBy');

        $builder = static::getContainer()->get(CriteriaQueryBuilder::class);
        $builder->build($queryMock, $productDefinition, new Criteria(), Context::createDefaultContext());
    }

    public function testDoNotSortByScoreManuallyIfNoScoreQueryOrSearchTermIsSet(): void
    {
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('_score'));
        $productDefinition = static::getContainer()->get(ProductDefinition::class);
        $queryMock = $this->createMock(QueryBuilder::class);
        $queryMock
            ->expects(static::never())
            ->method('addOrderBy');

        $builder = static::getContainer()->get(CriteriaQueryBuilder::class);
        $builder->build($queryMock, $productDefinition, $criteria, Context::createDefaultContext());
    }

    public function testSortByScoreIfScoreQueryIsSet(): void
    {
        $productDefinition = static::getContainer()->get(ProductDefinition::class);
        $criteria = new Criteria();
        $criteria->addQuery(new ScoreQuery(new ContainsFilter('name', 'test matching'), 1000));
        $queryMock = $this->createMock(QueryBuilder::class);
        $queryMock
            ->expects(static::once())
            ->method('addOrderBy')
            ->with('_score', 'DESC');

        $builder = static::getContainer()->get(CriteriaQueryBuilder::class);
        $builder->build($queryMock, $productDefinition, $criteria, Context::createDefaultContext());
    }

    public function testSortByScoreIfSearchTermIsSet(): void
    {
        $productDefinition = static::getContainer()->get(ProductDefinition::class);
        $criteria = new Criteria();
        $criteria->setTerm('searchTerm');
        $queryMock = $this->createMock(QueryBuilder::class);
        $queryMock
            ->expects(static::once())
            ->method('addOrderBy')
            ->with('_score', 'DESC');

        $builder = static::getContainer()->get(CriteriaQueryBuilder::class);
        $builder->build($queryMock, $productDefinition, $criteria, Context::createDefaultContext());
    }

    public function testSortByScoreAndAdditionalSorting(): void
    {
        $productDefinition = static::getContainer()->get(ProductDefinition::class);
        $criteria = new Criteria();
        $criteria->setTerm('searchTerm');
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));

        $queryBuilder = new QueryBuilder($this->createMock(Connection::class));

        $builder = static::getContainer()->get(CriteriaQueryBuilder::class);
        $builder->build($queryBuilder, $productDefinition, $criteria, Context::createDefaultContext());

        static::assertEquals($queryBuilder->getQueryPart('orderBy'), [
            'MIN(`product`.`created_at`) ASC',
            '_score DESC',
        ]);
    }

    public function testSortByScoreAndAdditionalSortingWithScore(): void
    {
        $productDefinition = static::getContainer()->get(ProductDefinition::class);
        $criteria = new Criteria();
        $criteria->setTerm('searchTerm');
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));
        $criteria->addSorting(new FieldSorting('_score', FieldSorting::ASCENDING));
        $queryBuilder = new QueryBuilder($this->createMock(Connection::class));

        $builder = static::getContainer()->get(CriteriaQueryBuilder::class);
        $builder->build($queryBuilder, $productDefinition, $criteria, Context::createDefaultContext());

        static::assertEquals($queryBuilder->getQueryPart('orderBy'), [
            'MIN(`product`.`created_at`) ASC',
            '_score ASC',
        ]);
    }
}
