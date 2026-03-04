<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Adapter\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Adapter\Field\ParameterBindingField;
use Shopware\Core\Framework\ContentSystem\Adapter\Field\ParameterBindingFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Adapter\Field\ResolutionConfigFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Adapter\ParameterBinding\ParameterBinding;
use Shopware\Core\Framework\ContentSystem\Adapter\ParameterBinding\ResolutionConfig;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
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
#[CoversClass(ParameterBindingFieldSerializer::class)]
class ParameterBindingFieldSerializerTest extends TestCase
{
    private ParameterBindingFieldSerializer $serializer;

    private ParameterBindingFieldSerializer $serializerWithPassthroughValidator;

    private EntityExistence $existence;

    private WriteParameterBag $parameters;

    protected function setUp(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);

        $resolutionConfigSerializer = static::createStub(ResolutionConfigFieldSerializer::class);

        $this->serializer = new ParameterBindingFieldSerializer($validator, $definitionRegistry, $resolutionConfigSerializer);

        // Passthrough validator never raises violations — used when value is a ParameterBinding object
        // (the Type('array') constraint would otherwise reject it before the serializer converts it)
        $passthroughValidator = static::createStub(ValidatorInterface::class);
        $passthroughValidator->method('validate')->willReturn(new ConstraintViolationList());

        $this->serializerWithPassthroughValidator = new ParameterBindingFieldSerializer(
            $passthroughValidator,
            $definitionRegistry,
            $resolutionConfigSerializer
        );

