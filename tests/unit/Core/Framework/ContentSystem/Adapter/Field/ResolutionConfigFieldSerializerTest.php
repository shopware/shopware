<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Adapter\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Adapter\Field\CriteriaFilterFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Adapter\Field\ResolutionConfigField;
use Shopware\Core\Framework\ContentSystem\Adapter\Field\ResolutionConfigFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Adapter\ParameterBinding\ResolutionConfig;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Test\Stub\ContentSystem\EmptyProductDefinition;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ResolutionConfigFieldSerializer::class)]
class ResolutionConfigFieldSerializerTest extends TestCase
{
    private ResolutionConfigFieldSerializer $serializer;

    private ResolutionConfigFieldSerializer $serializerWithPassthroughValidator;

    private EntityExistence $existence;

    private WriteParameterBag $parameters;

    protected function setUp(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $filterSerializer = new CriteriaFilterFieldSerializer($validator, $definitionRegistry);

        $this->serializer = new ResolutionConfigFieldSerializer($validator, $definitionRegistry, $filterSerializer);

        // Passthrough validator never raises violations — used when value is a ResolutionConfig object
        // (the Type('array') constraint would otherwise reject it before the serializer converts it)
        $passthroughValidator = static::createStub(ValidatorInterface::class);
        $passthroughValidator->method('validate')->willReturn(new ConstraintViolationList());
        $filterSerializerPassthrough = new CriteriaFilterFieldSerializer($passthroughValidator, $definitionRegistry);
        $this->serializerWithPassthroughValidator = new ResolutionConfigFieldSerializer(
            $passthroughValidator,
            $definitionRegistry,
            $filterSerializerPassthrough
        );

        $this->existence = new EntityExistence('content_layout', ['id' => 'test'], true, false, false, []);
        $this->parameters = static::createStub(WriteParameterBag::class);
    }

