<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Exception\InvalidSortingDirectionException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\SqlQueryParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\EntityScoreQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchTermInterpreter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class CriteriaQueryHelperTest extends TestCase
{
    use CriteriaQueryHelper;
    use IntegrationTestBehaviour;

    public function testInvalidSortingDirection(): void
    {
        $context = Context::createDefaultContext();
        /** @var EntityRepositoryInterface $taxRepository */
        $taxRepository = $this->getContainer()->get('tax.repository');

        $criteria = new Criteria();

        $criteria->addSorting(new FieldSorting('rate', 'invalid direction'));

        static::expectException(InvalidSortingDirectionException::class);
        $taxRepository->search($criteria, $context);
    }

    public function testDoNotSortByScoreIfNoScoreQueryOrSearchTermIsSet(): void
    {
        $productDefinition = $this->getContainer()->get(ProductDefinition::class);
        $queryMock = $this->createMock(QueryBuilder::class);
        $queryMock
            ->expects(static::never())
            ->method('addOrderBy');

        $this->buildQueryByCriteria($queryMock, $productDefinition, new Criteria(), Context::createDefaultContext());
    }

    public function testSortByScoreIfScoreQueryIsSet(): void
    {
        $productDefinition = $this->getContainer()->get(ProductDefinition::class);
        $criteria = new Criteria();
        $criteria->addQuery(new ScoreQuery(new ContainsFilter('name', 'test matching'), 1000));
        $queryMock = $this->createTestProxy(QueryBuilder::class, [$this->createMock(Connection::class)]);
        $queryMock
            ->expects(static::once())
            ->method('addOrderBy')
            ->with('_score', 'DESC');

        $this->buildQueryByCriteria($queryMock, $productDefinition, $criteria, Context::createDefaultContext());
    }

    public function testSortByScoreIfSearchTermIsSet(): void
    {
        $productDefinition = $this->getContainer()->get(ProductDefinition::class);
        $criteria = new Criteria();
        $criteria->setTerm('searchTerm');
        $queryMock = $this->createTestProxy(QueryBuilder::class, [$this->createMock(Connection::class)]);
        $queryMock
            ->expects(static::once())
            ->method('addOrderBy')
            ->with('_score', 'DESC');

        $this->buildQueryByCriteria($queryMock, $productDefinition, $criteria, Context::createDefaultContext());
    }

    public function testSortByScoreAndAdditionalSorting(): void
    {
        $productDefinition = $this->getContainer()->get(ProductDefinition::class);
        $criteria = new Criteria();
        $criteria->setTerm('searchTerm');
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));
        $queryMock = $this->createTestProxy(QueryBuilder::class, [$this->createMock(Connection::class)]);
        $queryMock
            ->expects(static::exactly(2))
            ->method('addOrderBy')
            ->withConsecutive(['MIN(`product`.`created_at`)', 'ASC'], ['_score', 'DESC']);

        $this->buildQueryByCriteria($queryMock, $productDefinition, $criteria, Context::createDefaultContext());
    }

    public function testSortByScoreAndAdditionalSortingWithScore(): void
    {
        $productDefinition = $this->getContainer()->get(ProductDefinition::class);
        $criteria = new Criteria();
        $criteria->setTerm('searchTerm');
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));
        $criteria->addSorting(new FieldSorting('_score', FieldSorting::ASCENDING));
        $queryMock = $this->createTestProxy(QueryBuilder::class, [$this->createMock(Connection::class)]);
        $queryMock
            ->expects(static::exactly(2))
            ->method('addOrderBy')
            ->withConsecutive(['MIN(`product`.`created_at`)', 'ASC'], ['_score', 'ASC']);

        $this->buildQueryByCriteria($queryMock, $productDefinition, $criteria, Context::createDefaultContext());
    }

    protected function getParser(): SqlQueryParser
    {
        return $this->getContainer()->get(SqlQueryParser::class);
    }

    protected function getDefinitionHelper(): EntityDefinitionQueryHelper
    {
        return $this->getContainer()->get(EntityDefinitionQueryHelper::class);
    }

    protected function getInterpreter(): SearchTermInterpreter
    {
        return $this->getContainer()->get(SearchTermInterpreter::class);
    }

    protected function getScoreBuilder(): EntityScoreQueryBuilder
    {
        return $this->getContainer()->get(EntityScoreQueryBuilder::class);
    }
}
