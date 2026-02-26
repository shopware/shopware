<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Layout\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Content\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Content\ContentSystem\Layout\Field\DataRequirementsField;
use Shopware\Core\Content\ContentSystem\Layout\Field\DataRequirementsFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\Validator\Constraints\All;
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
#[CoversClass(DataRequirementsFieldSerializer::class)]
class DataRequirementsFieldSerializerTest extends TestCase
{
    private DataRequirementsFieldSerializer $serializer;

    private DataRequirementsFieldSerializer $serializerWithPassthroughValidator;

    /**
     * @var DataLoaderConfigSerializerProvider&Stub
     */
    private DataLoaderConfigSerializerProvider $configProvider;

    private EntityExistence $existence;

    private WriteParameterBag $parameters;

    protected function setUp(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);

        $this->configProvider = static::createStub(DataLoaderConfigSerializerProvider::class);

        $this->serializer = new DataRequirementsFieldSerializer($validator, $definitionRegistry, $this->configProvider);

        // Passthrough validator never raises violations — used when encoding DataRequirement objects
        // (the Type('array') constraint would otherwise reject them before serializer conversion)
        $passthroughValidator = static::createStub(ValidatorInterface::class);
        $passthroughValidator->method('validate')->willReturn(new ConstraintViolationList());

        $this->serializerWithPassthroughValidator = new DataRequirementsFieldSerializer(
            $passthroughValidator,
            $definitionRegistry,
            $this->configProvider
        );

