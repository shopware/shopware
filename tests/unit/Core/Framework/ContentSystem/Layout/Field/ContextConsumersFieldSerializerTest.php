<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ContextConsumersField;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ContextConsumersFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ContextConsumersFieldSerializer::class)]
class ContextConsumersFieldSerializerTest extends TestCase
{
    private ContextConsumersFieldSerializer $serializer;

    private ContextConsumersFieldSerializer $serializerWithPassthroughValidator;

    private EntityExistence $existence;

    private WriteParameterBag $parameters;

    protected function setUp(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);

        $this->serializer = new ContextConsumersFieldSerializer($validator, $definitionRegistry);

        // Passthrough validator never raises violations — used when encoding ContextConsumer objects
        // (the Type('array') constraint would otherwise reject them before serializer conversion)
        $passthroughValidator = static::createStub(ValidatorInterface::class);
        $passthroughValidator->method('validate')->willReturn(new ConstraintViolationList());

        $this->serializerWithPassthroughValidator = new ContextConsumersFieldSerializer(
            $passthroughValidator,
            $definitionRegistry
        );

        $this->existence = new EntityExistence('content_layout', ['id' => 'test'], true, false, false, []);
        $this->parameters = static::createStub(WriteParameterBag::class);
    }

    #[TestDox('encodes ContextConsumer array to JSON string')]
    public function testEncodeWithContextConsumerArrayYieldsJson(): void
    {
        $field = $this->createContextConsumersField();
        $consumer = new ContextConsumer(
            type: ContextType::Single,
            required: true,
            redistribute: true,
            consumerAlias: 'my-alias',
            propertyAlias: 'myProp'
        );

        $kvPair = new KeyValuePair('accepts_context', ['product' => $consumer], false);

        $result = iterator_to_array(
            $this->serializerWithPassthroughValidator->encode($field, $this->existence, $kvPair, $this->parameters)
        );

        static::assertArrayHasKey('accepts_context', $result);
        $decoded = json_decode($result['accepts_context'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decoded);
        static::assertArrayHasKey('product', $decoded);
        static::assertSame('single', $decoded['product']['type']);
        static::assertTrue($decoded['product']['required']);
        static::assertTrue($decoded['product']['redistribute']);
        static::assertSame('my-alias', $decoded['product']['consumerAlias']);
        static::assertSame('myProp', $decoded['product']['propertyAlias']);
    }

    #[TestDox('encodes array of plain arrays as JSON passthrough')]
    public function testEncodeWithPlainArrayPassthroughYieldsJson(): void
    {
        $field = $this->createContextConsumersField();
        $arrayValue = [
            'product' => ['type' => 'single', 'required' => true],
        ];
        $kvPair = new KeyValuePair('accepts_context', $arrayValue, false);

        $result = iterator_to_array(
            $this->serializer->encode($field, $this->existence, $kvPair, $this->parameters)
        );

        static::assertArrayHasKey('accepts_context', $result);
        $decoded = json_decode($result['accepts_context'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame($arrayValue, $decoded);
    }

    #[TestDox('encodes null value as null')]
    public function testEncodeWithNullYieldsNull(): void
    {
        $field = $this->createContextConsumersField();
        $kvPair = new KeyValuePair('accepts_context', null, false);

        $result = iterator_to_array(
            $this->serializer->encode($field, $this->existence, $kvPair, $this->parameters)
        );

        static::assertArrayHasKey('accepts_context', $result);
        static::assertNull($result['accepts_context']);
    }

    #[TestDox('throws exception when encode receives wrong field type')]
    public function testEncodeThrowsOnNonStorageAwareField(): void
    {
        $invalidField = new TranslatedField('acceptsContext');
        $invalidField->compile(static::createStub(DefinitionInstanceRegistry::class));

        $kvPair = new KeyValuePair('accepts_context', null, false);

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldType(StorageAware::class, TranslatedField::class)
        );

        iterator_to_array(
            $this->serializer->encode($invalidField, $this->existence, $kvPair, $this->parameters)
        );
    }

    #[TestDox('decodes JSON string to ContextConsumer array')]
    public function testDecodeWithJsonStringReturnsContextConsumers(): void
    {
        $field = $this->createContextConsumersField();
        $json = json_encode([
            'product' => ['type' => 'single', 'required' => true],
            'items' => ['type' => 'collection', 'required' => false],
        ], \JSON_THROW_ON_ERROR);

        $result = $this->serializer->decode($field, $json);

        static::assertIsArray($result);
        static::assertCount(2, $result);
        static::assertArrayHasKey('product', $result);
        static::assertArrayHasKey('items', $result);
        static::assertInstanceOf(ContextConsumer::class, $result['product']);
        static::assertSame(ContextType::Single, $result['product']->type);
        static::assertTrue($result['product']->required);
        static::assertInstanceOf(ContextConsumer::class, $result['items']);
        static::assertSame(ContextType::Collection, $result['items']->type);
        static::assertFalse($result['items']->required);
    }

    #[TestDox('decodes JSON string with all optional fields to ContextConsumer')]
    public function testDecodeWithAllFieldsReturnsFullContextConsumer(): void
    {
        $field = $this->createContextConsumersField();
        $json = json_encode([
            'product' => [
                'type' => 'single',
                'required' => true,
                'redistribute' => true,
                'consumerAlias' => 'my-alias',
                'propertyAlias' => 'myProp',
            ],
        ], \JSON_THROW_ON_ERROR);

        $result = $this->serializer->decode($field, $json);

        static::assertIsArray($result);
        static::assertInstanceOf(ContextConsumer::class, $result['product']);
        static::assertTrue($result['product']->redistribute);
        static::assertSame('my-alias', $result['product']->consumerAlias);
        static::assertSame('myProp', $result['product']->propertyAlias);
    }

    #[TestDox('skips non-array entries during decode')]
    public function testDecodeSkipsNonArrayEntries(): void
    {
        $field = $this->createContextConsumersField();
        $json = json_encode([
            'valid' => ['type' => 'single', 'required' => true],
            'invalid' => 'not-an-array',
        ], \JSON_THROW_ON_ERROR);

        $result = $this->serializer->decode($field, $json);

        static::assertIsArray($result);
        static::assertCount(1, $result);
        static::assertArrayHasKey('valid', $result);
        static::assertArrayNotHasKey('invalid', $result);
    }

    #[TestDox('decodes null to null')]
    public function testDecodeWithNullReturnsNull(): void
    {
        $field = $this->createContextConsumersField();

        $result = $this->serializer->decode($field, null);

        static::assertNull($result);
    }

    #[TestDox('throws exception when decode receives wrong field type')]
    public function testDecodeThrowsOnNonContextConsumersField(): void
    {
        $invalidField = new JsonField('accepts_context', 'acceptsContext');
        $invalidField->compile(static::createStub(DefinitionInstanceRegistry::class));

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldType(ContextConsumersField::class, JsonField::class)
        );

        $this->serializer->decode($invalidField, ['some' => 'data']);
    }

    #[TestDox('throws exception when decode receives invalid value type')]
    public function testDecodeThrowsOnInvalidValueType(): void
    {
        $field = $this->createContextConsumersField();

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('acceptsContext', 'array', 'integer')
        );

        $this->serializer->decode($field, 42);
    }

    #[TestDox('throws exception when consumerAlias is set without redistribute')]
    public function testDecodeThrowsOnConsumerAliasWithoutRedistribute(): void
    {
        $field = $this->createContextConsumersField();
        $json = json_encode([
            'product' => [
                'type' => 'single',
                'required' => false,
                'redistribute' => false,
                'consumerAlias' => 'my-alias',
            ],
        ], \JSON_THROW_ON_ERROR);

        $this->expectExceptionObject(
            ContentSystemException::consumerAliasWithoutRedistribute('product')
        );

        $this->serializer->decode($field, $json);
    }

    #[TestDox('throws exception when propertyAlias contains dot notation')]
    public function testDecodeThrowsOnPropertyAliasWithDotNotation(): void
    {
        $field = $this->createContextConsumersField();
        $json = json_encode([
            'product' => [
                'type' => 'single',
                'required' => false,
                'propertyAlias' => 'my.prop',
            ],
        ], \JSON_THROW_ON_ERROR);

        $this->expectExceptionObject(
            ContentSystemException::propertyAliasWithDotNotation('product', 'my.prop')
        );

        $this->serializer->decode($field, $json);
    }

    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('serializeContextConsumerProvider')]
    #[TestDox('serializes ContextConsumer: $_dataName')]
    public function testSerializeContextConsumer(ContextConsumer $consumer, array $expected): void
    {
        static::assertSame($expected, $this->serializer->serializeContextConsumer($consumer));
    }

    /**
     * @return iterable<string, array{ContextConsumer, array<string, mixed>}>
     */
    public static function serializeContextConsumerProvider(): iterable
    {
        yield 'minimal fields omit the optional keys' => [
            new ContextConsumer(type: ContextType::Single, required: true),
            ['type' => 'single', 'required' => true],
        ];

        yield 'redistribute true includes the redistribute field' => [
            new ContextConsumer(type: ContextType::Collection, required: false, redistribute: true),
            ['type' => 'collection', 'required' => false, 'redistribute' => true],
        ];

        yield 'all fields set' => [
            new ContextConsumer(
                type: ContextType::Single,
                required: true,
                redistribute: true,
                consumerAlias: 'my-alias',
                propertyAlias: 'myProp'
            ),
            ['type' => 'single', 'required' => true, 'redistribute' => true, 'consumerAlias' => 'my-alias', 'propertyAlias' => 'myProp'],
        ];

        yield 'null consumerAlias omits the consumerAlias field' => [
            new ContextConsumer(
                type: ContextType::Single,
                required: false,
                redistribute: true,
                consumerAlias: null,
                propertyAlias: 'myProp'
            ),
            ['type' => 'single', 'required' => false, 'redistribute' => true, 'propertyAlias' => 'myProp'],
        ];

        yield 'null propertyAlias omits the propertyAlias field' => [
            new ContextConsumer(
                type: ContextType::Single,
                required: false,
                redistribute: true,
                consumerAlias: 'my-alias',
                propertyAlias: null
            ),
            ['type' => 'single', 'required' => false, 'redistribute' => true, 'consumerAlias' => 'my-alias'],
        ];
    }

    #[TestDox('returns Type array and All Collection constraints with expected field structure')]
    public function testBuildConstraintsWithoutRequiredFlagReturnsExpectedStructure(): void
    {
        $field = $this->createContextConsumersField();

        $constraints = $this->serializer->buildConstraints($field);

        static::assertCount(2, $constraints);
        static::assertInstanceOf(Type::class, $constraints[0]);
        static::assertInstanceOf(All::class, $constraints[1]);

        $allConstraint = $constraints[1];
        static::assertIsArray($allConstraint->constraints);
        $innerConstraints = $allConstraint->constraints;
        static::assertCount(1, $innerConstraints);

        $collection = $innerConstraints[0];
        static::assertInstanceOf(Collection::class, $collection);

        $fields = $collection->fields;
        static::assertArrayHasKey('type', $fields);
        static::assertArrayHasKey('required', $fields);
        static::assertArrayHasKey('redistribute', $fields);
        static::assertArrayHasKey('consumerAlias', $fields);
        static::assertArrayHasKey('propertyAlias', $fields);
        static::assertFalse($collection->allowExtraFields);
        static::assertFalse($collection->allowMissingFields);

        // 'type' is Required (Symfony wraps array constraints into Required)
        static::assertInstanceOf(\Symfony\Component\Validator\Constraints\Required::class, $fields['type']);
        $typeConstraints = $fields['type']->constraints;
        static::assertIsArray($typeConstraints);
        static::assertCount(2, $typeConstraints);
        static::assertInstanceOf(NotBlank::class, $typeConstraints[0]);
        static::assertInstanceOf(Choice::class, $typeConstraints[1]);

        // 'required' is Required (wraps Type constraint)
        static::assertInstanceOf(\Symfony\Component\Validator\Constraints\Required::class, $fields['required']);

        // 'redistribute', 'consumerAlias', 'propertyAlias' are Optional
        static::assertInstanceOf(Optional::class, $fields['redistribute']);
        static::assertInstanceOf(Optional::class, $fields['consumerAlias']);
        static::assertInstanceOf(Optional::class, $fields['propertyAlias']);
    }

    #[TestDox('appends NotBlank constraint when field has Required flag')]
    public function testBuildConstraintsWithRequiredFlagAddsNotBlank(): void
    {
        $field = $this->createContextConsumersFieldWithRequired();

        $constraints = $this->serializer->buildConstraints($field);

        static::assertCount(3, $constraints);
        static::assertInstanceOf(Type::class, $constraints[0]);
        static::assertInstanceOf(All::class, $constraints[1]);
        static::assertInstanceOf(NotBlank::class, $constraints[2]);
    }

    private function createContextConsumersField(): ContextConsumersField
    {
        $field = new ContextConsumersField('accepts_context', 'acceptsContext');
        $field->compile(static::createStub(DefinitionInstanceRegistry::class));

        return $field;
    }

    private function createContextConsumersFieldWithRequired(): ContextConsumersField
    {
        $field = new ContextConsumersField('accepts_context', 'acceptsContext');
        $field->addFlags(new Required());
        $field->compile(static::createStub(DefinitionInstanceRegistry::class));

        return $field;
    }
}
