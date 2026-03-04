<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Adapter\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Adapter\Field\CriteriaFilterFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Adapter\Field\CriteriaFilterListField;
use Shopware\Core\Framework\ContentSystem\Adapter\Field\CriteriaFilterListFieldSerializer;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(CriteriaFilterListFieldSerializer::class)]
class CriteriaFilterListFieldSerializerTest extends TestCase
{
    private CriteriaFilterListFieldSerializer $serializer;

    private CriteriaFilterListFieldSerializer $serializerWithPassthroughValidator;

    private EntityExistence $existence;

    private WriteParameterBag $parameters;

    protected function setUp(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);

        $filterSerializer = new CriteriaFilterFieldSerializer($validator, $definitionRegistry);
        $this->serializer = new CriteriaFilterListFieldSerializer($validator, $definitionRegistry, $filterSerializer);

        // Passthrough validator never raises violations — used when value contains Filter objects
        // (the Type('array') constraint on each item would otherwise reject them before conversion)
        $passthroughValidator = static::createStub(ValidatorInterface::class);
        $passthroughValidator->method('validate')->willReturn(new ConstraintViolationList());

        $filterSerializerPassthrough = new CriteriaFilterFieldSerializer($passthroughValidator, $definitionRegistry);
        $this->serializerWithPassthroughValidator = new CriteriaFilterListFieldSerializer(
            $passthroughValidator,
            $definitionRegistry,
            $filterSerializerPassthrough
        );

        $this->existence = new EntityExistence('content_layout', ['id' => 'test'], true, false, false, []);
        $this->parameters = static::createStub(WriteParameterBag::class);
    }

    #[TestDox('encodes list of Filter objects to JSON string')]
    public function testEncodeWithFilterObjectList(): void
    {
        $field = $this->createCriteriaFilterListField();
        $filters = [
            new EqualsFilter('active', true),
            new EqualsFilter('stock', 0),
        ];
        $kvPair = new KeyValuePair('criteria_filters', $filters, false);

        $result = iterator_to_array(
            $this->serializerWithPassthroughValidator->encode($field, $this->existence, $kvPair, $this->parameters)
        );

        static::assertArrayHasKey('criteria_filters', $result);
        $decoded = json_decode($result['criteria_filters'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decoded);
        static::assertCount(2, $decoded);
        static::assertSame('equals', $decoded[0]['type']);
        static::assertSame('active', $decoded[0]['field']);
        static::assertTrue($decoded[0]['value']);
        static::assertSame('equals', $decoded[1]['type']);
        static::assertSame('stock', $decoded[1]['field']);
        static::assertSame(0, $decoded[1]['value']);
    }

    #[TestDox('encodes list of raw arrays as JSON passthrough')]
    public function testEncodeWithRawArrayList(): void
    {
        $field = $this->createCriteriaFilterListField();
        $filters = [
            ['type' => 'equals', 'field' => 'active', 'value' => true],
            ['type' => 'equals', 'field' => 'stock', 'value' => 0],
        ];
        $kvPair = new KeyValuePair('criteria_filters', $filters, false);

        $result = iterator_to_array($this->serializer->encode($field, $this->existence, $kvPair, $this->parameters));

        static::assertArrayHasKey('criteria_filters', $result);
        $decoded = json_decode($result['criteria_filters'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame($filters, $decoded);
    }

    #[TestDox('encodes mixed list of Filter objects and raw arrays to JSON string')]
    public function testEncodeWithMixedList(): void
    {
        $field = $this->createCriteriaFilterListField();
        $filters = [
            new EqualsFilter('active', true),
            ['type' => 'equals', 'field' => 'stock', 'value' => 0],
        ];
        $kvPair = new KeyValuePair('criteria_filters', $filters, false);

        $result = iterator_to_array(
            $this->serializerWithPassthroughValidator->encode($field, $this->existence, $kvPair, $this->parameters)
        );

        static::assertArrayHasKey('criteria_filters', $result);
        $decoded = json_decode($result['criteria_filters'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decoded);
        static::assertCount(2, $decoded);
        static::assertSame('equals', $decoded[0]['type']);
        static::assertSame('active', $decoded[0]['field']);
        static::assertSame(['type' => 'equals', 'field' => 'stock', 'value' => 0], $decoded[1]);
    }

    #[TestDox('encodes list value when field is marked as required')]
    public function testEncodeWithRequiredField(): void
    {
        $field = new CriteriaFilterListField('criteria_filters', 'criteriaFilters');
        $field->addFlags(new Required());
        $field->compile(static::createStub(DefinitionInstanceRegistry::class));

        $arrayValue = [['type' => 'equals', 'field' => 'active', 'value' => true]];
        $kvPair = new KeyValuePair('criteria_filters', $arrayValue, false);

        $result = iterator_to_array($this->serializer->encode($field, $this->existence, $kvPair, $this->parameters));

        static::assertArrayHasKey('criteria_filters', $result);
        static::assertIsString($result['criteria_filters']);
    }

    #[TestDox('encodes null value as null')]
    public function testEncodeWithNull(): void
    {
        $field = $this->createCriteriaFilterListField();
        $kvPair = new KeyValuePair('criteria_filters', null, false);

        $result = iterator_to_array($this->serializer->encode($field, $this->existence, $kvPair, $this->parameters));

        static::assertArrayHasKey('criteria_filters', $result);
        static::assertNull($result['criteria_filters']);
    }

    #[TestDox('encodes empty list as JSON empty array')]
    public function testEncodeWithEmptyList(): void
    {
        $field = $this->createCriteriaFilterListField();
        $kvPair = new KeyValuePair('criteria_filters', [], false);

        $result = iterator_to_array($this->serializer->encode($field, $this->existence, $kvPair, $this->parameters));

        static::assertArrayHasKey('criteria_filters', $result);
        static::assertSame('[]', $result['criteria_filters']);
    }

    #[TestDox('throws exception when encode receives wrong field type')]
    public function testEncodeThrowsOnWrongFieldType(): void
    {
        $invalidField = new JsonField('criteria_filters', 'criteriaFilters');
        $invalidField->compile(static::createStub(DefinitionInstanceRegistry::class));
        $kvPair = new KeyValuePair('criteria_filters', null, false);

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldType(CriteriaFilterListField::class, JsonField::class)
        );

        iterator_to_array($this->serializer->encode($invalidField, $this->existence, $kvPair, $this->parameters));
    }

    #[TestDox('throws exception when a list item is not a Filter or array')]
    public function testEncodeThrowsOnInvalidItemType(): void
    {
        $field = $this->createCriteriaFilterListField();
        $kvPair = new KeyValuePair('criteria_filters', ['not-an-array-or-filter'], false);

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('criteriaFilters', 'Filter or array', 'string')
        );

        iterator_to_array(
            $this->serializerWithPassthroughValidator->encode($field, $this->existence, $kvPair, $this->parameters)
        );
    }

    #[TestDox('throws unsupported operation exception when decode is called')]
    public function testDecodeAlwaysThrows(): void
    {
        $field = $this->createCriteriaFilterListField();

        $this->expectExceptionObject(
            ContentSystemException::criteriaFilterFieldDecodeNotSupported()
        );

        $this->serializer->decode($field, '[{"type":"equals","field":"active","value":true}]');
    }

    private function createCriteriaFilterListField(): CriteriaFilterListField
    {
        $field = new CriteriaFilterListField('criteria_filters', 'criteriaFilters');
        $field->compile(static::createStub(DefinitionInstanceRegistry::class));

        return $field;
    }
}