        $this->existence = new EntityExistence('content_layout', ['id' => 'test'], true, false, false, []);
        $this->parameters = static::createStub(WriteParameterBag::class);
    }

    #[TestDox('encodes DataRequirement array to JSON string')]
    public function testEncodeWithDataRequirementArrayYieldsJson(): void
    {
        $field = $this->createDataRequirementsField();
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $requirement = new DataRequirement('my-key', 'entity', $config);

        $this->configProvider->method('encode')->willReturn(['entityName' => 'product', 'id' => 'abc']);

        $kvPair = new KeyValuePair('data_requirements', ['my-key' => $requirement], false);

        $result = iterator_to_array(
            $this->serializerWithPassthroughValidator->encode($field, $this->existence, $kvPair, $this->parameters)
        );

        static::assertArrayHasKey('data_requirements', $result);
        $decoded = json_decode($result['data_requirements'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decoded);
        static::assertArrayHasKey('my-key', $decoded);
        static::assertSame('my-key', $decoded['my-key']['key']);
        static::assertSame('entity', $decoded['my-key']['source']);
        static::assertSame(['entityName' => 'product', 'id' => 'abc'], $decoded['my-key']['config']);
    }

    #[TestDox('encodes array of plain arrays as JSON passthrough')]
    public function testEncodeWithPlainArrayPassthroughYieldsJson(): void
    {
        $field = $this->createDataRequirementsField();
        $arrayValue = [
            'product-data' => ['key' => 'product-data', 'source' => 'entity', 'config' => []],
        ];
        $kvPair = new KeyValuePair('data_requirements', $arrayValue, false);

        $result = iterator_to_array(
            $this->serializer->encode($field, $this->existence, $kvPair, $this->parameters)
        );

        static::assertArrayHasKey('data_requirements', $result);
        $decoded = json_decode($result['data_requirements'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame($arrayValue, $decoded);
    }

    #[TestDox('encodes non-array non-null value as JSON directly')]
    public function testEncodeWithNonArrayValueYieldsJsonEncoded(): void
    {
        $field = $this->createDataRequirementsField();
        // A scalar string-like value that is not null and not array — edge case
        $kvPair = new KeyValuePair('data_requirements', 'raw-string', false);

        $result = iterator_to_array(
            $this->serializerWithPassthroughValidator->encode($field, $this->existence, $kvPair, $this->parameters)
        );

        static::assertArrayHasKey('data_requirements', $result);
        static::assertSame('"raw-string"', $result['data_requirements']);
    }

    #[TestDox('encodes null value as null')]
    public function testEncodeWithNullYieldsNull(): void
    {
        $field = $this->createDataRequirementsField();
        $kvPair = new KeyValuePair('data_requirements', null, false);

        $result = iterator_to_array(
            $this->serializer->encode($field, $this->existence, $kvPair, $this->parameters)
        );

        static::assertArrayHasKey('data_requirements', $result);
        static::assertNull($result['data_requirements']);
    }

    #[TestDox('throws exception when encode receives wrong field type')]
    public function testEncodeThrowsOnNonDataRequirementsField(): void
    {
        $invalidField = new JsonField('data_requirements', 'dataRequirements');
        $invalidField->compile(static::createStub(DefinitionInstanceRegistry::class));

        $kvPair = new KeyValuePair('data_requirements', null, false);

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldType(DataRequirementsField::class, JsonField::class)
        );

        iterator_to_array(
            $this->serializer->encode($invalidField, $this->existence, $kvPair, $this->parameters)
        );
    }

    #[TestDox('decodes JSON string to DataRequirement array')]
    public function testDecodeWithJsonStringReturnsDataRequirements(): void
    {
        $field = $this->createDataRequirementsField();
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $this->configProvider->method('decode')->willReturn($config);

        $json = json_encode([
            'nav-data' => ['key' => 'nav-data', 'source' => 'navigation', 'config' => []],
            'products' => ['key' => 'products', 'source' => 'entity_collection', 'config' => ['ids' => []]],
        ], \JSON_THROW_ON_ERROR);

        $result = $this->serializer->decode($field, $json);

        static::assertIsArray($result);
        static::assertCount(2, $result);
        static::assertArrayHasKey('nav-data', $result);
        static::assertArrayHasKey('products', $result);
        static::assertInstanceOf(DataRequirement::class, $result['nav-data']);
        static::assertSame('nav-data', $result['nav-data']->key);
        static::assertSame('navigation', $result['nav-data']->source);
        static::assertSame('entity_collection', $result['products']->source);
    }

    #[TestDox('decodes array directly to DataRequirement array')]
    public function testDecodeWithArrayReturnsDataRequirements(): void
    {
        $field = $this->createDataRequirementsField();
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $this->configProvider->method('decode')->willReturn($config);

        $data = ['product-data' => ['key' => 'product-data', 'source' => 'entity', 'config' => ['id' => 'abc']]];

        $result = $this->serializer->decode($field, $data);

        static::assertIsArray($result);
        static::assertArrayHasKey('product-data', $result);
        static::assertInstanceOf(DataRequirement::class, $result['product-data']);
        static::assertSame('product-data', $result['product-data']->key);
        static::assertSame('entity', $result['product-data']->source);
        static::assertSame($config, $result['product-data']->config);
    }

    #[TestDox('decodes entry using array key as key when key field is missing')]
    public function testDecodeUsesArrayKeyWhenKeyFieldAbsent(): void
    {
        $field = $this->createDataRequirementsField();
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $this->configProvider->method('decode')->willReturn($config);

        // Entry without 'key' field — should fall back to the array key 'my-req'
        $data = ['my-req' => ['source' => 'entity', 'config' => []]];

        $result = $this->serializer->decode($field, $data);

        static::assertIsArray($result);
        static::assertArrayHasKey('my-req', $result);
        static::assertSame('my-req', $result['my-req']->key);
    }

    #[TestDox('decodes null to null')]
    public function testDecodeWithNullReturnsNull(): void
    {
        $field = $this->createDataRequirementsField();

        $result = $this->serializer->decode($field, null);

        static::assertNull($result);
    }

    #[TestDox('throws exception when decode receives wrong field type')]
    public function testDecodeThrowsOnNonDataRequirementsField(): void
    {
        $invalidField = new JsonField('data_requirements', 'dataRequirements');
        $invalidField->compile(static::createStub(DefinitionInstanceRegistry::class));

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldType(DataRequirementsField::class, JsonField::class)
        );

        $this->serializer->decode($invalidField, ['some' => 'data']);
    }

    #[TestDox('throws exception when decode receives non-string non-array non-null value')]
    public function testDecodeThrowsOnInvalidValueType(): void
    {
        $field = $this->createDataRequirementsField();

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('data_requirements', 'array', 'integer')
        );

        $this->serializer->decode($field, 42);
    }

    #[TestDox('serializes DataRequirement to array with key, source, and encoded config')]
    public function testSerializeDataRequirementReturnsExpectedArray(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $requirement = new DataRequirement('product-data', 'entity', $config);

        $this->configProvider->method('encode')->willReturn(['entityName' => 'product', 'id' => 'test-id']);

        $result = $this->serializer->serializeDataRequirement($requirement);

        static::assertSame('product-data', $result['key']);
        static::assertSame('entity', $result['source']);
        static::assertSame(['entityName' => 'product', 'id' => 'test-id'], $result['config']);
    }

    #[TestDox('returns Type array and All Collection constraints with expected field structure')]
    public function testBuildConstraintsWithoutRequiredFlagReturnsExpectedStructure(): void
    {
        $field = $this->createDataRequirementsField();

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
        static::assertArrayHasKey('key', $fields);
        static::assertArrayHasKey('source', $fields);
        static::assertArrayHasKey('config', $fields);
        static::assertFalse($collection->allowExtraFields);
        static::assertFalse($collection->allowMissingFields);

        // 'key' is Optional (wrapped by Symfony's Collection into Optional)
        static::assertInstanceOf(Optional::class, $fields['key']);

        // 'source' is a Required wrapper (Symfony wraps array constraints from Collection fields)
        // The Required wrapper contains NotBlank and Type constraints
        static::assertInstanceOf(\Symfony\Component\Validator\Constraints\Required::class, $fields['source']);
        $sourceConstraints = $fields['source']->constraints;
        static::assertIsArray($sourceConstraints);
        static::assertCount(2, $sourceConstraints);
        static::assertInstanceOf(NotBlank::class, $sourceConstraints[0]);
        static::assertInstanceOf(Type::class, $sourceConstraints[1]);

        // 'config' is Optional
        static::assertInstanceOf(Optional::class, $fields['config']);
    }

    #[TestDox('appends NotBlank constraint when field has Required flag')]
    public function testBuildConstraintsWithRequiredFlagAddsNotBlank(): void
    {
        $field = $this->createDataRequirementsFieldWithRequired();

        $constraints = $this->serializer->buildConstraints($field);

        static::assertCount(3, $constraints);
        static::assertInstanceOf(Type::class, $constraints[0]);
        static::assertInstanceOf(All::class, $constraints[1]);
        static::assertInstanceOf(NotBlank::class, $constraints[2]);
    }

    private function createDataRequirementsField(): DataRequirementsField
    {
        $field = new DataRequirementsField('data_requirements', 'dataRequirements');
        $field->compile(static::createStub(DefinitionInstanceRegistry::class));

        return $field;
    }

    private function createDataRequirementsFieldWithRequired(): DataRequirementsField
    {
        $field = new DataRequirementsField('data_requirements', 'dataRequirements');
        $field->addFlags(new Required());
        $field->compile(static::createStub(DefinitionInstanceRegistry::class));

        return $field;
    }
}
