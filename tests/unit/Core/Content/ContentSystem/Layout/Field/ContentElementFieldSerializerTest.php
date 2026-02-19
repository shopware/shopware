<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Layout\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Content\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Content\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Content\ContentSystem\Layout\Field\ContentElementField;
use Shopware\Core\Content\ContentSystem\Layout\Field\ContentElementFieldSerializer;
use Shopware\Core\Content\ContentSystem\Layout\Field\ContextConsumersFieldSerializer;
use Shopware\Core\Content\ContentSystem\Layout\Field\ContextProvidersFieldSerializer;
use Shopware\Core\Content\ContentSystem\Layout\Field\DataRequirementsFieldSerializer;
use Shopware\Core\Content\ContentSystem\Layout\Field\ElementSlotsFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\ContentElementBuilder;
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
                \Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware::class,
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
    public function testDecodeElementWithMinimalFieldsReturnsContentElement(): void
    {
        $minimal = $this->serializer->decodeElement([
            'id' => 'minimal-id',
            'component' => 'hero',
        ]);

        static::assertSame('minimal-id', $minimal->getId());
        static::assertSame('hero', $minimal->getComponent());
        static::assertSame([], $minimal->getDataRequirements());
        static::assertFalse($minimal->hasSlots());
        static::assertSame([], $minimal->getProvidesContext());
        static::assertSame([], $minimal->getAcceptsContext());
    }

    #[TestDox('decodes element with properties into a ContentElement with accessible property values')]
    public function testDecodeElementWithPropertiesReturnsContentElementWithProperties(): void
    {
        $withProperties = $this->serializer->decodeElement([
            'id' => 'elem-props',
            'component' => 'image',
            'properties' => ['src' => '/path/to/image.png', 'alt' => 'hero image'],
        ]);

        static::assertSame('/path/to/image.png', $withProperties->getProperty('src'));
        static::assertSame('hero image', $withProperties->getProperty('alt'));
    }

    #[TestDox('decodes element with data_requirements')]
    public function testDecodeElementWithDataRequirementsReturnsContentElementWithRequirements(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $this->configProvider->method('decode')->willReturn($config);

        $data = [
            'id' => 'elem-reqs',
            'component' => 'product-card',
            'data_requirements' => [
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

    #[TestDox('decodes element with context providers and consumers')]
    public function testDecodeElementWithContextDefinitionsReturnsContentElementWithContext(): void
    {
        $data = [
            'id' => 'elem-ctx',
            'component' => 'context-aware',
            'provides_context' => [
                'myData' => [
                    'type' => 'single',
                    'distribution' => 'broadcast',
                    'consumer_alias' => null,
                ],
            ],
            'accepts_context' => [
                'parentData' => [
                    'type' => 'single',
                    'required' => false,
                ],
            ],
        ];

        $result = $this->serializer->decodeElement($data);

        static::assertCount(1, $result->getProvidesContext());
        static::assertArrayHasKey('myData', $result->getProvidesContext());
        static::assertInstanceOf(ContextProvider::class, $result->getProvidesContext()['myData']);

        static::assertCount(1, $result->getAcceptsContext());
        static::assertArrayHasKey('parentData', $result->getAcceptsContext());
        static::assertInstanceOf(ContextConsumer::class, $result->getAcceptsContext()['parentData']);
    }

    #[TestDox('throws exception when decodeElement receives data without id field')]
    public function testDecodeElementThrowsWhenIdFieldMissing(): void
    {
        $data = ['component' => 'text'];

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('id', 'string', 'NULL')
        );

        $this->serializer->decodeElement($data);
    }

    #[TestDox('throws exception when decodeElement receives data without component field')]
    public function testDecodeElementThrowsWhenComponentFieldMissing(): void
    {
        $data = ['id' => 'elem-1'];

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('component', 'string', 'NULL')
        );

        $this->serializer->decodeElement($data);
    }

    #[TestDox('serializes ContentElement with minimal fields to array')]
    public function testSerializeContentElementWithMinimalFieldsReturnsExpectedArray(): void
    {
        $element = ContentElementBuilder::create('hero', 'elem-minimal')
            ->build();

        $result = $this->serializer->serializeContentElement($element);

        static::assertSame('elem-minimal', $result['id']);
        static::assertSame('hero', $result['component']);
        static::assertSame([], $result['properties']);
        static::assertArrayNotHasKey('data_requirements', $result);
        static::assertArrayNotHasKey('slots', $result);
        static::assertArrayNotHasKey('provides_context', $result);
        static::assertArrayNotHasKey('accepts_context', $result);
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

        static::assertArrayHasKey('data_requirements', $result);
        static::assertArrayHasKey('product', $result['data_requirements']);
        static::assertSame('product', $result['data_requirements']['product']['key']);
        static::assertSame('entity', $result['data_requirements']['product']['source']);
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

        static::assertArrayHasKey('provides_context', $result);
        static::assertArrayHasKey('myData', $result['provides_context']);
        static::assertSame('single', $result['provides_context']['myData']['type']);
        static::assertSame('broadcast', $result['provides_context']['myData']['distribution']);
    }

    #[TestDox('serializes ContentElement with context consumers to array')]
    public function testSerializeContentElementWithContextConsumersIncludesConsumers(): void
    {
        $element = ContentElementBuilder::create('consumer', 'elem-consumer')
            ->withConsumer('parentData', ContextType::Single, false)
            ->build();

        $result = $this->serializer->serializeContentElement($element);

        static::assertArrayHasKey('accepts_context', $result);
        static::assertArrayHasKey('parentData', $result['accepts_context']);
        static::assertSame('single', $result['accepts_context']['parentData']['type']);
        static::assertFalse($result['accepts_context']['parentData']['required']);
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
        $restored = $this->serializer->decodeElement($serialized);

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
        static::assertArrayHasKey('data_requirements', $collection->fields);
        static::assertArrayHasKey('slots', $collection->fields);
        static::assertArrayHasKey('provides_context', $collection->fields);
        static::assertArrayHasKey('accepts_context', $collection->fields);
        static::assertFalse($collection->allowExtraFields);
        static::assertFalse($collection->allowMissingFields);

        static::assertInstanceOf(Optional::class, $collection->fields['data_requirements']);
        static::assertInstanceOf(Optional::class, $collection->fields['slots']);
        static::assertInstanceOf(Optional::class, $collection->fields['provides_context']);
        static::assertInstanceOf(Optional::class, $collection->fields['accepts_context']);
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
            // ElementSlotsFieldSerializer needs ContentElementFieldSerializer — build placeholder first
            // and inject after construction via closure binding
            new ElementSlotsFieldSerializer($validator, $definitionRegistry, $this->buildPlaceholderElementSerializer($validator, $definitionRegistry))
        );

        // Build the canonical serializer with a real ElementSlotsFieldSerializer that references back
        $slotsSerializer = new ElementSlotsFieldSerializer($validator, $definitionRegistry, $realSerializer);
        $canonicalSerializer = new ContentElementFieldSerializer(
            $validator,
            $definitionRegistry,
            $dataRequirementsSerializer,
            $contextProvidersSerializer,
            $contextConsumersSerializer,
            $slotsSerializer
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
            new ElementSlotsFieldSerializer($passthroughValidator, $definitionRegistry, $canonicalSerializer)
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
            $slotsSerializer
        );
    }
}
