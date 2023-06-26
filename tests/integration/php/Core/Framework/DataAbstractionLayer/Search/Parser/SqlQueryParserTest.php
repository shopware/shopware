<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Parser;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\SqlQueryParser
 */
class SqlQueryParserTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepository $repository;

    private Context $context;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->repository = $this->getContainer()->get('product.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testParseEqualsFilterForListFieldsWithNullValues(): void
    {
        $method = ReflectionHelper::getMethod(SqlQueryParser::class, 'parseEqualsFilter');
        $queryHelper = new EntityDefinitionQueryHelper();
        $parser = new SqlQueryParser($queryHelper, $this->connection);
        $definition = $this->repository->getDefinition();

        $filter = new EqualsFilter('categoryIds', null);
        $expectedResult = new ParseResult();
        $expectedResult->addWhere('`product`.`category_ids` IS NULL');

        $parseResult = $method->invoke(
            $parser,
            $filter,
            $definition,
            $definition->getEntityName(),
            $this->context,
            false
        );

        static::assertEquals($expectedResult, $parseResult);
    }

    public function testParseEqualsFilterForListFields(): void
    {
        $method = ReflectionHelper::getMethod(SqlQueryParser::class, 'parseEqualsFilter');

        $queryHelper = new EntityDefinitionQueryHelper();
        $parser = new SqlQueryParser($queryHelper, $this->connection);
        $definition = $this->repository->getDefinition();

        $filter = new EqualsFilter('categoryIds', 'testvalue123');
        $parseResult = $method->invoke(
            $parser,
            $filter,
            $definition,
            $definition->getEntityName(),
            $this->context,
            false
        );

        static::assertCount(1, $parseResult->getParameters());

        $paramKey = array_keys($parseResult->getParameters())[0];
        $expectedResult = new ParseResult();
        $expectedResult->addWhere('JSON_CONTAINS(`product`.`category_ids`, JSON_ARRAY(:' . $paramKey . '))');
        $expectedResult->addParameter($paramKey, 'testvalue123');

        static::assertEquals($expectedResult, $parseResult);
    }
}
