<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Layout\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution\IndexedDistributionConfig;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution\IteratorDistributionConfig;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution\KeyedDistributionConfig;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution\SlicedDistributionConfig;
use Shopware\Core\Content\ContentSystem\Layout\Field\ContextProvidersField;
use Shopware\Core\Content\ContentSystem\Layout\Field\ContextProvidersFieldSerializer;
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
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ContextProvidersFieldSerializer::class)]
class ContextProvidersFieldSerializerTest extends TestCase
{
    private ContextProvidersFieldSerializer $serializer;

    private ContextProvidersFieldSerializer $serializerWithPassthroughValidator;

    private EntityExistence $existence;

    private WriteParameterBag $parameters;

    protected function setUp(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);

        $this->serializer = new ContextProvidersFieldSerializer($validator, $definitionRegistry);

        // Passthrough validator never raises violations — used when encoding ContextProvider objects
        // (the Type('array') constraint would otherwise reject them before serializer conversion)
        $passthroughValidator = static::createStub(ValidatorInterface::class);
        $passthroughValidator->method('validate')->willReturn(new ConstraintViolationList());

        $this->serializerWithPassthroughValidator = new ContextProvidersFieldSerializer(
            $passthroughValidator,
            $definitionRegistry
        );

        $this->existence = new EntityExistence('content_layout', ['id' => 'test'], true, false, false, []);
        $this->parameters = static::createStub(WriteParameterBag::class);
    }

    #[TestDox('encodes null value as null')]
    public function testEncodeWithNullYieldsNull(): void
    {
        $field = $this->createContextProvidersField();
        $kvPair = new KeyValuePair('provides_context', null, false);

        $result = iterator_to_array(
            $this->serializer->encode($field, $this->existence, $kvPair, $this->parameters)
        );

        static::assertArrayHasKey('provides_context', $result);
        static::assertNull($result['provides_context']);
    }

    #[TestDox('encodes ContextProvider array to JSON string')]
    public function testEncodeWithContextProviderArrayYieldsJson(): void
    {
        $field = $this->createContextProvidersField();
        $provider = new ContextProvider(
            type: ContextType::Single,
            distributionConfig: BroadcastDistributionConfig::simple()
        );

        $kvPair = new KeyValuePair('provides_context', ['product' => $provider], false);

        $result = iterator_to_array(
            $this->serializerWithPassthroughValidator->encode($field, $this->existence, $kvPair, $this->parameters)
        );

        static::assertArrayHasKey('provides_context', $result);
        $decoded = json_decode($result['provides_context'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decoded);
        static::assertArrayHasKey('product', $decoded);
        static::assertSame('single', $decoded['product']['type']);
        static::assertSame('broadcast', $decoded['product']['distribution']);
        static::assertNull($decoded['product']['consumer_alias']);
    }

    #[TestDox('encodes array of plain arrays as JSON passthrough')]
    public function testEncodeWithPlainArrayPassthroughYieldsJson(): void
    {
        $field = $this->createContextProvidersField();
        $arrayValue = [
            'product' => ['type' => 'single', 'distribution' => 'broadcast', 'consumer_alias' => null],
        ];
        $kvPair = new KeyValuePair('provides_context', $arrayValue, false);

        $result = iterator_to_array(
            $this->serializer->encode($field, $this->existence, $kvPair, $this->parameters)
        );

        static::assertArrayHasKey('provides_context', $result);
        $decoded = json_decode($result['provides_context'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame($arrayValue, $decoded);
    }

    #[TestDox('throws exception when encode receives wrong field type')]
    public function testEncodeThrowsOnNonStorageAwareField(): void
    {
        $invalidField = new TranslatedField('providesContext');
        $invalidField->compile(static::createStub(DefinitionInstanceRegistry::class));

        $kvPair = new KeyValuePair('provides_context', null, false);

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldType(StorageAware::class, TranslatedField::class)
        );

        iterator_to_array(
            $this->serializer->encode($invalidField, $this->existence, $kvPair, $this->parameters)
        );
    }

    #[TestDox('decodes null to null')]
    public function testDecodeWithNullReturnsNull(): void
    {
        $field = $this->createContextProvidersField();

        $result = $this->serializer->decode($field, null);

        static::assertNull($result);
    }

    #[TestDox('decodes JSON string to ContextProvider array for all distribution strategies')]
    public function testDecodeWithJsonStringReturnsContextProviders(): void
    {
        $field = $this->createContextProvidersField();
        $json = json_encode([
            'product' => ['type' => 'single', 'distribution' => 'broadcast', 'consumer_alias' => null],
            'items' => ['type' => 'collection', 'distribution' => 'indexed', 'consumer_alias' => null],
            'iter' => ['type' => 'collection', 'distribution' => 'iterator', 'consumer_alias' => null],
            'keyed' => ['type' => 'collection', 'distribution' => 'keyed', 'key_property' => 'data_key', 'consumer_alias' => null],
            'sliced' => ['type' => 'collection', 'distribution' => 'sliced', 'slice_size' => 3, 'consumer_alias' => null],
        ], \JSON_THROW_ON_ERROR);

        $result = $this->serializer->decode($field, $json);

        static::assertIsArray($result);
        static::assertCount(5, $result);
        static::assertArrayHasKey('product', $result);
        static::assertArrayHasKey('items', $result);
        static::assertInstanceOf(ContextProvider::class, $result['product']);
        static::assertSame(ContextType::Single, $result['product']->type);
        static::assertInstanceOf(BroadcastDistributionConfig::class, $result['product']->distributionConfig);
        static::assertInstanceOf(ContextProvider::class, $result['items']);
        static::assertSame(ContextType::Collection, $result['items']->type);
        static::assertInstanceOf(IndexedDistributionConfig::class, $result['items']->distributionConfig);
        static::assertInstanceOf(IteratorDistributionConfig::class, $result['iter']->distributionConfig);
        static::assertInstanceOf(KeyedDistributionConfig::class, $result['keyed']->distributionConfig);
        static::assertInstanceOf(SlicedDistributionConfig::class, $result['sliced']->distributionConfig);
    }

    #[TestDox('skips non-array and non-string-key entries during decode')]
    public function testDecodeSkipsNonArrayEntries(): void
    {
        $field = $this->createContextProvidersField();
        $json = json_encode([
            'valid' => ['type' => 'single', 'distribution' => 'broadcast', 'consumer_alias' => null],
            'invalid' => 'not-an-array',
        ], \JSON_THROW_ON_ERROR);

        $result = $this->serializer->decode($field, $json);

        static::assertIsArray($result);
        static::assertCount(1, $result);
        static::assertArrayHasKey('valid', $result);
        static::assertArrayNotHasKey('invalid', $result);
    }

    #[TestDox('throws exception when decode receives wrong field type')]
    public function testDecodeThrowsOnNonContextProvidersField(): void
    {
        $invalidField = new JsonField('provides_context', 'providesContext');
        $invalidField->compile(static::createStub(DefinitionInstanceRegistry::class));

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldType(ContextProvidersField::class, JsonField::class)
        );

        $this->serializer->decode($invalidField, ['some' => 'data']);
    }

    #[TestDox('throws exception when decode receives non-string non-array non-null value')]
    public function testDecodeThrowsOnInvalidValueType(): void
    {
        $field = $this->createContextProvidersField();

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('provides_context', 'array', 'integer')
        );

        $this->serializer->decode($field, 42);
    }

    /**
     * @return iterable<string, array{ContextProvider, array<string, mixed>}>
     */
    public static function serializeContextProviderProvider(): iterable
    {
        yield 'broadcast distribution config' => [
            new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple()),
            ['type' => 'single', 'distribution' => 'broadcast', 'consumer_alias' => null],
        ];
        yield 'indexed distribution config' => [
            new ContextProvider(ContextType::Collection, IndexedDistributionConfig::simple()),
            ['type' => 'collection', 'distribution' => 'indexed', 'consumer_alias' => null],
        ];
        yield 'iterator distribution config' => [
            new ContextProvider(ContextType::Collection, IteratorDistributionConfig::simple()),
            ['type' => 'collection', 'distribution' => 'iterator', 'consumer_alias' => null],
        ];
        yield 'keyed distribution config' => [
            new ContextProvider(ContextType::Collection, KeyedDistributionConfig::simple()),
            ['type' => 'collection', 'distribution' => 'keyed', 'key_property' => 'data_key', 'consumer_alias' => null],
        ];
        yield 'sliced distribution config' => [
            new ContextProvider(ContextType::Collection, SlicedDistributionConfig::withSliceSize(5)),
            ['type' => 'collection', 'distribution' => 'sliced', 'slice_size' => 5, 'consumer_alias' => null],
        ];
    }

    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('serializeContextProviderProvider')]
    #[TestDox('serializes ContextProvider to correct array representation')]
    public function testSerializeContextProvider(ContextProvider $provider, array $expected): void
    {
        $result = $this->serializer->serializeContextProvider($provider);

        foreach ($expected as $key => $value) {
            static::assertArrayHasKey($key, $result);
            static::assertSame($value, $result[$key]);
        }
    }

    #[TestDox('returns Type array and All constraints without Required flag')]
    public function testBuildConstraintsWithoutRequiredFlagReturnsExpectedStructure(): void
    {
        $field = $this->createContextProvidersField();

        $constraints = $this->serializer->buildConstraints($field);

        static::assertCount(2, $constraints);
        static::assertInstanceOf(Type::class, $constraints[0]);
        static::assertInstanceOf(All::class, $constraints[1]);

        $allConstraint = $constraints[1];
        static::assertIsArray($allConstraint->constraints);
        $innerConstraints = $allConstraint->constraints;
        static::assertCount(2, $innerConstraints);

        $collection = $innerConstraints[0];
        static::assertInstanceOf(Collection::class, $collection);

        $fields = $collection->fields;
        static::assertArrayHasKey('type', $fields);
        static::assertArrayHasKey('distribution', $fields);
        static::assertTrue($collection->allowExtraFields);
        static::assertFalse($collection->allowMissingFields);

        // Symfony Collection wraps raw arrays in Required automatically
        $typeEntry = $fields['type'];
        static::assertInstanceOf(\Symfony\Component\Validator\Constraints\Required::class, $typeEntry);
        $typeConstraints = $typeEntry->constraints;
        static::assertIsArray($typeConstraints);
        static::assertCount(2, $typeConstraints);
        static::assertInstanceOf(NotBlank::class, $typeConstraints[0]);
        static::assertInstanceOf(Choice::class, $typeConstraints[1]);

        $distributionEntry = $fields['distribution'];
        static::assertInstanceOf(\Symfony\Component\Validator\Constraints\Required::class, $distributionEntry);
        $distributionConstraints = $distributionEntry->constraints;
        static::assertIsArray($distributionConstraints);
        static::assertCount(2, $distributionConstraints);
        static::assertInstanceOf(NotBlank::class, $distributionConstraints[0]);
        static::assertInstanceOf(Choice::class, $distributionConstraints[1]);
    }

    #[TestDox('appends NotBlank constraint when field has Required flag')]
    public function testBuildConstraintsWithRequiredFlagAddsNotBlank(): void
    {
        $field = $this->createContextProvidersFieldWithRequired();

        $constraints = $this->serializer->buildConstraints($field);

        static::assertCount(3, $constraints);
        static::assertInstanceOf(Type::class, $constraints[0]);
        static::assertInstanceOf(All::class, $constraints[1]);
        static::assertInstanceOf(NotBlank::class, $constraints[2]);
    }

    private function createContextProvidersField(): ContextProvidersField
    {
        $field = new ContextProvidersField('provides_context', 'providesContext');
        $field->compile(static::createStub(DefinitionInstanceRegistry::class));

        return $field;
    }

    private function createContextProvidersFieldWithRequired(): ContextProvidersField
    {
        $field = new ContextProvidersField('provides_context', 'providesContext');
        $field->addFlags(new Required());
        $field->compile(static::createStub(DefinitionInstanceRegistry::class));

        return $field;
    }
}