    #[TestDox('encodes ResolutionConfig object to JSON string')]
    public function testEncodeWithResolutionConfigObject(): void
    {
        $field = $this->createResolutionConfigField();
        $config = new ResolutionConfig('product', 'id');
        $kvPair = new KeyValuePair('resolution_config', $config, false);

        // Use passthrough validator: the Type('array') constraint would reject the ResolutionConfig
        // object before serialization converts it to array — bypassing is the intended encode path
        $result = iterator_to_array(
            $this->serializerWithPassthroughValidator->encode($field, $this->existence, $kvPair, $this->parameters)
        );

        static::assertArrayHasKey('resolution_config', $result);
        $decoded = json_decode($result['resolution_config'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('product', $decoded['entity']);
        static::assertSame('id', $decoded['match_field']);
        static::assertArrayNotHasKey('constraints', $decoded);
    }

    #[TestDox('encodes array value as JSON passthrough')]
    public function testEncodeWithArrayPassthrough(): void
    {
        $field = $this->createResolutionConfigField();
        $arrayValue = ['entity' => 'category', 'match_field' => 'slug'];
        $kvPair = new KeyValuePair('resolution_config', $arrayValue, false);

        $result = iterator_to_array($this->serializer->encode($field, $this->existence, $kvPair, $this->parameters));

        static::assertArrayHasKey('resolution_config', $result);
        static::assertSame(Json::encode($arrayValue), $result['resolution_config']);
    }

    #[TestDox('encodes array value when field is marked as required')]
    public function testEncodeWithRequiredField(): void
    {
        $field = new ResolutionConfigField('resolution_config', 'resolutionConfig');
        $field->addFlags(new Required());
        $field->compile(static::createStub(DefinitionInstanceRegistry::class));

        $arrayValue = ['entity' => 'product', 'match_field' => 'id'];
        $kvPair = new KeyValuePair('resolution_config', $arrayValue, false);

        $result = iterator_to_array($this->serializer->encode($field, $this->existence, $kvPair, $this->parameters));

        static::assertArrayHasKey('resolution_config', $result);
        static::assertIsString($result['resolution_config']);
    }

    #[TestDox('encodes null value as null')]
    public function testEncodeWithNull(): void
    {
        $field = $this->createResolutionConfigField();
        $kvPair = new KeyValuePair('resolution_config', null, false);

        $result = iterator_to_array($this->serializer->encode($field, $this->existence, $kvPair, $this->parameters));

        static::assertArrayHasKey('resolution_config', $result);
        static::assertNull($result['resolution_config']);
    }

    #[TestDox('throws exception when encode receives non-StorageAware field')]
    public function testEncodeThrowsOnNonStorageAwareField(): void
    {
        // TranslatedField extends Field but does NOT implement StorageAware
        $invalidField = new TranslatedField('resolutionConfig');
        $kvPair = new KeyValuePair('resolution_config', null, false);

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldType(StorageAware::class, TranslatedField::class)
        );

        iterator_to_array($this->serializer->encode($invalidField, $this->existence, $kvPair, $this->parameters));
    }

    #[TestDox('decodes JSON string to ResolutionConfig object')]
    public function testDecodeWithJsonString(): void
    {
        $field = $this->createResolutionConfigField();
        $json = json_encode(['entity' => 'product', 'match_field' => 'productNumber'], \JSON_THROW_ON_ERROR);

        $result = $this->serializer->decode($field, $json);

        static::assertInstanceOf(ResolutionConfig::class, $result);
        static::assertSame('product', $result->entity);
        static::assertSame('productNumber', $result->matchField);
        static::assertSame([], $result->constraints);
    }

    #[TestDox('decodes array to ResolutionConfig object')]
    public function testDecodeWithArray(): void
    {
        $field = $this->createResolutionConfigField();
        $arrayValue = ['entity' => 'category', 'match_field' => 'id'];

        $result = $this->serializer->decode($field, $arrayValue);

        static::assertInstanceOf(ResolutionConfig::class, $result);
        static::assertSame('category', $result->entity);
        static::assertSame('id', $result->matchField);
    }

    #[TestDox('decodes null to null')]
    public function testDecodeWithNull(): void
    {
        $field = $this->createResolutionConfigField();

        $result = $this->serializer->decode($field, null);

        static::assertNull($result);
    }

    #[TestDox('throws exception when decode receives non-ResolutionConfigField')]
    public function testDecodeThrowsOnWrongFieldType(): void
    {
        $invalidField = new JsonField('resolution_config', 'resolutionConfig');
        $invalidField->compile(static::createStub(DefinitionInstanceRegistry::class));

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldType(ResolutionConfigField::class, JsonField::class)
        );

        $this->serializer->decode($invalidField, ['entity' => 'product', 'match_field' => 'id']);
    }

    #[TestDox('throws exception when decode receives non-array non-string value')]
    public function testDecodeThrowsOnInvalidValueType(): void
    {
        $field = $this->createResolutionConfigField();

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('resolution', 'array', 'integer')
        );

