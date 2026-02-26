<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Adapter\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Adapter\Field\ParameterBindingFieldSerializer;
use Shopware\Core\Content\ContentSystem\Adapter\Field\ParameterBindingsField;
use Shopware\Core\Content\ContentSystem\Adapter\Field\ParameterBindingsFieldSerializer;
use Shopware\Core\Content\ContentSystem\Adapter\ParameterBinding\ParameterBinding;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ParameterBindingsFieldSerializer::class)]
class ParameterBindingsFieldSerializerTest extends TestCase
{
    private ParameterBindingsFieldSerializer $serializer;

    private EntityExistence $existence;

    private WriteParameterBag $parameters;

    protected function setUp(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $bindingSerializer = static::createStub(ParameterBindingFieldSerializer::class);

        $this->serializer = new ParameterBindingsFieldSerializer($validator, $definitionRegistry, $bindingSerializer);

        $this->existence = new EntityExistence('content_layout', ['id' => 'test'], true, false, false, []);
        $this->parameters = static::createStub(WriteParameterBag::class);
    }

    #[TestDox('encodes array of ParameterBinding objects to JSON string')]
    public function testEncodeWithParameterBindingObjects(): void
    {
        $binding = new ParameterBinding('seoUrl', 'seoUrl');
        $serializedBinding = ['placeholder' => 'seoUrl'];

        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $bindingSerializer = static::createStub(ParameterBindingFieldSerializer::class);
        $bindingSerializer
            ->method('serializeParameterBinding')
            ->willReturn($serializedBinding);

        $passthroughValidator = static::createStub(ValidatorInterface::class);
        $passthroughValidator->method('validate')->willReturn(new ConstraintViolationList());
        $serializer = new ParameterBindingsFieldSerializer($passthroughValidator, $definitionRegistry, $bindingSerializer);

        $field = $this->createParameterBindingsField();
        $value = ['seoUrl' => $binding];
        $kvPair = new KeyValuePair('parameter_bindings', $value, false);

        $result = iterator_to_array($serializer->encode($field, $this->existence, $kvPair, $this->parameters));

        static::assertArrayHasKey('parameter_bindings', $result);
        $decoded = json_decode($result['parameter_bindings'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('seoUrl', $decoded);
        static::assertSame('seoUrl', $decoded['seoUrl']['placeholder']);
    }

    #[TestDox('encodes array with mixed ParameterBinding and raw values to JSON string')]
    public function testEncodeWithMixedArrayValues(): void
    {
        $binding = new ParameterBinding('productId', 'productId');
        $serializedBinding = ['placeholder' => 'productId'];

        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $bindingSerializer = static::createStub(ParameterBindingFieldSerializer::class);
        $bindingSerializer
            ->method('serializeParameterBinding')
            ->willReturn($serializedBinding);

        $passthroughValidator = static::createStub(ValidatorInterface::class);
        $passthroughValidator->method('validate')->willReturn(new ConstraintViolationList());
        $serializer = new ParameterBindingsFieldSerializer($passthroughValidator, $definitionRegistry, $bindingSerializer);

        $field = $this->createParameterBindingsField();
        $value = [
            'productId' => $binding,
            'rawKey' => ['placeholder' => 'rawKey'],
        ];
        $kvPair = new KeyValuePair('parameter_bindings', $value, false);

        $result = iterator_to_array($serializer->encode($field, $this->existence, $kvPair, $this->parameters));

        static::assertArrayHasKey('parameter_bindings', $result);
        $decoded = json_decode($result['parameter_bindings'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('productId', $decoded['productId']['placeholder']);
        static::assertSame('rawKey', $decoded['rawKey']['placeholder']);
    }

    #[TestDox('encodes array value when field is marked as required')]
    public function testEncodeWithRequiredField(): void
    {
        $field = new ParameterBindingsField('parameter_bindings', 'parameterBindings');
        $field->addFlags(new Required());
        $field->compile(static::createStub(DefinitionInstanceRegistry::class));

        $arrayValue = ['seoUrl' => ['placeholder' => 'seoUrl']];
        $kvPair = new KeyValuePair('parameter_bindings', $arrayValue, false);

        $result = iterator_to_array($this->serializer->encode($field, $this->existence, $kvPair, $this->parameters));

        static::assertArrayHasKey('parameter_bindings', $result);
        static::assertIsString($result['parameter_bindings']);
    }

    #[TestDox('encodes null value as null')]
    public function testEncodeWithNull(): void
    {
        $field = $this->createParameterBindingsField();
        $kvPair = new KeyValuePair('parameter_bindings', null, false);

        $result = iterator_to_array($this->serializer->encode($field, $this->existence, $kvPair, $this->parameters));

        static::assertArrayHasKey('parameter_bindings', $result);
        static::assertNull($result['parameter_bindings']);
    }

    #[TestDox('throws exception when encode receives non-StorageAware field')]
    public function testEncodeThrowsOnNonStorageAwareField(): void
    {
        // TranslatedField extends Field but does NOT implement StorageAware
        $invalidField = new TranslatedField('parameterBindings');
        $kvPair = new KeyValuePair('parameter_bindings', null, false);

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldType(StorageAware::class, TranslatedField::class)
        );

        iterator_to_array($this->serializer->encode($invalidField, $this->existence, $kvPair, $this->parameters));
    }

    #[TestDox('decodes JSON string to array of ParameterBinding objects')]
    public function testDecodeWithJsonString(): void
    {
        $binding = new ParameterBinding('seoUrl', 'seoUrl');

        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $bindingSerializer = static::createStub(ParameterBindingFieldSerializer::class);
        $bindingSerializer
            ->method('deserializeParameterBinding')
            ->willReturn($binding);

        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $serializer = new ParameterBindingsFieldSerializer($validator, $definitionRegistry, $bindingSerializer);

        $field = $this->createParameterBindingsField();
        $json = json_encode(['seoUrl' => ['placeholder' => 'seoUrl']], \JSON_THROW_ON_ERROR);

        $result = $serializer->decode($field, $json);

        static::assertIsArray($result);
        static::assertArrayHasKey('seoUrl', $result);
        static::assertSame($binding, $result['seoUrl']);
    }

    #[TestDox('decodes array to array of ParameterBinding objects')]
    public function testDecodeWithArray(): void
    {
        $binding1 = new ParameterBinding('seoUrl', 'seoUrl');
        $binding2 = new ParameterBinding('productId', 'productId');

        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $bindingSerializer = static::createStub(ParameterBindingFieldSerializer::class);
        $bindingSerializer
            ->method('deserializeParameterBinding')
            ->willReturnMap([
                [['placeholder' => 'seoUrl'], 'seoUrl', $binding1],
                [['placeholder' => 'productId'], 'productId', $binding2],
            ]);

        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $serializer = new ParameterBindingsFieldSerializer($validator, $definitionRegistry, $bindingSerializer);

        $field = $this->createParameterBindingsField();
        $arrayValue = [
            'seoUrl' => ['placeholder' => 'seoUrl'],
            'productId' => ['placeholder' => 'productId'],
        ];

        $result = $serializer->decode($field, $arrayValue);

        static::assertIsArray($result);
        static::assertCount(2, $result);
        static::assertSame($binding1, $result['seoUrl']);
        static::assertSame($binding2, $result['productId']);
    }

    #[TestDox('decodes null to null')]
    public function testDecodeWithNull(): void
    {
        $field = $this->createParameterBindingsField();

        $result = $this->serializer->decode($field, null);

        static::assertNull($result);
    }

    #[TestDox('throws exception when decode receives non-ParameterBindingsField')]
    public function testDecodeThrowsOnWrongFieldType(): void
    {
        $invalidField = new JsonField('parameter_bindings', 'parameterBindings');
        $invalidField->compile(static::createStub(DefinitionInstanceRegistry::class));

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldType(ParameterBindingsField::class, JsonField::class)
        );

        $this->serializer->decode($invalidField, ['seoUrl' => ['placeholder' => 'seoUrl']]);
    }

    #[TestDox('throws exception when decode receives non-array non-string value')]
    public function testDecodeThrowsOnInvalidValueType(): void
    {
        $field = $this->createParameterBindingsField();

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('parameter_bindings', 'array', 'integer')
        );

        $this->serializer->decode($field, 42);
    }

    private function createParameterBindingsField(): ParameterBindingsField
    {
        $field = new ParameterBindingsField('parameter_bindings', 'parameterBindings');
        $field->compile(static::createStub(DefinitionInstanceRegistry::class));

        return $field;
    }
}
