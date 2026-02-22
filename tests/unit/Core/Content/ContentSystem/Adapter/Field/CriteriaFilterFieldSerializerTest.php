<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Adapter\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Adapter\Field\CriteriaFilterField;
use Shopware\Core\Content\ContentSystem\Adapter\Field\CriteriaFilterFieldSerializer;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidFilterQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Util\Json;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(CriteriaFilterFieldSerializer::class)]
class CriteriaFilterFieldSerializerTest extends TestCase
{
    private CriteriaFilterFieldSerializer $serializer;

    private CriteriaFilterFieldSerializer $serializerWithPassthroughValidator;

    private EntityExistence $existence;

    private WriteParameterBag $parameters;

    protected function setUp(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);

        $this->serializer = new CriteriaFilterFieldSerializer($validator, $definitionRegistry);

        // Passthrough validator never raises violations — used when value is a Filter object
        // (the Type('array') constraint would otherwise reject it before the serializer converts it)
        $passthroughValidator = static::createStub(ValidatorInterface::class);
        $passthroughValidator->method('validate')->willReturn(new ConstraintViolationList());

        $this->serializerWithPassthroughValidator = new CriteriaFilterFieldSerializer(
            $passthroughValidator,
            $definitionRegistry
        );

        $this->existence = new EntityExistence('content_layout', ['id' => 'test'], true, false, false, []);
        $this->parameters = static::createStub(WriteParameterBag::class);
    }

    #[TestDox('encodes Filter object to JSON string')]
    public function testEncodeWithFilterObject(): void
    {
        $field = $this->createCriteriaFilterField();
        $filter = new EqualsFilter('active', true);
        $kvPair = new KeyValuePair('criteria_filter', $filter, false);

        // Use passthrough validator: the Type('array') constraint would reject the Filter
        // object before serialization converts it to array — bypassing is the intended encode path
        $result = iterator_to_array(
            $this->serializerWithPassthroughValidator->encode($field, $this->existence, $kvPair, $this->parameters)
        );

        static::assertArrayHasKey('criteria_filter', $result);
        $decoded = json_decode($result['criteria_filter'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('equals', $decoded['type']);
        static::assertSame('active', $decoded['field']);
        static::assertTrue($decoded['value']);
    }

    #[TestDox('encodes array value as JSON passthrough')]
    public function testEncodeWithArrayPassthrough(): void
    {
        $field = $this->createCriteriaFilterField();
        $arrayValue = ['type' => 'equals', 'field' => 'active', 'value' => true];
        $kvPair = new KeyValuePair('criteria_filter', $arrayValue, false);

        $result = iterator_to_array($this->serializer->encode($field, $this->existence, $kvPair, $this->parameters));

        static::assertArrayHasKey('criteria_filter', $result);
        static::assertSame(Json::encode($arrayValue), $result['criteria_filter']);
    }

    #[TestDox('encodes array value when field is marked as required')]
    public function testEncodeWithRequiredField(): void
    {
        $field = new CriteriaFilterField('criteria_filter', 'criteriaFilter');
        $field->addFlags(new Required());
        $field->compile(static::createStub(DefinitionInstanceRegistry::class));

        $arrayValue = ['type' => 'equals', 'field' => 'active', 'value' => true];
        $kvPair = new KeyValuePair('criteria_filter', $arrayValue, false);

        $result = iterator_to_array($this->serializer->encode($field, $this->existence, $kvPair, $this->parameters));

        static::assertArrayHasKey('criteria_filter', $result);
        static::assertIsString($result['criteria_filter']);
    }

    #[TestDox('encodes null value as null')]
    public function testEncodeWithNull(): void
    {
        $field = $this->createCriteriaFilterField();
        $kvPair = new KeyValuePair('criteria_filter', null, false);

        $result = iterator_to_array($this->serializer->encode($field, $this->existence, $kvPair, $this->parameters));

        static::assertArrayHasKey('criteria_filter', $result);
        static::assertNull($result['criteria_filter']);
    }

    #[TestDox('throws exception when encode receives wrong field type')]
    public function testEncodeThrowsOnWrongFieldType(): void
    {
        $invalidField = new JsonField('criteria_filter', 'criteriaFilter');
        $invalidField->compile(static::createStub(DefinitionInstanceRegistry::class));
        $kvPair = new KeyValuePair('criteria_filter', null, false);

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldType(CriteriaFilterField::class, JsonField::class)
        );

        iterator_to_array($this->serializer->encode($invalidField, $this->existence, $kvPair, $this->parameters));
    }

    #[TestDox('throws exception when encode receives invalid value type')]
    public function testEncodeThrowsOnInvalidValueType(): void
    {
        $field = $this->createCriteriaFilterField();
        $kvPair = new KeyValuePair('criteria_filter', 42, false);

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('criteriaFilter', 'Filter or array', 'integer')
        );

        iterator_to_array($this->serializerWithPassthroughValidator->encode($field, $this->existence, $kvPair, $this->parameters));
    }

    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('serializesCriteriaFilterProvider')]
    #[TestDox('serializes filter to array representation')]
    public function testSerializesCriteriaFilterToArray(Filter $filter, array $expected): void
    {
        $result = $this->serializer->serializeCriteriaFilter($filter);

        static::assertSame($expected, $result);
    }

    /**
     * @param array<string, mixed> $data
     * @param class-string<Filter> $expectedClass
     */
    #[DataProvider('deserializesCriteriaFilterProvider')]
    #[TestDox('deserializes filter array to correct Filter object')]
    public function testDeserializesCriteriaFilterFromArray(array $data, string $expectedClass): void
    {
        $definition = $this->createProductDefinition();

        $result = $this->serializer->deserializeCriteriaFilter($data, $definition);

        static::assertInstanceOf($expectedClass, $result);
    }

    #[TestDox('throws unsupported operation exception when decode is called')]
    public function testDecodeAlwaysThrows(): void
    {
        $field = $this->createCriteriaFilterField();

        $this->expectExceptionObject(
            ContentSystemException::criteriaFilterFieldDecodeNotSupported()
        );

        $this->serializer->decode($field, ['type' => 'equals', 'field' => 'active', 'value' => true]);
    }

    #[TestDox('throws when filter type is unsupported')]
    public function testDeserializeCriteriaFilterThrowsOnUnsupportedFilterType(): void
    {
        $definition = $this->createProductDefinition();
        $data = ['type' => 'invalid-type'];

        // QueryStringParser::fromArray() throws InvalidFilterQueryException directly
        // for unsupported types (not via SearchRequestException collection)
        $this->expectException(InvalidFilterQueryException::class);
        $this->expectExceptionMessage('Unsupported filter type: invalid-type');

        $this->serializer->deserializeCriteriaFilter($data, $definition);
    }

    #[TestDox('throws on invalid nested filter queries')]
    public function testDeserializeCriteriaFilterThrowsOnInvalidNestedQueries(): void
    {
        $definition = $this->createProductDefinition();
        // A multi filter with an invalid nested query causes SearchRequestException via tryToThrow()
        $data = [
            'type' => 'multi',
            'operator' => 'AND',
            'queries' => [
                ['type' => 'invalid-nested-type'],
            ],
        ];

        $this->expectException(SearchRequestException::class);
        $this->expectExceptionMessage('Mapping failed, got 0 failure(s).');

        $this->serializer->deserializeCriteriaFilter($data, $definition);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, class-string<Filter>}>
     */
    public static function deserializesCriteriaFilterProvider(): iterable
    {
        yield 'equals filter returns EqualsFilter' => [
            ['type' => 'equals', 'field' => 'active', 'value' => true],
            EqualsFilter::class,
        ];

        yield 'multi filter returns MultiFilter' => [
            ['type' => 'multi', 'operator' => 'AND', 'queries' => [
                ['type' => 'equals', 'field' => 'active', 'value' => true],
            ]],
            MultiFilter::class,
        ];
    }

    /**
     * @return iterable<string, array{Filter, array<string, mixed>}>
     */
    public static function serializesCriteriaFilterProvider(): iterable
    {
        yield 'equals filter maps to type/field/value keys' => [
            new EqualsFilter('active', true),
            ['type' => 'equals', 'field' => 'active', 'value' => true],
        ];

        yield 'multi filter maps nested queries with AND operator' => [
            new MultiFilter(
                MultiFilter::CONNECTION_AND,
                [
                    new EqualsFilter('active', true),
                    new EqualsFilter('stock', 0),
                ]
            ),
            [
                'type' => 'multi',
                'queries' => [
                    ['type' => 'equals', 'field' => 'active', 'value' => true],
                    ['type' => 'equals', 'field' => 'stock', 'value' => 0],
                ],
                'operator' => 'AND',
            ],
        ];
    }

    private function createCriteriaFilterField(): CriteriaFilterField
    {
        $field = new CriteriaFilterField('criteria_filter', 'criteriaFilter');
        $field->compile(static::createStub(DefinitionInstanceRegistry::class));

        return $field;
    }

    private function createProductDefinition(): EntityDefinition
    {
        return new class extends EntityDefinition {
            public function getEntityName(): string
            {
                return 'product';
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection([]);
            }
        };
    }
}
