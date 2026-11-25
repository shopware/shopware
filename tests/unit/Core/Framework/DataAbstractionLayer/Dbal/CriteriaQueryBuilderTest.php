<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityReader;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\CriteriaPartResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\JoinGroupBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\SqlQueryParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\EntityScoreQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchTermInterpreter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CriteriaQueryBuilder::class)]
class CriteriaQueryBuilderTest extends TestCase
{
    public function testInvalidSortingDirectionException(): void
    {
        $queryBuilder = new QueryBuilder(static::createStub(Connection::class));

        $definition = $this->returnMockDefinition();
        $definition->compile(static::createStub(DefinitionInstanceRegistry::class));

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('name', 'foo'));

        $builder = new CriteriaQueryBuilder(
            static::createStub(SqlQueryParser::class),
            static::createStub(EntityDefinitionQueryHelper::class),
            static::createStub(SearchTermInterpreter::class),
            static::createStub(EntityScoreQueryBuilder::class),
            static::createStub(JoinGroupBuilder::class),
            static::createStub(CriteriaPartResolver::class)
        );

        $this->expectExceptionObject(DataAbstractionLayerException::invalidSortingDirection('foo'));

        $builder->build($queryBuilder, $definition, $criteria, Context::createDefaultContext());
    }

    public function testToManyAssociationLimitQueryKeepsSortingUnaggregated(): void
    {
        $definition = $this->returnMockDefinition();
        $criteria = new Criteria();
        $sorting = new FieldSorting('name', FieldSorting::ASCENDING);
        $criteria->addSorting($sorting);

        $helper = $this->createMock(EntityDefinitionQueryHelper::class);
        $helper->expects($this->exactly(2))
            ->method('getFieldAccessor')
            ->willReturn('`order`.`name`');

        $builder = new CriteriaQueryBuilder(
            static::createStub(SqlQueryParser::class),
            $helper,
            static::createStub(SearchTermInterpreter::class),
            static::createStub(EntityScoreQueryBuilder::class),
            static::createStub(JoinGroupBuilder::class),
            static::createStub(CriteriaPartResolver::class)
        );

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        $groupedQuery = new QueryBuilder($connection);
        $groupedQuery->addState(EntityDefinitionQueryHelper::HAS_TO_MANY_JOIN);

        $builder->addSortings($definition, $criteria, [$sorting], $groupedQuery, Context::createDefaultContext());

        static::assertSame(['MIN(`order`.`name`) ASC'], $groupedQuery->getOrderByParts());

        $limitQuery = new QueryBuilder($connection);
        $limitQuery->addState(EntityDefinitionQueryHelper::HAS_TO_MANY_JOIN);
        $limitQuery->addState(EntityReader::TO_MANY_ASSOCIATION_LIMIT_QUERY);

        $builder->addSortings($definition, $criteria, [$sorting], $limitQuery, Context::createDefaultContext());

        static::assertSame(['`order`.`name` ASC'], $limitQuery->getOrderByParts());
    }

    private function returnMockDefinition(): EntityDefinition
    {
        return new class extends EntityDefinition {
            public function getEntityName(): string
            {
                return 'order';
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection([
                    (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
                    (new TranslatedField('name'))->addFlags(new ApiAware()),
                    new TranslatedField('description'),
                ]);
            }
        };
    }
}
