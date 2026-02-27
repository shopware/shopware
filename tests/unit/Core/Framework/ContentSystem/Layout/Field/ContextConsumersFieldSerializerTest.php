<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Field;

use PHPUnit\Framework\Attributes\CoversClass;
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
        static::assertSame('my-alias', $decoded['product']['consumer_alias']);
        static::assertSame('myProp', $decoded['product']['property_alias']);
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
                'consumer_alias' => 'my-alias',
                'property_alias' => 'myProp',
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

    #[TestDox('throws exception when decode receives non-string non-array non-null value')]
    public function testDecodeThrowsOnInvalidValueType(): void
    {
        $field = $this->createContextConsumersField();

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('accepts_context', 'array', 'integer')
        );

        $this->serializer->decode($field, 42);
    }

    #[TestDox('throws exception when consumer_alias is set without redistribute')]
    public function testDecodeThrowsOnConsumerAliasWithoutRedistribute(): void
    {
        $field = $this->createContextConsumersField();
        $json = json_encode([
            'product' => [
                'type' => 'single',
                'required' => false,
                'redistribute' => false,
                'consumer_alias' => 'my-alias',
            ],
        ], \JSON_THROW_ON_ERROR);

        $this->expectExceptionObject(
            ContentSystemException::consumerAliasWithoutRedistribute('product')
        );

        $this->serializer->decode($field, $json);
    }

    #[TestDox('throws exception when property_alias contains dot notation')]
    public function testDecodeThrowsOnPropertyAliasWithDotNotation(): void
    {
        $field = $this->createContextConsumersField();
        $json = json_encode([
            'product' => [
                'type' => 'single',
                'required' => false,
                'property_alias' => 'my.prop',
            ],
        ], \JSON_THROW_ON_ERROR);

        $this->expectExceptionObject(
            ContentSystemException::propertyAliasWithDotNotation('product', 'my.prop')
        );

        $this->serializer->decode($field, $json);
    }

    #[TestDox('serializes ContextConsumer with type and required only when defaults apply')]
    public function testSerializeContextConsumerWithMinimalFieldsReturnsExpectedArray(): void
    {
        $consumer = new ContextConsumer(
            type: ContextType::Single,
            required: true
        );

        $result = $this->serializer->serializeContextConsumer($consumer);

        static::assertSame('single', $result['type']);
        static::assertTrue($result['required']);
        static::assertArrayNotHasKey('redistribute', $result);
        static::assertArrayNotHasKey('consumer_alias', $result);
        static::assertArrayNotHasKey('property_alias', $result);
    }

    #[TestDox('serializes ContextConsumer with redistribute true includes redistribute field')]
    public function testSerializeContextConsumerWithRedistributeIncludesField(): void
    {
        $consumer = new ContextConsumer(
            type: ContextType::Collection,
            required: false,
            redistribute: true
        );

        $result = $this->serializer->serializeContextConsumer($consumer);

        static::assertSame('collection', $result['type']);
        static::assertFalse($result['required']);
        static::assertArrayHasKey('redistribute', $result);
        static::assertTrue($result['redistribute']);
        static::assertArrayNotHasKey('consumer_alias', $result);
        static::assertArrayNotHasKey('property_alias', $result);
    }

    #[TestDox('serializes ContextConsumer with all fields set')]
    public function testSerializeContextConsumerWithAllFieldsReturnsFullArray(): void
    {
        $consumer = new ContextConsumer(
            type: ContextType::Single,
            required: true,
            redistribute: true,
            consumerAlias: 'my-alias',
            propertyAlias: 'myProp'
        );

        $result = $this->serializer->serializeContextConsumer($consumer);

        static::assertSame('single', $result['type']);
        static::assertTrue($result['required']);
        static::assertArrayHasKey('redistribute', $result);
        static::assertTrue($result['redistribute']);
        static::assertArrayHasKey('consumer_alias', $result);
        static::assertSame('my-alias', $result['consumer_alias']);
        static::assertArrayHasKey('property_alias', $result);
        static::assertSame('myProp', $result['property_alias']);
    }

    #[TestDox('serializes ContextConsumer with consumer_alias null omits consumer_alias field')]
    public function testSerializeContextConsumerWithNullConsumerAliasOmitsField(): void
    {
        $consumer = new ContextConsumer(
            type: ContextType::Single,
            required: false,
            redistribute: true,
            consumerAlias: null,
            propertyAlias: 'myProp'
        );

        $result = $this->serializer->serializeContextConsumer($consumer);

        static::assertArrayNotHasKey('consumer_alias', $result);
        static::assertArrayHasKey('property_alias', $result);
        static::assertSame('myProp', $result['property_alias']);
    }

    #[TestDox('serializes ContextConsumer with property_alias null omits property_alias field')]
    public function testSerializeContextConsumerWithNullPropertyAliasOmitsField(): void
    {
        $consumer = new ContextConsumer(
            type: ContextType::Single,
            required: false,
            redistribute: true,
            consumerAlias: 'my-alias',
            propertyAlias: null
        );

        $result = $this->serializer->serializeContextConsumer($consumer);

        static::assertArrayNotHasKey('property_alias', $result);
        static::assertArrayHasKey('consumer_alias', $result);
        static::assertSame('my-alias', $result['consumer_alias']);
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
        static::assertArrayHasKey('consumer_alias', $fields);
        static::assertArrayHasKey('property_alias', $fields);
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

        // 'redistribute', 'consumer_alias', 'property_alias' are Optional
        static::assertInstanceOf(Optional::class, $fields['redistribute']);
        static::assertInstanceOf(Optional::class, $fields['consumer_alias']);
        static::assertInstanceOf(Optional::class, $fields['property_alias']);
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
