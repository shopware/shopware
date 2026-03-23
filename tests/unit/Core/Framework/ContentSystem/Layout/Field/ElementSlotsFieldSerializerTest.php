<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ContentElementFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ElementSlotsField;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ElementSlotsFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ElementSlotsFieldSerializer::class)]
class ElementSlotsFieldSerializerTest extends TestCase
{
    private ElementSlotsFieldSerializer $serializer;

    private ElementSlotsFieldSerializer $serializerWithPassthroughValidator;

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

        $this->serializer = new ElementSlotsFieldSerializer($validator, $definitionRegistry, $this->elementSerializer);

        // Passthrough validator never raises violations — used when encoding SlotContent objects
        // (the Type('array') constraint would otherwise reject them before serializer conversion)
        $passthroughValidator = static::createStub(ValidatorInterface::class);
        $passthroughValidator->method('validate')->willReturn(new ConstraintViolationList());

        $this->serializerWithPassthroughValidator = new ElementSlotsFieldSerializer(
            $passthroughValidator,
            $definitionRegistry,
            $this->elementSerializer
        );

        $this->existence = new EntityExistence('content_layout', ['id' => 'test'], true, false, false, []);
        $this->parameters = static::createStub(WriteParameterBag::class);
    }

    #[TestDox('encodes SlotContent array to JSON string')]
    public function testEncodeWithSlotContentArrayYieldsJson(): void
    {
        $field = $this->createElementSlotsField();

        $element = new ContentElement('elem-1', 'text');
        $slotContent = new SlotContent([$element]);

        $this->elementSerializer
            ->method('serializeContentElement')
            ->willReturn(['id' => 'elem-1', 'component' => 'text', 'properties' => []]);

        $kvPair = new KeyValuePair('slots', ['default' => $slotContent], false);

        $result = iterator_to_array(
            $this->serializerWithPassthroughValidator->encode($field, $this->existence, $kvPair, $this->parameters)
        );

        static::assertArrayHasKey('slots', $result);
        static::assertIsString($result['slots']);

        $decoded = json_decode($result['slots'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decoded);
        static::assertArrayHasKey('default', $decoded);
        static::assertIsArray($decoded['default']);
        static::assertCount(1, $decoded['default']);
        static::assertSame('elem-1', $decoded['default'][0]['id']);
        static::assertSame('text', $decoded['default'][0]['component']);
    }

    #[TestDox('encodes null value as null')]
    public function testEncodeWithNullYieldsNull(): void
    {
        $field = $this->createElementSlotsField();
        $kvPair = new KeyValuePair('slots', null, false);

        $result = iterator_to_array(
            $this->serializer->encode($field, $this->existence, $kvPair, $this->parameters)
        );

        static::assertArrayHasKey('slots', $result);
        static::assertNull($result['slots']);
    }

    #[TestDox('encodes empty slots array to JSON empty object')]
    public function testEncodeWithEmptySlotArrayYieldsEmptyJsonObject(): void
    {
        $field = $this->createElementSlotsField();
        $kvPair = new KeyValuePair('slots', [], false);

        $result = iterator_to_array(
            $this->serializer->encode($field, $this->existence, $kvPair, $this->parameters)
        );

        static::assertArrayHasKey('slots', $result);
        static::assertIsString($result['slots']);

        $decoded = json_decode($result['slots'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame([], $decoded);
    }

    #[TestDox('throws exception when encode receives wrong field type')]
    public function testEncodeThrowsOnNonElementSlotsField(): void
    {
        $invalidField = new JsonField('slots', 'slots');
        $invalidField->compile(static::createStub(DefinitionInstanceRegistry::class));

        $kvPair = new KeyValuePair('slots', null, false);

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldType(ElementSlotsField::class, JsonField::class)
        );

        iterator_to_array(
            $this->serializer->encode($invalidField, $this->existence, $kvPair, $this->parameters)
        );
    }

    #[TestDox('decodes JSON string to SlotContent array')]
    public function testDecodeWithJsonStringReturnsSlotContentArray(): void
    {
        $field = $this->createElementSlotsField();

        $decodedElement = new ContentElement('elem-1', 'text');
        $this->elementSerializer->method('decodeElement')->willReturn($decodedElement);

        $json = json_encode([
            'default' => [
                ['id' => 'elem-1', 'component' => 'text', 'properties' => []],
            ],
            'sidebar' => [
                ['id' => 'elem-2', 'component' => 'image', 'properties' => []],
            ],
        ], \JSON_THROW_ON_ERROR);

        $result = $this->serializer->decode($field, $json);

        static::assertIsArray($result);
        static::assertCount(2, $result);
        static::assertArrayHasKey('default', $result);
        static::assertArrayHasKey('sidebar', $result);
        static::assertInstanceOf(SlotContent::class, $result['default']);
        static::assertInstanceOf(SlotContent::class, $result['sidebar']);
        static::assertCount(1, $result['default']);
        static::assertCount(1, $result['sidebar']);
    }

    #[TestDox('decodes array directly to SlotContent array')]
    public function testDecodeWithArrayReturnsSlotContentArray(): void
    {
        $field = $this->createElementSlotsField();

        $decodedElement = new ContentElement('elem-1', 'text');
        $this->elementSerializer->method('decodeElement')->willReturn($decodedElement);

        $data = [
            'main' => [
                ['id' => 'elem-1', 'component' => 'text', 'properties' => []],
            ],
        ];

        $result = $this->serializer->decode($field, $data);

        static::assertIsArray($result);
        static::assertArrayHasKey('main', $result);
        static::assertInstanceOf(SlotContent::class, $result['main']);
    }

    #[TestDox('decodes empty slots to empty array')]
    public function testDecodeWithEmptyArrayReturnsEmptyArray(): void
    {
        $field = $this->createElementSlotsField();

        $result = $this->serializer->decode($field, []);

        static::assertIsArray($result);
        static::assertSame([], $result);
    }

    #[TestDox('decodes null to null')]
    public function testDecodeWithNullReturnsNull(): void
    {
        $field = $this->createElementSlotsField();

        $result = $this->serializer->decode($field, null);

        static::assertNull($result);
    }

    #[TestDox('decodes slots skipping non-array elements within a slot list')]
    public function testDecodeSkipsNonArrayElementsWithinSlotList(): void
    {
        $field = $this->createElementSlotsField();

        $decodedElement = new ContentElement('elem-1', 'text');
        $this->elementSerializer->method('decodeElement')->willReturn($decodedElement);

        // Slot list with a mix of valid array element and non-array string
        $result = $this->serializer->decode($field, [
            'default' => ['not-an-array', ['id' => 'elem-1', 'component' => 'text', 'properties' => []]],
        ]);

        static::assertIsArray($result);
        static::assertArrayHasKey('default', $result);
        // Only the valid array element is decoded; the string is skipped
        static::assertCount(1, $result['default']);
    }

    #[TestDox('throws exception when decode receives wrong field type')]
    public function testDecodeThrowsOnNonElementSlotsField(): void
    {
        $invalidField = new JsonField('slots', 'slots');
        $invalidField->compile(static::createStub(DefinitionInstanceRegistry::class));

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldType(ElementSlotsField::class, JsonField::class)
        );

        $this->serializer->decode($invalidField, ['some' => 'data']);
    }

    #[TestDox('throws exception when decode receives non-string non-array non-null value')]
    public function testDecodeThrowsOnInvalidValueType(): void
    {
        $field = $this->createElementSlotsField();

        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('slots', 'array', 'integer')
        );

        $this->serializer->decode($field, 42);
    }

    #[TestDox('serializes slots map with SlotContent to nested array')]
    public function testSerializeSlotsWithSlotContentReturnsNestedArray(): void
    {
        $element1 = new ContentElement('elem-1', 'text');
        $element2 = new ContentElement('elem-2', 'image');
        $slotDefault = new SlotContent([$element1, $element2]);
        $slotSidebar = new SlotContent([]);

        $this->elementSerializer
            ->method('serializeContentElement')
            ->willReturnOnConsecutiveCalls(
                ['id' => 'elem-1', 'component' => 'text', 'properties' => []],
                ['id' => 'elem-2', 'component' => 'image', 'properties' => []]
            );

        $result = $this->serializer->serializeSlots([
            'default' => $slotDefault,
            'sidebar' => $slotSidebar,
        ]);

        static::assertCount(2, $result);
        static::assertArrayHasKey('default', $result);
        static::assertArrayHasKey('sidebar', $result);
        static::assertCount(2, $result['default']);
        static::assertSame('elem-1', $result['default'][0]['id']);
        static::assertSame('text', $result['default'][0]['component']);
        static::assertCount(0, $result['sidebar']);
    }

    #[TestDox('throws exception when serializeSlots receives non-SlotContent value')]
    public function testSerializeSlotsThrowsOnNonSlotContentValue(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType(
                'slots[broken]',
                SlotContent::class,
                'string'
            )
        );

        $this->serializer->serializeSlots(['broken' => 'not-a-slot-content']); // @phpstan-ignore argument.type (intentional wrong type for test)
    }

    #[TestDox('passes validation when slot elements satisfy constraints')]
    public function testBuildConstraintsValidatesElementDataUsingElementSerializer(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);

        $elementSerializer = static::createStub(ContentElementFieldSerializer::class);
        $elementSerializer->method('buildConstraints')->willReturn([new Type('array')]);

        $serializer = new ElementSlotsFieldSerializer($validator, $definitionRegistry, $elementSerializer);
        $field = $this->createElementSlotsField();
        $constraints = $serializer->buildConstraints($field);

        // Slots map: array<string, list<ContentElementData>> — All constraint iterates over slot values
        $violations = $validator->validate(
            ['default' => [['id' => 'elem1', 'component' => 'text', 'properties' => []]]],
            $constraints
        );

        static::assertCount(0, $violations);
    }

    #[TestDox('returns two-level constraints for slot and element validation without Required flag')]
    public function testBuildConstraintsWithoutRequiredFlagReturnsExpectedStructure(): void
    {
        $field = $this->createElementSlotsField();

        $constraints = $this->serializer->buildConstraints($field);

        static::assertCount(2, $constraints);
        static::assertInstanceOf(Type::class, $constraints[0]);
        static::assertSame('array', $constraints[0]->type);
        static::assertInstanceOf(All::class, $constraints[1]);

        $allConstraint = $constraints[1];
        static::assertIsArray($allConstraint->constraints);
        static::assertCount(2, $allConstraint->constraints);
        static::assertInstanceOf(Type::class, $allConstraint->constraints[0]);
        static::assertInstanceOf(Callback::class, $allConstraint->constraints[1]);
    }

    #[TestDox('adds mandatory field constraint when field has Required flag')]
    public function testBuildConstraintsWithRequiredFlagAddsNotBlank(): void
    {
        $field = $this->createElementSlotsFieldWithRequired();

        $constraints = $this->serializer->buildConstraints($field);

        static::assertCount(3, $constraints);
        static::assertInstanceOf(Type::class, $constraints[0]);
        static::assertInstanceOf(All::class, $constraints[1]);
        static::assertInstanceOf(NotBlank::class, $constraints[2]);
    }

    #[TestDox('skips slot validation when slot value is not an array')]
    public function testSkipsElementValidationWhenSlotValueIsNotArray(): void
    {
        $field = $this->createElementSlotsField();
        $constraints = $this->serializer->buildConstraints($field);

        // The inner Type('array') constraint produces exactly 1 violation for the string slot value.
        // validateSlotElements returns early without adding further violations.
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $violations = $validator->validate(['default' => 'not-an-array'], $constraints);

        static::assertCount(1, $violations);
    }

    #[TestDox('reports validation violations when element data is invalid')]
    public function testReportsViolationsWhenSlotElementDataIsInvalid(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);

        // Use a constraint that will fail to simulate invalid element data
        $elementSerializer = static::createStub(ContentElementFieldSerializer::class);
        $elementSerializer->method('buildConstraints')->willReturn([new NotBlank()]);

        $serializer = new ElementSlotsFieldSerializer($validator, $definitionRegistry, $elementSerializer);
        $field = $this->createElementSlotsField();
        $constraints = $serializer->buildConstraints($field);

        // Slot with an empty element (NotBlank fails on empty arrays)
        $violations = $validator->validate(
            ['default' => [[]]],
            $constraints
        );

        static::assertCount(1, $violations);
    }

    private function createElementSlotsField(): ElementSlotsField
    {
        $field = new ElementSlotsField('slots', 'slots');
        $field->compile(static::createStub(DefinitionInstanceRegistry::class));

        return $field;
    }

    private function createElementSlotsFieldWithRequired(): ElementSlotsField
    {
        $field = new ElementSlotsField('slots', 'slots');
        $field->addFlags(new Required());
        $field->compile(static::createStub(DefinitionInstanceRegistry::class));

        return $field;
    }
}