        $this->existence = new EntityExistence('content_layout', ['id' => 'test'], true, false, false, []);
        $this->parameters = static::createStub(WriteParameterBag::class);
    }

    #[TestDox('encodes ParameterBinding object to JSON string')]
    public function testEncodeWithParameterBindingObject(): void
    {
        $field = $this->createParameterBindingField();
        $binding = new ParameterBinding('seoUrl', 'seoUrl');
        $kvPair = new KeyValuePair('parameter_binding', $binding, false);

        // Use passthrough validator: the Type('array') constraint would reject the ParameterBinding
        // object before serialization converts it to array — bypassing is the intended encode path
        $result = iterator_to_array(
            $this->serializerWithPassthroughValidator->encode($field, $this->existence, $kvPair, $this->parameters)
        );

        static::assertArrayHasKey('parameter_binding', $result);
        $decoded = json_decode($result['parameter_binding'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('seoUrl', $decoded['placeholder']);
        static::assertArrayNotHasKey('resolution', $decoded);
    }

    #[TestDox('encodes array value as JSON passthrough')]
    public function testEncodeWithArrayPassthrough(): void
    {
        $field = $this->createParameterBindingField();
        $arrayValue = ['placeholder' => 'seoUrl'];
        $kvPair = new KeyValuePair('parameter_binding', $arrayValue, false);

        $result = iterator_to_array($this->serializer->encode($field, $this->existence, $kvPair, $this->parameters));

        static::assertArrayHasKey('parameter_binding', $result);
        static::assertSame(Json::encode($arrayValue), $result['parameter_binding']);
    }

    #[TestDox('encodes array value when field is marked as required')]
    public function testEncodeWithRequiredField(): void
    {
        $field = new ParameterBindingField('parameter_binding', 'parameterBinding');
        $field->addFlags(new Required());
        $field->compile(static::createStub(DefinitionInstanceRegistry::class));

        $arrayValue = ['placeholder' => 'seoUrl'];
        $kvPair = new KeyValuePair('parameter_binding', $arrayValue, false);

        $result = iterator_to_array($this->serializer->encode($field, $this->existence, $kvPair, $this->parameters));

        static::assertArrayHasKey('parameter_binding', $result);
        static::assertIsString($result['parameter_binding']);
    }

    #[TestDox('encodes null value as null')]
    public function testEncodeWithNull(): void
    {
        $field = $this->createParameterBindingField();
        $kvPair = new KeyValuePair('parameter_binding', null, false);

        $result = iterator_to_array($this->serializer->encode($field, $this->existence, $kvPair, $this->parameters));

        static::assertArrayHasKey('parameter_binding', $result);
        static::assertNull($result['parameter_binding']);
    }

    #[TestDox('throws exception when encode receives non-StorageAware field')]
    public function testEncodeThrowsOnNonStorageAwareField(): void
    {
        // TranslatedField extends Field but does NOT implement StorageAware
        $invalidField = new TranslatedField('parameterBinding');
        $kvPair = new KeyValuePair('parameter_binding', null, false);

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldType(StorageAware::class, TranslatedField::class)
        );

        iterator_to_array($this->serializer->encode($invalidField, $this->existence, $kvPair, $this->parameters));
    }

    #[TestDox('decodes JSON string to ParameterBinding object')]
    public function testDecodeWithJsonString(): void
    {
        $field = $this->createParameterBindingField();
        $json = json_encode(['placeholder' => 'seoUrl'], \JSON_THROW_ON_ERROR);

        $result = $this->serializer->decode($field, $json);

        static::assertInstanceOf(ParameterBinding::class, $result);
        static::assertSame('seoUrl', $result->placeholder);
        static::assertNull($result->resolution);
    }

    #[TestDox('decodes array to ParameterBinding object')]
    public function testDecodeWithArray(): void
    {
        $field = $this->createParameterBindingField();
        $arrayValue = ['placeholder' => 'productNumber'];

        $result = $this->serializer->decode($field, $arrayValue);

        static::assertInstanceOf(ParameterBinding::class, $result);
        static::assertSame('productNumber', $result->placeholder);
        static::assertNull($result->resolution);
    }

    #[TestDox('decodes null to null')]
    public function testDecodeWithNull(): void
    {
        $field = $this->createParameterBindingField();

        $result = $this->serializer->decode($field, null);

        static::assertNull($result);
    }

    #[TestDox('throws exception when decode receives non-ParameterBindingField')]
    public function testDecodeThrowsOnWrongFieldType(): void
    {
        $invalidField = new JsonField('parameter_binding', 'parameterBinding');
        $invalidField->compile(static::createStub(DefinitionInstanceRegistry::class));

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldType(ParameterBindingField::class, JsonField::class)
        );

        $this->serializer->decode($invalidField, ['placeholder' => 'seoUrl']);
    }

    #[TestDox('throws exception when decode receives non-array non-string value')]
    public function testDecodeThrowsOnInvalidValueType(): void
    {
        $field = $this->createParameterBindingField();

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('parameter_binding', 'array', 'integer')
        );

        $this->serializer->decode($field, 42);
    }

    #[TestDox('serializes ParameterBinding with placeholder')]
    public function testSerializeParameterBindingWithPlaceholder(): void
    {
        $binding = new ParameterBinding('seoUrl', 'seoUrl');

        $result = $this->serializer->serializeParameterBinding($binding);

        static::assertArrayHasKey('placeholder', $result);
        static::assertSame('seoUrl', $result['placeholder']);
        static::assertArrayNotHasKey('resolution', $result);
    }

    #[TestDox('serializes ParameterBinding with resolution config included')]
    public function testSerializeParameterBindingWithResolution(): void
    {
        $resolution = new ResolutionConfig('product', 'productNumber');
        $binding = new ParameterBinding('seoUrl', 'seoUrl', $resolution);

        $expectedResolutionData = ['entity' => 'product', 'match_field' => 'productNumber'];

        $resolutionConfigSerializer = static::createStub(ResolutionConfigFieldSerializer::class);
        $resolutionConfigSerializer
            ->method('serializeResolutionConfig')
            ->willReturn($expectedResolutionData);

        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $serializer = new ParameterBindingFieldSerializer($validator, $definitionRegistry, $resolutionConfigSerializer);

        $result = $serializer->serializeParameterBinding($binding);

        static::assertArrayHasKey('placeholder', $result);
        static::assertArrayHasKey('resolution', $result);
        static::assertSame('seoUrl', $result['placeholder']);
        static::assertSame($expectedResolutionData, $result['resolution']);
    }

    #[TestDox('serializes ParameterBinding without placeholder or resolution')]
    public function testSerializeParameterBindingWithoutPlaceholderOrResolution(): void
    {
        $binding = new ParameterBinding('seoUrl');

        $result = $this->serializer->serializeParameterBinding($binding);

        static::assertArrayNotHasKey('placeholder', $result);
        static::assertArrayNotHasKey('resolution', $result);
        static::assertSame([], $result);
    }

    #[TestDox('deserializes array data to ParameterBinding without resolution')]
    public function testDeserializeParameterBindingWithoutResolution(): void
    {
        $data = ['placeholder' => 'seoUrl'];

        $result = $this->serializer->deserializeParameterBinding($data);

        static::assertSame('seoUrl', $result->parameterName);
        static::assertSame('seoUrl', $result->placeholder);
        static::assertNull($result->resolution);
    }

    #[TestDox('deserializes array data using explicit parameterName when provided')]
    public function testDeserializeParameterBindingUsesExplicitParameterName(): void
    {
        $data = ['placeholder' => 'seoUrl'];

        $result = $this->serializer->deserializeParameterBinding($data, 'explicitName');

        static::assertSame('explicitName', $result->parameterName);
        static::assertSame('seoUrl', $result->placeholder);
        static::assertNull($result->resolution);
    }

    #[TestDox('deserializes array data to ParameterBinding with resolution when resolution data present')]
    public function testDeserializeParameterBindingWithResolution(): void
    {
        $expectedResolution = new ResolutionConfig('product', 'productNumber');
        $data = [
            'placeholder' => 'seoUrl',
            'resolution' => ['entity' => 'product', 'match_field' => 'productNumber'],
        ];

        $resolutionConfigSerializer = static::createStub(ResolutionConfigFieldSerializer::class);
        $resolutionConfigSerializer
            ->method('deserializeResolutionConfig')
            ->willReturn($expectedResolution);

        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $serializer = new ParameterBindingFieldSerializer($validator, $definitionRegistry, $resolutionConfigSerializer);

        $result = $serializer->deserializeParameterBinding($data);

        static::assertSame('seoUrl', $result->parameterName);
        static::assertSame('seoUrl', $result->placeholder);
        static::assertSame($expectedResolution, $result->resolution);
    }

    #[TestDox('deserializes array data using parameterName when placeholder is absent')]
    public function testDeserializeParameterBindingUsesEmptyStringWhenPlaceholderMissing(): void
    {
        $data = [];

        $result = $this->serializer->deserializeParameterBinding($data);

        static::assertSame('', $result->parameterName);
        static::assertNull($result->placeholder);
        static::assertNull($result->resolution);
    }

    #[TestDox('deserializes array data skipping resolution when resolution value is not an array')]
    public function testDeserializeParameterBindingSkipsNonArrayResolution(): void
    {
        /** @var array{placeholder?: string, resolution?: array{entity: string, match_field: string}} $data */
        $data = ['placeholder' => 'seoUrl', 'resolution' => 'not-an-array']; // @phpstan-ignore-line

        $resolutionConfigSerializer = static::createMock(ResolutionConfigFieldSerializer::class);
        $resolutionConfigSerializer->expects($this->never())->method('deserializeResolutionConfig');

        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $serializer = new ParameterBindingFieldSerializer($validator, $definitionRegistry, $resolutionConfigSerializer);

        $result = $serializer->deserializeParameterBinding($data);

        static::assertSame('seoUrl', $result->placeholder);
        static::assertNull($result->resolution);
    }

    private function createParameterBindingField(): ParameterBindingField
    {
        $field = new ParameterBindingField('parameter_binding', 'parameterBinding');
        $field->compile(static::createStub(DefinitionInstanceRegistry::class));

        return $field;
    }
}
