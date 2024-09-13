<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\ParseResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\SqlQueryParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\EntityScoreQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchTermInterpreter;

/**
 * @internal
 */
#[CoversClass(CriteriaQueryBuilder::class)]
class CriteriaQueryBuilderTest extends TestCase
{
    public function testBuildWithWhereCondition(): void
    {
        $queryBuilder = new QueryBuilder($this->createMock(Connection::class));

        $criteria = new Criteria();
        $criteria->addQuery(new ScoreQuery(
            new ContainsFilter('name', 'test'),
            500
        ));
        $criteria->addQuery(new ScoreQuery(
            new ContainsFilter('description', 'test'),
            250
        ));

        $parser = $this->createMock(SqlQueryParser::class);

        $parserResult = new ParseResult();
        $parserResult->addWhere('IF(`order`.`name` LIKE :param_018f75fcb173706bb1e5a16110f13c1d, \'500\', 0)');
        $parserResult->addWhere('IF(`order`.`description` LIKE :param_018f766366cf70ce8e487d3cc1b513a6, \'250\', 0)');
        $parser->expects(static::once())->method('parseRanking')->willReturn($parserResult);

        $whereParserResult1 = new ParseResult();
        $whereParserResult2 = new ParseResult();
        $whereParserResult1->addWhere('`order`.`name` LIKE :param_018f75fcb173706bb1e5a16110f13c1d');
        $whereParserResult2->addWhere('`order`.`description` LIKE :param_018f766366cf70ce8e487d3cc1b513a6');
        $parser->expects(static::exactly(3))->method('parse')->willReturnOnConsecutiveCalls(new ParseResult(), $whereParserResult1, $whereParserResult2);

        $helper = $this->createMock(EntityDefinitionQueryHelper::class);
        $helper->expects(static::once())->method('getBaseQuery')->willReturn($queryBuilder);

        $builder = new CriteriaQueryBuilder(
            $parser,
            $helper,
            $this->createMock(SearchTermInterpreter::class),
            $this->createMock(EntityScoreQueryBuilder::class),
            $this->createMock(JoinGroupBuilder::class),
            $this->createMock(CriteriaPartResolver::class)
        );

        $definition = $this->returnMockDefinition();
        $definition->compile($this->createMock(DefinitionInstanceRegistry::class));

        $builder->build($queryBuilder, $definition, $criteria, Context::createDefaultContext());

        static::assertEquals(
            CompositeExpression::and(
                '`order`.`name` LIKE :param_018f75fcb173706bb1e5a16110f13c1d OR `order`.`description` LIKE :param_018f766366cf70ce8e487d3cc1b513a6',
            ),
            $queryBuilder->getQueryPart('where'),
        );
    }

    public function testBuildWithoutAddConditions(): void
    {
        $queryBuilder = new QueryBuilder($this->createMock(Connection::class));

        $criteria = new Criteria();
        $criteria->addQuery(new ScoreQuery(
            new ContainsFilter('name', 'test'),
            500
        ));

        $parserResult = new ParseResult();
        $parserResult->addWhere('IF(`order`.`name` LIKE :param_018f75fcb173706bb1e5a16110f13c1d, \'500\', 0)');

        $parser = $this->createMock(SqlQueryParser::class);
        $parser->expects(static::once())->method('parseRanking')->willReturn($parserResult);

        $parser->method('parse')->willReturn(new ParseResult());

        $builder = new CriteriaQueryBuilder(
            $parser,
            $this->createMock(EntityDefinitionQueryHelper::class),
            $this->createMock(SearchTermInterpreter::class),
            $this->createMock(EntityScoreQueryBuilder::class),
            $this->createMock(JoinGroupBuilder::class),
            $this->createMock(CriteriaPartResolver::class)
        );

        $definition = $this->returnMockDefinition();
        $definition->compile($this->createMock(DefinitionInstanceRegistry::class));
        $builder->build($queryBuilder, $definition, $criteria, Context::createDefaultContext());

        static::assertEmpty($queryBuilder->getQueryPart('where'));
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