        $this->serializer->decode($field, 42);
    }

    #[TestDox('serializes ResolutionConfig without constraints')]
    public function testSerializeResolutionConfigWithoutConstraints(): void
    {
        $config = new ResolutionConfig('product', 'id');

        $result = $this->serializer->serializeResolutionConfig($config);

        static::assertSame('product', $result['entity']);
        static::assertSame('id', $result['match_field']);
        static::assertArrayNotHasKey('constraints', $result);
    }

    #[TestDox('serializes ResolutionConfig with Filter constraints')]
    public function testSerializeResolutionConfigWithFilterConstraints(): void
    {
        $filter1 = new EqualsFilter('active', true);
        $filter2 = new EqualsFilter('stock', 0);
        $config = new ResolutionConfig('product', 'productNumber', [$filter1, $filter2]);

        $result = $this->serializer->serializeResolutionConfig($config);

        static::assertSame('product', $result['entity']);
        static::assertSame('productNumber', $result['match_field']);
        static::assertArrayHasKey('constraints', $result);
        static::assertCount(2, $result['constraints']);
        static::assertSame('equals', $result['constraints'][0]['type']);
        static::assertSame('active', $result['constraints'][0]['field']);
        static::assertTrue($result['constraints'][0]['value']);
        static::assertSame('equals', $result['constraints'][1]['type']);
        static::assertSame('stock', $result['constraints'][1]['field']);
    }

    #[TestDox('serializes ResolutionConfig with array constraints passed through directly')]
    public function testSerializeResolutionConfigWithArrayConstraints(): void
    {
        $arrayConstraint = ['type' => 'equals', 'field' => 'active', 'value' => true];
        // @phpstan-ignore argument.type (intentionally passing array constraint to test the is_array passthrough branch)
        $config = new ResolutionConfig('product', 'productNumber', [$arrayConstraint]);

        $result = $this->serializer->serializeResolutionConfig($config);

        static::assertArrayHasKey('constraints', $result);
        static::assertCount(1, $result['constraints']);
        static::assertSame($arrayConstraint, $result['constraints'][0]);
    }

    #[TestDox('deserializes array data to ResolutionConfig without constraints')]
    public function testDeserializeResolutionConfigWithoutConstraints(): void
    {
        $data = ['entity' => 'product', 'match_field' => 'productNumber'];

        $result = $this->serializer->deserializeResolutionConfig($data);

        static::assertSame('product', $result->entity);
        static::assertSame('productNumber', $result->matchField);
        static::assertSame([], $result->constraints);
    }

    #[TestDox('deserializes array data with constraints using entity definition')]
    public function testDeserializeResolutionConfigWithConstraints(): void
    {
        $serializer = $this->createSerializerWithEntityDefinition();

        $data = [
            'entity' => 'product',
            'match_field' => 'productNumber',
            'constraints' => [
                ['type' => 'equals', 'field' => 'active', 'value' => true],
            ],
        ];

        $result = $serializer->deserializeResolutionConfig($data);

        static::assertSame('product', $result->entity);
        static::assertSame('productNumber', $result->matchField);
        static::assertCount(1, $result->constraints);
        static::assertInstanceOf(EqualsFilter::class, $result->constraints[0]);
    }

    #[TestDox('deserializes array data skipping non-array constraint entries')]
    public function testDeserializeResolutionConfigSkipsNonArrayConstraints(): void
    {
        $serializer = $this->createSerializerWithEntityDefinition();

        $data = [
            'entity' => 'product',
            'match_field' => 'id',
            'constraints' => [
                'not-an-array',
                ['type' => 'equals', 'field' => 'active', 'value' => true],
            ],
        ];

        $result = $serializer->deserializeResolutionConfig($data);

        static::assertCount(1, $result->constraints);
        static::assertInstanceOf(EqualsFilter::class, $result->constraints[0]);
    }

    #[TestDox('deserializes array data using default match_field when missing')]
    public function testDeserializeResolutionConfigUsesDefaultMatchField(): void
    {
        $data = ['entity' => 'product'];

        $result = $this->serializer->deserializeResolutionConfig($data);

        static::assertSame('product', $result->entity);
        static::assertSame('id', $result->matchField);
    }

    #[TestDox('deserializes array data skipping constraints when entity is empty')]
    public function testDeserializeResolutionConfigSkipsConstraintsWhenEntityEmpty(): void
    {
        $data = [
            'entity' => '',
            'match_field' => 'id',
            'constraints' => [
                ['type' => 'equals', 'field' => 'active', 'value' => true],
            ],
        ];

        $result = $this->serializer->deserializeResolutionConfig($data);

        static::assertSame('', $result->entity);
        static::assertSame([], $result->constraints);
    }

    private function createSerializerWithEntityDefinition(): ResolutionConfigFieldSerializer
    {
        $definition = new EmptyProductDefinition();

        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $definitionRegistry
            ->method('getByEntityName')
            ->willReturn($definition);

        $filterSerializer = new CriteriaFilterFieldSerializer($validator, $definitionRegistry);

        return new ResolutionConfigFieldSerializer($validator, $definitionRegistry, $filterSerializer);
    }

    private function createResolutionConfigField(): ResolutionConfigField
    {
        $field = new ResolutionConfigField('resolution_config', 'resolutionConfig');
        $field->compile(static::createStub(DefinitionInstanceRegistry::class));

        return $field;
    }
}
