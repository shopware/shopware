<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search\Parser;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\CustomFieldsAccessorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\JsonFieldAccessorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\SqlQueryParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\JsonDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ListDefinition;
use Shopware\Core\System\CustomField\CustomFieldService;
use Shopware\Core\System\Unit\Aggregate\UnitTranslation\UnitTranslationDefinition;
use Shopware\Core\System\Unit\UnitDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\Doctrine\FakeConnection;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SqlQueryParser::class)]
class SqlQueryParserTest extends TestCase
{
    public function testParseUnsupportedQueryFilter(): void
    {
        $this->expectException(DataAbstractionLayerException::class);

        $parser = new SqlQueryParser(new EntityDefinitionQueryHelper(), static::createStub(Connection::class));

        $parser->parse(
            new ScoreQuery(new ContainsFilter('description', 'test'), 250),
            new UnitDefinition(),
            Context::createDefaultContext(),
        );
    }

    public function testParseNegatedEqualsAnyFilterKeepsNullableRows(): void
    {
        $parser = new SqlQueryParser(new EntityDefinitionQueryHelper(), static::createStub(Connection::class));

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
        $parser = new SqlQueryParser(new EntityDefinitionQueryHelper(), static::createStub(Connection::class));

        $result = $parser->parse(
            new EqualsAnyFilter('data', []),
            $this->getRegistry([ListDefinition::class])->getByEntityName(ListDefinition::ENTITY_NAME),
            Context::createDefaultContext(),
        );

        static::assertSame(['1 = 0'], $result->getWheres());
        static::assertSame([], $result->getParameters());
    }

    #[DataProvider('equalsFilterOnJsonFieldProvider')]
    public function testParseEqualsFilterCastsTheValueLikeTheJsonFieldAccessor(string $entityName, string $field, string $expectedValueSql): void
    {
        $parser = new SqlQueryParser(new EntityDefinitionQueryHelper(), static::createStub(Connection::class));

        $result = $parser->parse(
            new EqualsFilter($field, '1990-01-01'),
            $this->getRegistry([UnitDefinition::class, UnitTranslationDefinition::class, JsonDefinition::class])->getByEntityName($entityName),
            Context::createDefaultContext(),
        );

        $key = array_key_first($result->getParameters());
        static::assertIsString($key);
        static::assertCount(1, $result->getWheres());
        static::assertStringEndsWith(' = ' . str_replace('#key#', $key, $expectedValueSql), $result->getWheres()[0]);
        static::assertSame('1990-01-01', $result->getParameters()[$key]);
    }

    public static function equalsFilterOnJsonFieldProvider(): \Generator
    {
        yield 'datetime custom field is cast to datetime' => [UnitDefinition::ENTITY_NAME, 'customFields.my_date', 'CAST(:#key# AS datetime(6))'];
        yield 'datetime custom field behind an association is cast to datetime' => [UnitTranslationDefinition::ENTITY_NAME, 'unit_translation.unit.customFields.my_date', 'CAST(:#key# AS datetime(6))'];
        yield 'datetime custom field reached through an extensions segment is cast to datetime' => [UnitTranslationDefinition::ENTITY_NAME, 'unit_translation.extensions.unit.customFields.my_date', 'CAST(:#key# AS datetime(6))'];
        yield 'text custom field keeps the plain value' => [UnitDefinition::ENTITY_NAME, 'customFields.my_text', ':#key#'];
        yield 'date field in the property mapping of a JSON field is cast to date' => [JsonDefinition::ENTITY_NAME, 'root.child.childDate', 'CAST(:#key# AS DATE)'];
    }

    public function testParseRangeFilterCastsBothBoundsOfADatetimeCustomField(): void
    {
        $parser = new SqlQueryParser(new EntityDefinitionQueryHelper(), static::createStub(Connection::class));

        $result = $parser->parse(
            new RangeFilter('customFields.my_date', [RangeFilter::GTE => '1990-01-01', RangeFilter::LT => '1990-01-02']),
            $this->getRegistry()->getByEntityName(UnitDefinition::ENTITY_NAME),
            Context::createDefaultContext(),
        );

        $keys = array_keys($result->getParameters());
        static::assertCount(2, $keys);
        static::assertCount(1, $result->getWheres());
        static::assertStringContainsString(' >= CAST(:' . $keys[0] . ' AS datetime(6))', $result->getWheres()[0]);
        static::assertStringContainsString(' < CAST(:' . $keys[1] . ' AS datetime(6))', $result->getWheres()[0]);
        static::assertSame(['1990-01-01', '1990-01-02'], array_values($result->getParameters()));
    }

    /**
     * @param list<class-string<EntityDefinition>> $definitions
     */
    private function getRegistry(array $definitions = [UnitDefinition::class, UnitTranslationDefinition::class]): DefinitionInstanceRegistry
    {
        $connection = static::createStub(Connection::class);
        $connection->method('quote')->willReturnCallback(static fn (string $value): string => '\'' . $value . '\'');

        $customFieldService = new CustomFieldService(new FakeConnection([['my_date', 'datetime'], ['my_text', 'text']]));

        return new StaticDefinitionInstanceRegistry(
            $definitions,
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
            accessorBuilders: [
                JsonFieldAccessorBuilder::class => new JsonFieldAccessorBuilder($connection),
                CustomFieldsAccessorBuilder::class => new CustomFieldsAccessorBuilder($customFieldService, $connection),
            ],
        );
    }
}
