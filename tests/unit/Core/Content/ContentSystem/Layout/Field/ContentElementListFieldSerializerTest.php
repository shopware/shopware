<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Layout\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Field\ContentElementFieldSerializer;
use Shopware\Core\Content\ContentSystem\Layout\Field\ContentElementListField;
use Shopware\Core\Content\ContentSystem\Layout\Field\ContentElementListFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ContentElementListFieldSerializer::class)]
class ContentElementListFieldSerializerTest extends TestCase
{
    private ContentElementListFieldSerializer $serializer;

    private ContentElementListFieldSerializer $serializerWithPassthroughValidator;

    /**
     * @var ContentElementFieldSerializer&Stub
     */
    private ContentElementFieldSerializer $elementSerializer;

    private EntityExistence $existence;

    private WriteParameterBag $parameters;

    protected function setUp(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);

        $this->elementSerializer = static::createStub(ContentElementFieldSerializer::class);
        // buildConstraints must return at least one valid constraint so that new All([...]) does not throw
        $this->elementSerializer->method('buildConstraints')->willReturn([new Type('array')]);

        $this->serializer = new ContentElementListFieldSerializer($validator, $definitionRegistry, $this->elementSerializer);

        // Passthrough validator never raises violations — used when encoding ContentElement objects
        // (the Type('array') constraint would otherwise reject them before serializer conversion)
        $passthroughValidator = static::createStub(ValidatorInterface::class);
        $passthroughValidator->method('validate')->willReturn(new ConstraintViolationList());

        $this->serializerWithPassthroughValidator = new ContentElementListFieldSerializer(
            $passthroughValidator,
            $definitionRegistry,
            $this->elementSerializer
        );

