<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfigSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Validation\StyleOptionConstraintDeriver;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ContentElementField;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ContentElementFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ContextConsumersFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ContextProvidersFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Field\DataRequirementsFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ElementSlotsFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ElementStyleFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ContentElementFieldSerializer::class)]
class ContentElementFieldSerializerTest extends TestCase
{
    private ContentElementFieldSerializer $serializer;

    private ContentElementFieldSerializer $serializerWithPassthroughValidator;

    private EntityExistence $existence;

    private WriteParameterBag $parameters;

    /**
     * @var DataLoaderConfigSerializerProvider&Stub
     */
    private DataLoaderConfigSerializerProvider $configProvider;

    protected function setUp(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);

        $this->configProvider = static::createStub(DataLoaderConfigSerializerProvider::class);

        [$this->serializer, $this->serializerWithPassthroughValidator] = $this->buildSerializers(
            $validator,
            $definitionRegistry
        );

        $this->existence = new EntityExistence('content_layout', ['id' => 'test'], true, false, false, []);
        $this->parameters = static::createStub(WriteParameterBag::class);
    }

    #[TestDox('encodes ContentElement object to JSON string')]
    public function testEncodeWithContentElementYieldsJson(): void
    {
        $field = $this->createContentElementField();
        $element = ContentElementBuilder::create('text', 'elem-1')
            ->withProperty('color', 'red')
            ->build();

        $kvPair = new KeyValuePair('element', $element, false);

        $result = iterator_to_array(
            $this->serializerWithPassthroughValidator->encode($field, $this->existence, $kvPair, $this->parameters)
        );

        static::assertArrayHasKey('element', $result);
        static::assertIsString($result['element']);

        $decoded = json_decode($result['element'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decoded);
        static::assertSame('elem-1', $decoded['id']);
        static::assertSame('text', $decoded['component']);
        static::assertSame(['color' => 'red'], $decoded['properties']);
    }

    #[TestDox('encodes plain array passthrough to JSON string')]
    public function testEncodeWithPlainArrayPassthroughYieldsJson(): void
    {
        $field = $this->createContentElementField();
        $arrayValue = [
            'id' => 'elem-1',
            'component' => 'text',
            'properties' => ['color' => 'blue'],
        ];

        $kvPair = new KeyValuePair('element', $arrayValue, false);

        $result = iterator_to_array(
            $this->serializer->encode($field, $this->existence, $kvPair, $this->parameters)
        );

        static::assertArrayHasKey('element', $result);
        static::assertIsString($result['element']);

        $decoded = json_decode($result['element'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame($arrayValue, $decoded);
    }

    #[TestDox('encodes null value as null')]
    public function testEncodeWithNullYieldsNull(): void
    {
        $field = $this->createContentElementField();
        $kvPair = new KeyValuePair('element', null, false);

        $result = iterator_to_array(
            $this->serializer->encode($field, $this->existence, $kvPair, $this->parameters)
        );

        static::assertArrayHasKey('element', $result);
        static::assertNull($result['element']);
    }

    #[TestDox('throws exception when encode receives non-StorageAware field')]
    public function testEncodeThrowsOnNonStorageAwareField(): void
    {
        $invalidField = new TranslatedField('element');
        $invalidField->compile(static::createStub(DefinitionInstanceRegistry::class));

        $kvPair = new KeyValuePair('element', null, false);

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldType(
                StorageAware::class,
                TranslatedField::class
            )
        );

        iterator_to_array(
            $this->serializer->encode($invalidField, $this->existence, $kvPair, $this->parameters)
        );
    }

    #[TestDox('throws exception when encode receives non-array non-null non-ContentElement value')]
    public function testEncodeThrowsOnInvalidValueType(): void
    {
        $field = $this->createContentElementField();
        $kvPair = new KeyValuePair('element', 42, false);

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('element', 'array|ContentElement', 'integer')
        );

        iterator_to_array(
            $this->serializerWithPassthroughValidator->encode($field, $this->existence, $kvPair, $this->parameters)
        );
    }

    #[TestDox('decodes JSON string to ContentElement')]
    public function testDecodeWithJsonStringReturnsContentElement(): void
    {
        $field = $this->createContentElementField();

        $json = json_encode([
            'id' => 'elem-1',
            'component' => 'text',
            'properties' => ['color' => 'green'],
        ], \JSON_THROW_ON_ERROR);

        $result = $this->serializer->decode($field, $json);

        static::assertInstanceOf(ContentElement::class, $result);
        static::assertSame('elem-1', $result->getId());
        static::assertSame('text', $result->getComponent());
        static::assertSame('green', $result->getProperty('color'));
    }

    #[TestDox('decodes null to null')]
    public function testDecodeWithNullReturnsNull(): void
    {
        $field = $this->createContentElementField();

        $result = $this->serializer->decode($field, null);

        static::assertNull($result);
    }

    #[TestDox('throws exception when decode receives non-ContentElementField field type')]
    public function testDecodeThrowsOnNonContentElementField(): void
    {
        $invalidField = new JsonField('element', 'element');
        $invalidField->compile(static::createStub(DefinitionInstanceRegistry::class));

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldType(ContentElementField::class, JsonField::class)
        );

        $this->serializer->decode($invalidField, '{}');
    }

    #[TestDox('decodes element with minimal fields into a ContentElement with empty defaults')]
    public function testDecodeElementWithMinimalFieldsReturnsEmptyDefaults(): void
    {
        $result = $this->serializer->decodeElement([
            'id' => 'minimal-id',
            'component' => 'hero',
        ]);

        static::assertSame('minimal-id', $result->getId());
        static::assertSame('hero', $result->getComponent());
        static::assertSame([], $result->getDataRequirements());
        static::assertFalse($result->hasSlots());
        static::assertSame([], $result->getProvidesContext());
        static::assertSame([], $result->getAcceptsContext());
        static::assertTrue($result->getStyle()->isEmpty());
    }

    #[TestDox('decodes element with properties into a ContentElement with accessible property values')]
    public function testDecodeElementWithPropertiesReturnsAccessibleValues(): void
    {
        $result = $this->serializer->decodeElement([
            'id' => 'elem-props',
            'component' => 'image',
            'properties' => ['src' => '/path/to/image.png', 'alt' => 'hero image'],
        ]);

        static::assertSame('/path/to/image.png', $result->getProperty('src'));
        static::assertSame('hero image', $result->getProperty('alt'));
    }

    #[TestDox('decodes element with dataRequirements into a ContentElement with mapped DataRequirement objects')]
    public function testDecodeElementWithDataRequirementsReturnsContentElementWithRequirements(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $this->configProvider->method('decode')->willReturn($config);

        $data = [
            'id' => 'elem-reqs',
            'component' => 'product-card',
            'dataRequirements' => [
                'product' => ['key' => 'product', 'source' => 'entity', 'config' => []],
            ],
        ];

        $result = $this->serializer->decodeElement($data);

        static::assertCount(1, $result->getDataRequirements());
        static::assertArrayHasKey('product', $result->getDataRequirements());
        static::assertInstanceOf(DataRequirement::class, $result->getDataRequirements()['product']);
    }

    #[TestDox('decodes element with slots containing child elements')]
    public function testDecodeElementWithSlotsReturnsContentElementWithSlots(): void
    {
        $data = [
            'id' => 'elem-slots',
            'component' => 'grid',
            'slots' => [
                'main' => [
                    ['id' => 'child-1', 'component' => 'text'],
                    ['id' => 'child-2', 'component' => 'image'],
                ],
            ],
        ];

        $result = $this->serializer->decodeElement($data);

        static::assertTrue($result->hasSlots());
        static::assertArrayHasKey('main', $result->getSlots());
        static::assertInstanceOf(SlotContent::class, $result->getSlots()['main']);
        static::assertCount(2, $result->getSlots()['main']);
    }

    #[TestDox('decodes element with context providers into ContextProvider objects')]
    public function testDecodeElementWithContextProvidersReturnsContextProviders(): void
    {
        $data = [
            'id' => 'elem-ctx',
            'component' => 'context-aware',
            'providesContext' => [
                'myData' => [
                    'type' => 'single',
                    'distribution' => 'broadcast',
                    'consumerAlias' => null,
                ],
            ],
        ];

        $result = $this->serializer->decodeElement($data);

        static::assertCount(1, $result->getProvidesContext());
        static::assertArrayHasKey('myData', $result->getProvidesContext());
        static::assertInstanceOf(ContextProvider::class, $result->getProvidesContext()['myData']);
    }

    #[TestDox('decodes element with context consumers into ContextConsumer objects')]
    public function testDecodeElementWithContextConsumersReturnsContextConsumers(): void
    {
        $data = [
            'id' => 'elem-ctx',
            'component' => 'context-aware',
            'acceptsContext' => [
                'parentData' => [
                    'type' => 'single',
                    'required' => false,
                ],
            ],
        ];

        $result = $this->serializer->decodeElement($data);

        static::assertCount(1, $result->getAcceptsContext());
        static::assertArrayHasKey('parentData', $result->getAcceptsContext());
        static::assertInstanceOf(ContextConsumer::class, $result->getAcceptsContext()['parentData']);
    }

    #[TestDox('decodes a style key into the element ElementStyle')]
    public function testDecodeElementWithStyleReturnsElementStyle(): void
    {
        $result = $this->serializer->decodeElement([
            'id' => 'elem-style',
            'component' => 'text',
            'style' => ['col-span' => ['md' => 6]],
        ]);

        static::assertSame(['col-span' => ['md' => 6]], $result->getStyle()->toArray());
    }

    /**
     * @param array<string, string> $data
     */
    #[DataProvider('decodeElementThrowsOnMissingFieldProvider')]
    #[TestDox('throws exception when decodeElement receives data without $missingField field')]
    public function testDecodeElementThrowsWhenRequiredFieldMissing(array $data, string $missingField): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType($missingField, 'string', 'NULL')
        );

        $this->serializer->decodeElement($data);
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function decodeElementThrowsOnMissingFieldProvider(): iterable
    {
        yield 'missing id' => [['component' => 'text'], 'id'];
        yield 'missing component' => [['id' => 'elem-1'], 'component'];
    }

    #[TestDox('throws a client-defect invalid_field_value_type when decodeElement receives non-array properties')]
    public function testDecodeElementThrowsOnNonArrayProperties(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('properties', 'array', 'string')
        );

        $this->serializer->decodeElement([
            'id' => 'elem-1',
            'component' => 'text',
            'properties' => 'not-an-array',
        ]);
    }

    #[TestDox('serializes ContentElement with minimal fields to array')]
    public function testSerializeContentElementWithMinimalFieldsReturnsExpectedArray(): void
    {
        $element = ContentElementBuilder::create('hero', 'elem-minimal')
            ->build();

        $result = $this->serializer->serializeContentElement($element);

        static::assertSame('elem-minimal', $result['id']);
        static::assertSame('hero', $result['component']);
        // The storage/write form keeps the empty property map as an array; the API response boundary re-types it to {}
        static::assertSame([], $result['properties']);
        static::assertArrayNotHasKey('dataRequirements', $result);
        static::assertArrayNotHasKey('slots', $result);
        static::assertArrayNotHasKey('providesContext', $result);
        static::assertArrayNotHasKey('acceptsContext', $result);
        static::assertArrayNotHasKey('style', $result);
    }

    #[TestDox('serializes a ContentElement object with a non-empty style into the style key')]
    public function testSerializeContentElementWithStyleIncludesStyle(): void
    {
        $element = ContentElementBuilder::create('text', 'elem-style')
            ->withStyle(new ElementStyle(['col-span' => ['md' => 6]]))
            ->build();

        $result = $this->serializer->serializeContentElement($element);

        static::assertArrayHasKey('style', $result);
        static::assertSame(['col-span' => ['md' => 6]], $result['style']);
    }

    #[TestDox('serializes ContentElement with data requirements to array')]
    public function testSerializeContentElementWithDataRequirementsIncludesRequirements(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $this->configProvider->method('encode')->willReturn(['entityName' => 'product']);

        $element = ContentElementBuilder::create('product-card', 'elem-req')
            ->withDataRequirement('product', 'entity', $config)
            ->build();

        $result = $this->serializer->serializeContentElement($element);

        static::assertArrayHasKey('dataRequirements', $result);
        static::assertArrayHasKey('product', $result['dataRequirements']);
        static::assertSame('product', $result['dataRequirements']['product']['key']);
        static::assertSame('entity', $result['dataRequirements']['product']['source']);
    }

    #[TestDox('serializes ContentElement with slots to array')]
    public function testSerializeContentElementWithSlotsIncludesSlots(): void
    {
        $child = ContentElementBuilder::create('text', 'child-1')->build();
        $element = ContentElementBuilder::create('grid', 'elem-slots')
            ->withSlot('main', [$child])
            ->build();

        $result = $this->serializer->serializeContentElement($element);

        static::assertArrayHasKey('slots', $result);
        static::assertArrayHasKey('main', $result['slots']);
        static::assertCount(1, $result['slots']['main']);
        static::assertSame('child-1', $result['slots']['main'][0]['id']);
    }

    #[TestDox('serializes ContentElement with context providers to array')]
    public function testSerializeContentElementWithContextProvidersIncludesProviders(): void
    {
        $element = ContentElementBuilder::create('provider', 'elem-provider')
            ->withProvider('myData', BroadcastDistributionConfig::simple(), ContextType::Single)
            ->build();

        $result = $this->serializer->serializeContentElement($element);

        static::assertArrayHasKey('providesContext', $result);
        static::assertArrayHasKey('myData', $result['providesContext']);
        static::assertSame('single', $result['providesContext']['myData']['type']);
        static::assertSame('broadcast', $result['providesContext']['myData']['distribution']);
    }

    #[TestDox('serializes ContentElement with context consumers to array')]
    public function testSerializeContentElementWithContextConsumersIncludesConsumers(): void
    {
        $element = ContentElementBuilder::create('consumer', 'elem-consumer')
            ->withConsumer('parentData', ContextType::Single, false)
            ->build();

        $result = $this->serializer->serializeContentElement($element);

        static::assertArrayHasKey('acceptsContext', $result);
        static::assertArrayHasKey('parentData', $result['acceptsContext']);
        static::assertSame('single', $result['acceptsContext']['parentData']['type']);
        static::assertFalse($result['acceptsContext']['parentData']['required']);
    }

    #[TestDox('serializes ContentElement property preserving raw value when value is non-Struct object')]
    public function testSerializeContentElementPreservesRawObjectProperties(): void
    {
        $objectWithToArray = new ObjectWithToArray();

        $element = ContentElementBuilder::create('hero', 'elem-obj-prop')
            ->withProperty('myObj', $objectWithToArray)
            ->build();

        $result = $this->serializer->serializeContentElement($element);

        static::assertArrayHasKey('properties', $result);
        static::assertSame($objectWithToArray, $result['properties']['myObj']);
    }

    #[TestDox('restores ContentElement through serialize-then-decode roundtrip')]
    public function testSerializeContentElementRoundtrip(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $this->configProvider->method('encode')->willReturn(['entityName' => 'product']);
        $this->configProvider->method('decode')->willReturn($config);

        $child = ContentElementBuilder::create('text', 'child-rt')->build();
        $original = ContentElementBuilder::create('product-card', 'elem-rt')
            ->withProperty('title', 'My Product')
            ->withDataRequirement('product', 'entity', $config)
            ->withSlot('footer', [$child])
            ->withProvider('myProduct', BroadcastDistributionConfig::simple(), ContextType::Single)
            ->withConsumer('parentCtx', ContextType::Single, false)
            ->build();

        $serialized = $this->serializer->serializeContentElement($original);
        $restored = $this->serializer->decodeElement($this->wireRoundTrip($serialized));

        static::assertSame($original->getId(), $restored->getId());
        static::assertSame($original->getComponent(), $restored->getComponent());
        static::assertSame('My Product', $restored->getProperty('title'));
        static::assertCount(1, $restored->getDataRequirements());
        static::assertTrue($restored->hasSlots());
        static::assertArrayHasKey('footer', $restored->getSlots());
        static::assertCount(1, $restored->getProvidesContext());
        static::assertCount(1, $restored->getAcceptsContext());
    }

    #[TestDox('returns Type and Collection constraints when field has no Required flag')]
    public function testBuildConstraintsWithoutRequiredFlagReturnsExpectedStructure(): void
    {
        $field = $this->createContentElementField();

        $constraints = $this->serializer->buildConstraints($field);

        static::assertCount(2, $constraints);
        static::assertInstanceOf(Type::class, $constraints[0]);
        static::assertSame('array', $constraints[0]->type);
        static::assertInstanceOf(Collection::class, $constraints[1]);

        $collection = $constraints[1];
        static::assertArrayHasKey('id', $collection->fields);
        static::assertArrayHasKey('component', $collection->fields);
        static::assertArrayHasKey('properties', $collection->fields);
        static::assertArrayHasKey('dataRequirements', $collection->fields);
        static::assertArrayHasKey('slots', $collection->fields);
        static::assertArrayHasKey('providesContext', $collection->fields);
        static::assertArrayHasKey('acceptsContext', $collection->fields);
        static::assertArrayHasKey('style', $collection->fields);
        static::assertFalse($collection->allowExtraFields);
        static::assertFalse($collection->allowMissingFields);

        static::assertInstanceOf(Optional::class, $collection->fields['dataRequirements']);
        static::assertInstanceOf(Optional::class, $collection->fields['slots']);
        static::assertInstanceOf(Optional::class, $collection->fields['providesContext']);
        static::assertInstanceOf(Optional::class, $collection->fields['acceptsContext']);
        static::assertInstanceOf(Optional::class, $collection->fields['style']);
    }

    #[TestDox('appends NotBlank constraint when field has Required flag')]
    public function testBuildConstraintsWithRequiredFlagAddsNotBlank(): void
    {
        $field = $this->createContentElementFieldWithRequired();

        $constraints = $this->serializer->buildConstraints($field);

        static::assertCount(3, $constraints);
        static::assertInstanceOf(Type::class, $constraints[0]);
        static::assertInstanceOf(Collection::class, $constraints[1]);
        static::assertInstanceOf(NotBlank::class, $constraints[2]);
    }

    #[TestDox('accepts a written element whose style uses a registered option within its bounds')]
    public function testBuildConstraintsAcceptsKnownStyleOption(): void
    {
        $element = [
            'id' => 'elem-1',
            'component' => 'text',
            'style' => ['col-span' => ['md' => 6]],
        ];

        $violations = $this->validateElementAgainstContentElementFieldConstraints($element);

        static::assertCount(0, $violations);
    }

    #[TestDox('throws exception when buildConstraints receives wrong field type')]
    public function testBuildConstraintsThrowsOnNonContentElementField(): void
    {
        $invalidField = new JsonField('element', 'element');
        $invalidField->compile(static::createStub(DefinitionInstanceRegistry::class));

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldType(ContentElementField::class, JsonField::class)
        );

        $this->serializer->buildConstraints($invalidField);
    }

    #[TestDox('rejects a written element whose style references an unregistered option at the style path')]
    public function testBuildConstraintsRejectsUnknownStyleOption(): void
    {
        $element = [
            'id' => 'elem-1',
            'component' => 'text',
            'style' => ['made-up-option' => ['md' => 6]],
        ];

        $violations = $this->validateElementAgainstContentElementFieldConstraints($element);

        static::assertGreaterThanOrEqual(1, $violations->count());
        static::assertSame('[style][made-up-option]', $violations->get(0)->getPropertyPath());
    }

    /**
     * Validates $element against the constraints produced by a freshly-built ContentElementField,
     * using a real Symfony validator. Both style-option tests share this act phase verbatim.
     *
     * @param array<string, mixed> $element
     *
     * @return ConstraintViolationListInterface<ConstraintViolationInterface>
     */
    private function validateElementAgainstContentElementFieldConstraints(array $element): ConstraintViolationListInterface
    {
        return Validation::createValidatorBuilder()->getValidator()
            ->validate($element, $this->serializer->buildConstraints($this->createContentElementField()));
    }

    #[TestDox('preserves all camelCase wire keys and their values faithfully through a serialize-decode round-trip')]
    public function testRoundTripPreservesCamelCaseWireFormatAndValues(): void
    {
        $serializer = $this->buildSerializerWithRealConfigProvider();

        $config = new EntityLoaderConfig('product', 'product', ['manufacturer']);
        $child = ContentElementBuilder::create('text', 'slot-child-1')->build();

        $element = ContentElementBuilder::create('product-card', 'rt-elem-1')
            ->withProperty('title', 'My Product')
            ->withProperty('count', 42)
            ->withDataRequirement('product', 'entity', $config)
            ->withProvider('productCtx', BroadcastDistributionConfig::aliased('myAlias'), ContextType::Single)
            ->withConsumer('catCtx', ContextType::Single, required: true, redistribute: true, consumerAlias: 'ca', propertyAlias: 'pa')
            ->withSlot('main', [$child])
            ->withStyle(new ElementStyle(['col-span' => ['md' => 6]]))
            ->build();

        $serialized = $serializer->serializeContentElement($element);

        // camelCase top-level keys must be present
        static::assertArrayHasKey('id', $serialized);
        static::assertArrayHasKey('component', $serialized);
        static::assertArrayHasKey('properties', $serialized);
        static::assertArrayHasKey('dataRequirements', $serialized);
        static::assertArrayHasKey('providesContext', $serialized);
        static::assertArrayHasKey('acceptsContext', $serialized);
        static::assertArrayHasKey('slots', $serialized);
        static::assertArrayHasKey('style', $serialized);

        // snake_case keys must NOT appear at the top level
        static::assertArrayNotHasKey('data_requirements', $serialized);
        static::assertArrayNotHasKey('provides_context', $serialized);
        static::assertArrayNotHasKey('accepts_context', $serialized);

        // provider entry: camelCase consumerAlias present, snake absent
        static::assertArrayHasKey('productCtx', $serialized['providesContext']);
        $providerEntry = $serialized['providesContext']['productCtx'];
        static::assertArrayHasKey('consumerAlias', $providerEntry);
        static::assertSame('myAlias', $providerEntry['consumerAlias']);
        static::assertArrayNotHasKey('consumer_alias', $providerEntry);

        // consumer entry: camelCase consumerAlias and propertyAlias present, snake absent
        static::assertArrayHasKey('catCtx', $serialized['acceptsContext']);
        $consumerEntry = $serialized['acceptsContext']['catCtx'];
        static::assertArrayHasKey('consumerAlias', $consumerEntry);
        static::assertSame('ca', $consumerEntry['consumerAlias']);
        static::assertArrayHasKey('propertyAlias', $consumerEntry);
        static::assertSame('pa', $consumerEntry['propertyAlias']);
        static::assertArrayNotHasKey('consumer_alias', $consumerEntry);
        static::assertArrayNotHasKey('property_alias', $consumerEntry);

        // style survives serialization identically (read == write)
        static::assertSame(['col-span' => ['md' => 6]], $serialized['style']);

        $decoded = $serializer->decodeElement($this->wireRoundTrip($serialized));

        // id / component / properties
        static::assertSame('rt-elem-1', $decoded->getId());
        static::assertSame('product-card', $decoded->getComponent());
        static::assertSame('My Product', $decoded->getProperty('title'));
        static::assertSame(42, $decoded->getProperty('count'));

        // dataRequirements survive with config fields intact
        static::assertCount(1, $decoded->getDataRequirements());
        static::assertArrayHasKey('product', $decoded->getDataRequirements());
        $decodedConfig = $decoded->getDataRequirements()['product']->config;
        static::assertInstanceOf(EntityLoaderConfig::class, $decodedConfig);
        static::assertSame('product', $decodedConfig->entity);
        static::assertSame('product', $decodedConfig->property);
        static::assertSame(['manufacturer'], $decodedConfig->associations);

        // providesContext survives — non-empty, with correct type, distribution and consumerAlias
        $providers = $decoded->getProvidesContext();
        static::assertCount(1, $providers, 'providesContext must NOT collapse to empty');
        static::assertArrayHasKey('productCtx', $providers);
        static::assertSame(ContextType::Single, $providers['productCtx']->type);
        $decodedDistribution = $providers['productCtx']->distributionConfig;
        static::assertInstanceOf(BroadcastDistributionConfig::class, $decodedDistribution);
        static::assertSame('myAlias', $decodedDistribution->getConsumerAlias());

        // acceptsContext survives — non-empty, with all consumer fields intact
        $consumers = $decoded->getAcceptsContext();
        static::assertCount(1, $consumers, 'acceptsContext must NOT collapse to empty');
        static::assertArrayHasKey('catCtx', $consumers);
        $decodedConsumer = $consumers['catCtx'];
        static::assertSame(ContextType::Single, $decodedConsumer->type);
        static::assertTrue($decodedConsumer->required);
        static::assertTrue($decodedConsumer->redistribute);
        static::assertSame('ca', $decodedConsumer->consumerAlias);
        static::assertSame('pa', $decodedConsumer->propertyAlias);

        // slot with nested element survives
        static::assertTrue($decoded->hasSlots());
        static::assertArrayHasKey('main', $decoded->getSlots());
        static::assertCount(1, $decoded->getSlots()['main']);

        // style survives identically (decoded == original)
        static::assertSame(['col-span' => ['md' => 6]], $decoded->getStyle()->toArray());
    }

    /**
     * Mirrors the storage hop: the serialized tree is persisted as JSON and read back with
     * associative arrays, so an empty object {} comes back as []. The in-memory serialize output
     * is never fed straight to decode in production.
     *
     * @param array<string, mixed> $serialized
     *
     * @return array<string, mixed>
     */
    private function wireRoundTrip(array $serialized): array
    {
        return json_decode((string) json_encode($serialized, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);
    }

    private function createContentElementField(): ContentElementField
    {
        $field = new ContentElementField('element', 'element');
        $field->compile(static::createStub(DefinitionInstanceRegistry::class));

        return $field;
    }

    private function createContentElementFieldWithRequired(): ContentElementField
    {
        $field = new ContentElementField('element', 'element');
        $field->addFlags(new Required());
        $field->compile(static::createStub(DefinitionInstanceRegistry::class));

        return $field;
    }

    /**
     * @return array{0: ContentElementFieldSerializer, 1: ContentElementFieldSerializer}
     */
    private function buildSerializers(ValidatorInterface $validator, DefinitionInstanceRegistry $definitionRegistry): array
    {
        $dataRequirementsSerializer = new DataRequirementsFieldSerializer(
            $validator,
            $definitionRegistry,
            $this->configProvider
        );

        $contextProvidersSerializer = new ContextProvidersFieldSerializer($validator, $definitionRegistry);
        $contextConsumersSerializer = new ContextConsumersFieldSerializer($validator, $definitionRegistry);

        $realSerializer = new ContentElementFieldSerializer(
            $validator,
            $definitionRegistry,
            $dataRequirementsSerializer,
            $contextProvidersSerializer,
            $contextConsumersSerializer,
            // ElementSlotsFieldSerializer needs a ContentElementFieldSerializer — inject a placeholder
            // (built with a stubbed slots serializer) to break the circular dependency
            new ElementSlotsFieldSerializer($validator, $definitionRegistry, $this->buildPlaceholderElementSerializer($validator, $definitionRegistry)),
            $this->buildStyleSerializer($validator, $definitionRegistry)
        );

        // Build the canonical serializer with a real ElementSlotsFieldSerializer that references back
        $slotsSerializer = new ElementSlotsFieldSerializer($validator, $definitionRegistry, $realSerializer);
        $canonicalSerializer = new ContentElementFieldSerializer(
            $validator,
            $definitionRegistry,
            $dataRequirementsSerializer,
            $contextProvidersSerializer,
            $contextConsumersSerializer,
            $slotsSerializer,
            $this->buildStyleSerializer($validator, $definitionRegistry)
        );

        // Passthrough validator - never raises violations for ContentElement objects
        $passthroughValidator = static::createStub(ValidatorInterface::class);
        $passthroughValidator->method('validate')->willReturn(new ConstraintViolationList());

        $dataRequirementsSerializerPassthrough = new DataRequirementsFieldSerializer(
            $passthroughValidator,
            $definitionRegistry,
            $this->configProvider
        );
        $contextProvidersSerializerPassthrough = new ContextProvidersFieldSerializer($passthroughValidator, $definitionRegistry);
        $contextConsumersSerializerPassthrough = new ContextConsumersFieldSerializer($passthroughValidator, $definitionRegistry);

        $passthroughSerializer = new ContentElementFieldSerializer(
            $passthroughValidator,
            $definitionRegistry,
            $dataRequirementsSerializerPassthrough,
            $contextProvidersSerializerPassthrough,
            $contextConsumersSerializerPassthrough,
            new ElementSlotsFieldSerializer($passthroughValidator, $definitionRegistry, $canonicalSerializer),
            $this->buildStyleSerializer($passthroughValidator, $definitionRegistry)
        );

        return [$canonicalSerializer, $passthroughSerializer];
    }

    private function buildPlaceholderElementSerializer(
        ValidatorInterface $validator,
        DefinitionInstanceRegistry $definitionRegistry
    ): ContentElementFieldSerializer {
        $dataRequirementsSerializer = new DataRequirementsFieldSerializer($validator, $definitionRegistry, $this->configProvider);
        $contextProvidersSerializer = new ContextProvidersFieldSerializer($validator, $definitionRegistry);
        $contextConsumersSerializer = new ContextConsumersFieldSerializer($validator, $definitionRegistry);

        // ElementSlotsField won't be used in simple tests — use a stub to break circularity
        $slotsSerializer = static::createStub(ElementSlotsFieldSerializer::class);
        $slotsSerializer->method('decode')->willReturn([]);
        $slotsSerializer->method('serializeSlots')->willReturn([]);
        $slotsSerializer->method('buildConstraints')->willReturn([new Type('array')]);

        return new ContentElementFieldSerializer(
            $validator,
            $definitionRegistry,
            $dataRequirementsSerializer,
            $contextProvidersSerializer,
            $contextConsumersSerializer,
            $slotsSerializer,
            $this->buildStyleSerializer($validator, $definitionRegistry)
        );
    }

    /**
     * Builds a ContentElementFieldSerializer wired to a real DataLoaderConfigSerializerProvider
     * (with EntityLoaderConfigSerializer registered) so the config array survives the round-trip
     * through the genuine decode path.
     */
    private function buildSerializerWithRealConfigProvider(): ContentElementFieldSerializer
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);

        $realConfigProvider = new DataLoaderConfigSerializerProvider(
            new ServiceLocator([
                EntityLoaderConfigSerializer::getSource() => static fn () => new EntityLoaderConfigSerializer(),
            ])
        );

        $dataRequirementsSerializer = new DataRequirementsFieldSerializer($validator, $definitionRegistry, $realConfigProvider);
        $contextProvidersSerializer = new ContextProvidersFieldSerializer($validator, $definitionRegistry);
        $contextConsumersSerializer = new ContextConsumersFieldSerializer($validator, $definitionRegistry);
        $styleSerializer = $this->buildStyleSerializer($validator, $definitionRegistry);

        // A placeholder serializer (with stub slots) for breaking the circular dependency in slot recursion.
        // The nested slot child is a simple element without further nesting, so the placeholder is sufficient.
        $stubSlotsSerializer = static::createStub(ElementSlotsFieldSerializer::class);
        $stubSlotsSerializer->method('decode')->willReturn([]);
        $stubSlotsSerializer->method('serializeSlots')->willReturn([]);
        $stubSlotsSerializer->method('buildConstraints')->willReturn([new Type('array')]);

        $baseSerializer = new ContentElementFieldSerializer(
            $validator,
            $definitionRegistry,
            $dataRequirementsSerializer,
            $contextProvidersSerializer,
            $contextConsumersSerializer,
            $stubSlotsSerializer,
            $styleSerializer
        );

        $slotsSerializer = new ElementSlotsFieldSerializer($validator, $definitionRegistry, $baseSerializer);

        return new ContentElementFieldSerializer(
            $validator,
            $definitionRegistry,
            $dataRequirementsSerializer,
            $contextProvidersSerializer,
            $contextConsumersSerializer,
            $slotsSerializer,
            $styleSerializer
        );
    }

    /**
     * Registers one known option (col-span) so the composed style constraints reject an unregistered option.
     * deserialize() is registry-free, so it keeps a decoded style key verbatim regardless of this set.
     */
    private function buildStyleSerializer(
        ValidatorInterface $validator,
        DefinitionInstanceRegistry $definitionRegistry
    ): ElementStyleFieldSerializer {
        $registry = static::createStub(AbstractContentSystemStyleOptionRegistry::class);
        $registry->method('all')->willReturn([
            'col-span' => new StyleOptionSpecification(
                'col-span',
                new StyleOptionValueType('integer', null, ['min' => 1, 'max' => 12], null, null),
                true,
                null,
                'core',
            ),
        ]);

        return new ElementStyleFieldSerializer($validator, $definitionRegistry, $registry, new StyleOptionConstraintDeriver());
    }
}

/**
 * @internal
 */
final class ObjectWithToArray
{
    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return ['serialized' => 'value'];
    }
}
