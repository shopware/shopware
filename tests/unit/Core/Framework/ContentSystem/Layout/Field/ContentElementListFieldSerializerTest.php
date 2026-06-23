<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ContentElementFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ContentElementListField;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ContentElementListFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutDefaultSeeder;
use Shopware\Core\Framework\ContentSystem\Layout\Type\PrimitiveDefaultProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;
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
    #[TestDox('normalize seeds the type primitive defaults into a raw layout payload before encode')]
    public function testNormalizeSeedsPrimitiveDefaultsIntoRawPayload(): void
    {
        $field = $this->createContentElementListField();
        $data = ['elements' => [['id' => 'el', 'component' => 'Sw:Block', 'properties' => []]]];

        $result = $this->serializerWithRealSeeder()->normalize($field, $data, $this->parameters());

        static::assertSame([['id' => 'el', 'component' => 'Sw:Block', 'properties' => ['headline' => 'Hi']]], $result['elements']);
    }

    #[TestDox('normalize wraps a single ContentElement value into a list')]
    public function testNormalizeWrapsSingleContentElementIntoList(): void
    {
        $field = $this->createContentElementListField();
        $element = new ContentElement('el', 'Sw:Block');

        $result = $this->serializerWithRealSeeder()->normalize($field, ['elements' => $element], $this->parameters());

        static::assertSame([$element], $result['elements']);
    }

    #[TestDox('normalize seeds the type primitive defaults onto a wrapped ContentElement')]
    public function testNormalizeSeedsPrimitiveDefaultsOnWrappedContentElement(): void
    {
        $field = $this->createContentElementListField();
        $element = new ContentElement('el', 'Sw:Block');

        $this->serializerWithRealSeeder()->normalize($field, ['elements' => $element], $this->parameters());

        static::assertSame('Hi', $element->getProperty('headline'));
    }

    #[TestDox('normalize leaves a non-list layout value untouched')]
    public function testNormalizeLeavesNonListValueUntouched(): void
    {
        $field = $this->createContentElementListField();

        $result = $this->serializerWithRealSeeder()->normalize($field, ['elements' => 'not-a-list'], $this->parameters());

        static::assertSame(['elements' => 'not-a-list'], $result);
    }

    #[TestDox('encodes single ContentElement wrapped to array as JSON string')]
    public function testEncodeWithSingleContentElementWrapsAndEncodesAsJson(): void
    {
        $field = $this->createContentElementListField();
        $element = new ContentElement('elem-1', 'text');

        $elementSerializer = $this->elementSerializer();
        $elementSerializer
            ->method('serializeContentElement')
            ->willReturn(['id' => 'elem-1', 'component' => 'text', 'properties' => []]);

        $kvPair = new KeyValuePair('elements', $element, false);

        $result = iterator_to_array(
            $this->serializerWithPassthroughValidator($elementSerializer)->encode($field, $this->existence(), $kvPair, $this->parameters())
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

        $elementSerializer = $this->elementSerializer();
        $elementSerializer
            ->method('serializeContentElement')
            ->willReturnOnConsecutiveCalls(
                ['id' => 'elem-1', 'component' => 'text', 'properties' => []],
                ['id' => 'elem-2', 'component' => 'image', 'properties' => []]
            );

        $kvPair = new KeyValuePair('elements', [$element1, $element2], false);

        $result = iterator_to_array(
            $this->serializerWithPassthroughValidator($elementSerializer)->encode($field, $this->existence(), $kvPair, $this->parameters())
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
            $this->serializer()->encode($field, $this->existence(), $kvPair, $this->parameters())
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
            $this->serializer()->encode($field, $this->existence(), $kvPair, $this->parameters())
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
            $this->serializer()->encode($invalidField, $this->existence(), $kvPair, $this->parameters())
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
            $this->serializerWithPassthroughValidator()->encode($field, $this->existence(), $kvPair, $this->parameters())
        );
    }

    #[TestDox('decodes JSON string to ContentElement array')]
    public function testDecodeWithJsonStringReturnsContentElementArray(): void
    {
        $field = $this->createContentElementListField();
        $elementSerializer = $this->elementSerializer();
        $elementSerializer->method('decodeElement')->willReturn(new ContentElement('elem-1', 'text'));

        $json = json_encode([
            ['id' => 'elem-1', 'component' => 'text', 'properties' => []],
            ['id' => 'elem-2', 'component' => 'image', 'properties' => []],
        ], \JSON_THROW_ON_ERROR);

        $result = $this->serializer($elementSerializer)->decode($field, $json);

        static::assertIsArray($result);
        static::assertCount(2, $result);
        static::assertContainsOnlyInstancesOf(ContentElement::class, $result);
    }

    #[TestDox('decodes array directly to ContentElement array')]
    public function testDecodeWithArrayReturnsContentElementArray(): void
    {
        $field = $this->createContentElementListField();
        $elementSerializer = $this->elementSerializer();
        $elementSerializer->method('decodeElement')->willReturn(new ContentElement('elem-1', 'text'));

        $data = [
            ['id' => 'elem-1', 'component' => 'text', 'properties' => []],
        ];

        $result = $this->serializer($elementSerializer)->decode($field, $data);

        static::assertIsArray($result);
        static::assertCount(1, $result);
        static::assertInstanceOf(ContentElement::class, $result[0]);
    }

    #[TestDox('decodes null to null')]
    public function testDecodeWithNullReturnsNull(): void
    {
        $field = $this->createContentElementListField();

        $result = $this->serializer()->decode($field, null);

        static::assertNull($result);
    }

    #[TestDox('decodes empty array to empty array')]
    public function testDecodeWithEmptyArrayReturnsEmptyArray(): void
    {
        $field = $this->createContentElementListField();

        $result = $this->serializer()->decode($field, []);

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

        $this->serializer()->decode($invalidField, []);
    }

    #[TestDox('throws exception when decode receives non-string non-array non-null value')]
    public function testDecodeThrowsOnInvalidValueType(): void
    {
        $field = $this->createContentElementListField();

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('elements', 'array', 'integer')
        );

        $this->serializer()->decode($field, 42);
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

        $this->serializer()->decode($field, ['key' => ['id' => 'elem-1', 'component' => 'text', 'properties' => []]]);
    }

    #[TestDox('throws exception when decode receives array with non-array element')]
    public function testDecodeThrowsOnNonArrayElement(): void
    {
        $field = $this->createContentElementListField();

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('elements[0]', 'array', 'string')
        );

        $this->serializer()->decode($field, ['not-an-array']);
    }

    #[TestDox('returns Type and All constraints without Required flag')]
    public function testBuildConstraintsWithoutRequiredFlagReturnsExpectedStructure(): void
    {
        $field = $this->createContentElementListField();

        $constraints = $this->serializer()->buildConstraints($field);

        static::assertCount(2, $constraints);
        static::assertInstanceOf(Type::class, $constraints[0]);
        static::assertSame('array', $constraints[0]->type);
        static::assertInstanceOf(All::class, $constraints[1]);
    }

    #[TestDox('appends NotBlank constraint when field has Required flag')]
    public function testBuildConstraintsWithRequiredFlagAddsNotBlank(): void
    {
        $field = $this->createContentElementListFieldWithRequired();

        $constraints = $this->serializer()->buildConstraints($field);

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

        $this->serializer()->buildConstraints($invalidField);
    }

    private function serializer(?ContentElementFieldSerializer $elementSerializer = null): ContentElementListFieldSerializer
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        return new ContentElementListFieldSerializer(
            $validator,
            static::createStub(DefinitionInstanceRegistry::class),
            $elementSerializer ?? $this->elementSerializer(),
            static::createStub(LayoutDefaultSeeder::class),
        );
    }

    private function serializerWithPassthroughValidator(?ContentElementFieldSerializer $elementSerializer = null): ContentElementListFieldSerializer
    {
        // Passthrough validator never raises violations — used when encoding ContentElement objects
        // (the Type('array') constraint would otherwise reject them before serializer conversion)
        $passthroughValidator = static::createStub(ValidatorInterface::class);
        $passthroughValidator->method('validate')->willReturn(new ConstraintViolationList());

        return new ContentElementListFieldSerializer(
            $passthroughValidator,
            static::createStub(DefinitionInstanceRegistry::class),
            $elementSerializer ?? $this->elementSerializer(),
            static::createStub(LayoutDefaultSeeder::class),
        );
    }

    private function serializerWithRealSeeder(): ContentElementListFieldSerializer
    {
        $specs = ['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create('Sw:Block')->primitive('headline', 'string', default: 'Hi')->build()];

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => isset($specs[$name]));
        $registry->method('get')->willReturnCallback(static fn (string $name): ContentSystemElementTypeSpecification => $specs[$name]);

        return new ContentElementListFieldSerializer(
            static::createStub(ValidatorInterface::class),
            static::createStub(DefinitionInstanceRegistry::class),
            $this->elementSerializer(),
            new LayoutDefaultSeeder($registry, new PrimitiveDefaultProvider()),
        );
    }

    /**
     * buildConstraints must return at least one valid constraint so that new All([...]) does not throw.
     *
     * @return ContentElementFieldSerializer&Stub
     */
    private function elementSerializer(): ContentElementFieldSerializer
    {
        $elementSerializer = static::createStub(ContentElementFieldSerializer::class);
        $elementSerializer->method('buildConstraints')->willReturn([new Type('array')]);

        return $elementSerializer;
    }

    private function existence(): EntityExistence
    {
        return new EntityExistence('content_layout', ['id' => 'test'], true, false, false, []);
    }

    private function parameters(): WriteParameterBag
    {
        return static::createStub(WriteParameterBag::class);
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
