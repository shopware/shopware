<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search\Parser;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\SqlQueryParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ListDefinition;
use Shopware\Core\System\Unit\Aggregate\UnitTranslation\UnitTranslationDefinition;
use Shopware\Core\System\Unit\UnitDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(SqlQueryParser::class)]
class SqlQueryParserTest extends TestCase
{
    public function testParseUnsupportedQueryFilter(): void
    {
        $this->expectException(DataAbstractionLayerException::class);

        $parser = new SqlQueryParser(new EntityDefinitionQueryHelper(), $this->createMock(Connection::class));

        $parser->parse(
            new ScoreQuery(new ContainsFilter('description', 'test'), 250),
            new UnitDefinition(),
            Context::createDefaultContext(),
        );
    }

    public function testParseNegatedEqualsAnyFilterKeepsNullableRows(): void
    {
        $parser = new SqlQueryParser(new EntityDefinitionQueryHelper(), $this->createMock(Connection::class));

        $result = $parser->parse(
            new NotFilter(NotFilter::CONNECTION_AND, [
                new EqualsAnyFilter('shortCode', ['foo']),
            ]),
            $this->getRegistry()->getByEntityName(UnitDefinition::ENTITY_NAME),
            Context::createDefaultContext(),
        );

        static::assertCount(1, $result->getWheres());
        static::assertStringStartsWith('NOT ((', $result->getWheres()[0]);
        static::assertStringContainsString(' IN (:param_', $result->getWheres()[0]);
        static::assertStringContainsString('IS NOT NULL', $result->getWheres()[0]);

        $parameters = array_values($result->getParameters());
        static::assertCount(1, $parameters);
        static::assertSame(['foo'], $parameters[0]);
    }

    public function testParseEmptyEqualsAnyFilterOnListFieldMatchesNothing(): void
    {
        $parser = new SqlQueryParser(new EntityDefinitionQueryHelper(), $this->createMock(Connection::class));

        $result = $parser->parse(
            new EqualsAnyFilter('data', []),
            $this->getRegistry([ListDefinition::class])->getByEntityName(ListDefinition::ENTITY_NAME),
            Context::createDefaultContext(),
        );

        static::assertSame(['1 = 0'], $result->getWheres());
        static::assertSame([], $result->getParameters());
    }

    /**
     * @param list<class-string<EntityDefinition>> $definitions
     */
    private function getRegistry(array $definitions = [UnitDefinition::class, UnitTranslationDefinition::class]): DefinitionInstanceRegistry
    {
        return new StaticDefinitionInstanceRegistry(
            $definitions,
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );
    }
}