        $this->existence = new EntityExistence('content_layout', ['id' => 'test'], true, false, false, []);
        $this->parameters = static::createStub(WriteParameterBag::class);
    }

    #[TestDox('encodes single ContentElement wrapped to array as JSON string')]
    public function testEncodeWithSingleContentElementWrapsAndEncodesAsJson(): void
    {
        $field = $this->createContentElementListField();
        $element = new ContentElement('elem-1', 'text');

        $this->elementSerializer
            ->method('serializeContentElement')
            ->willReturn(['id' => 'elem-1', 'component' => 'text', 'properties' => []]);

        $kvPair = new KeyValuePair('elements', $element, false);

        $result = iterator_to_array(
            $this->serializerWithPassthroughValidator->encode($field, $this->existence, $kvPair, $this->parameters)
        );

        static::assertArrayHasKey('elements', $result);
        static::assertIsString($result['elements']);

        $decoded = json_decode($result['elements'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decoded);
        static::assertCount(1, $decoded);
        static::assertSame('elem-1', $decoded[0]['id']);
        static::assertSame('text', $decoded[0]['component']);
    }

    #[TestDox('encodes ContentElement array to JSON string')]
    public function testEncodeWithContentElementArrayYieldsJson(): void
    {
        $field = $this->createContentElementListField();
        $element1 = new ContentElement('elem-1', 'text');
        $element2 = new ContentElement('elem-2', 'image');

        $this->elementSerializer
            ->method('serializeContentElement')
            ->willReturnOnConsecutiveCalls(
                ['id' => 'elem-1', 'component' => 'text', 'properties' => []],
                ['id' => 'elem-2', 'component' => 'image', 'properties' => []]
            );

        $kvPair = new KeyValuePair('elements', [$element1, $element2], false);

        $result = iterator_to_array(
            $this->serializerWithPassthroughValidator->encode($field, $this->existence, $kvPair, $this->parameters)
        );

        static::assertArrayHasKey('elements', $result);
        static::assertIsString($result['elements']);

        $decoded = json_decode($result['elements'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decoded);
        static::assertCount(2, $decoded);
        static::assertSame('elem-1', $decoded[0]['id']);
        static::assertSame('elem-2', $decoded[1]['id']);
    }

    #[TestDox('encodes plain array passthrough to JSON string')]
    public function testEncodeWithPlainArrayPassthroughYieldsJson(): void
    {
        $field = $this->createContentElementListField();
        $arrayValue = [
            ['id' => 'elem-1', 'component' => 'text', 'properties' => []],
        ];

        $kvPair = new KeyValuePair('elements', $arrayValue, false);

        $result = iterator_to_array(
            $this->serializer->encode($field, $this->existence, $kvPair, $this->parameters)
        );

        static::assertArrayHasKey('elements', $result);
        static::assertIsString($result['elements']);

        $decoded = json_decode($result['elements'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame($arrayValue, $decoded);
    }

    #[TestDox('encodes null value as null')]
    public function testEncodeWithNullYieldsNull(): void
    {
        $field = $this->createContentElementListField();
        $kvPair = new KeyValuePair('elements', null, false);

        $result = iterator_to_array(
            $this->serializer->encode($field, $this->existence, $kvPair, $this->parameters)
        );

        static::assertArrayHasKey('elements', $result);
        static::assertNull($result['elements']);
    }

    #[TestDox('throws exception when encode receives non-StorageAware field')]
    public function testEncodeThrowsOnNonStorageAwareField(): void
    {
        // TranslatedField does not implement StorageAware
        $invalidField = new TranslatedField('elements');
        $invalidField->compile(static::createStub(DefinitionInstanceRegistry::class));

        $kvPair = new KeyValuePair('elements', null, false);

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldType(StorageAware::class, TranslatedField::class)
        );

        iterator_to_array(
            $this->serializer->encode($invalidField, $this->existence, $kvPair, $this->parameters)
        );
    }

    #[TestDox('throws exception when encode receives non-array non-null non-ContentElement value')]
    public function testEncodeThrowsOnInvalidValueType(): void
    {
        $field = $this->createContentElementListField();
        $kvPair = new KeyValuePair('elements', 'invalid-string', false);

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('elements', 'array', 'string')
        );

        iterator_to_array(
            $this->serializerWithPassthroughValidator->encode($field, $this->existence, $kvPair, $this->parameters)
        );
    }

    #[TestDox('decodes JSON string to ContentElement array')]
    public function testDecodeWithJsonStringReturnsContentElementArray(): void
    {
        $field = $this->createContentElementListField();
        $decodedElement = new ContentElement('elem-1', 'text');
        $this->elementSerializer->method('decodeElement')->willReturn($decodedElement);

        $json = json_encode([
            ['id' => 'elem-1', 'component' => 'text', 'properties' => []],
            ['id' => 'elem-2', 'component' => 'image', 'properties' => []],
        ], \JSON_THROW_ON_ERROR);

        $result = $this->serializer->decode($field, $json);

        static::assertIsArray($result);
        static::assertCount(2, $result);
        static::assertContainsOnlyInstancesOf(ContentElement::class, $result);
    }

    #[TestDox('decodes array directly to ContentElement array')]
    public function testDecodeWithArrayReturnsContentElementArray(): void
    {
        $field = $this->createContentElementListField();
        $decodedElement = new ContentElement('elem-1', 'text');
        $this->elementSerializer->method('decodeElement')->willReturn($decodedElement);

        $data = [
            ['id' => 'elem-1', 'component' => 'text', 'properties' => []],
        ];

        $result = $this->serializer->decode($field, $data);

        static::assertIsArray($result);
        static::assertCount(1, $result);
        static::assertInstanceOf(ContentElement::class, $result[0]);
    }

    #[TestDox('decodes null to null')]
    public function testDecodeWithNullReturnsNull(): void
    {
        $field = $this->createContentElementListField();

        $result = $this->serializer->decode($field, null);

        static::assertNull($result);
    }

    #[TestDox('decodes empty array to empty array')]
    public function testDecodeWithEmptyArrayReturnsEmptyArray(): void
    {
        $field = $this->createContentElementListField();

        $result = $this->serializer->decode($field, []);

        static::assertIsArray($result);
        static::assertSame([], $result);
    }

    #[TestDox('throws exception when decode receives wrong field type')]
    public function testDecodeThrowsOnNonContentElementListField(): void
    {
        $invalidField = new JsonField('elements', 'elements');
        $invalidField->compile(static::createStub(DefinitionInstanceRegistry::class));

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldType(ContentElementListField::class, JsonField::class)
        );

        $this->serializer->decode($invalidField, []);
    }

    #[TestDox('throws exception when decode receives non-string non-array non-null value')]
    public function testDecodeThrowsOnInvalidValueType(): void
    {
        $field = $this->createContentElementListField();

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('elements', 'array', 'integer')
        );

        $this->serializer->decode($field, 42);
    }

    #[TestDox('throws exception when decode receives associative array instead of indexed array')]
    public function testDecodeThrowsOnAssociativeArray(): void
    {
        $field = $this->createContentElementListField();

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType(
                'elements',
                'indexed array of elements',
                'associative array'
            )
        );

        $this->serializer->decode($field, ['key' => ['id' => 'elem-1', 'component' => 'text', 'properties' => []]]);
    }

    #[TestDox('throws exception when decode receives array with non-array element')]
    public function testDecodeThrowsOnNonArrayElement(): void
    {
        $field = $this->createContentElementListField();

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('elements[0]', 'array', 'string')
        );

        $this->serializer->decode($field, ['not-an-array']);
    }

    #[TestDox('returns Type and All constraints without Required flag')]
    public function testBuildConstraintsWithoutRequiredFlagReturnsExpectedStructure(): void
    {
        $field = $this->createContentElementListField();

        $constraints = $this->serializer->buildConstraints($field);

        static::assertCount(2, $constraints);
        static::assertInstanceOf(Type::class, $constraints[0]);
        static::assertSame('array', $constraints[0]->type);
        static::assertInstanceOf(All::class, $constraints[1]);
    }

    #[TestDox('appends NotBlank constraint when field has Required flag')]
    public function testBuildConstraintsWithRequiredFlagAddsNotBlank(): void
    {
        $field = $this->createContentElementListFieldWithRequired();

        $constraints = $this->serializer->buildConstraints($field);

        static::assertCount(3, $constraints);
        static::assertInstanceOf(Type::class, $constraints[0]);
        static::assertInstanceOf(All::class, $constraints[1]);
        static::assertInstanceOf(NotBlank::class, $constraints[2]);
    }

    #[TestDox('throws exception when buildConstraints receives wrong field type')]
    public function testBuildConstraintsThrowsOnNonContentElementListField(): void
    {
        $invalidField = new JsonField('elements', 'elements');
        $invalidField->compile(static::createStub(DefinitionInstanceRegistry::class));

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldType(ContentElementListField::class, JsonField::class)
        );

        $this->serializer->buildConstraints($invalidField);
    }

    private function createContentElementListField(): ContentElementListField
    {
        $field = new ContentElementListField('elements', 'elements');
        $field->compile(static::createStub(DefinitionInstanceRegistry::class));

        return $field;
    }

    private function createContentElementListFieldWithRequired(): ContentElementListField
    {
        $field = new ContentElementListField('elements', 'elements');
        $field->addFlags(new Required());
        $field->compile(static::createStub(DefinitionInstanceRegistry::class));

        return $field;
    }
}
