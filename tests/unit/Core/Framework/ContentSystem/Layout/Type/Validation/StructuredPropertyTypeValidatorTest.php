<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\PropertySpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Validation\StructuredPropertyType;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Validation\StructuredPropertyTypeValidator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StructuredPropertyTypeValidator::class)]
class StructuredPropertyTypeValidatorTest extends TestCase
{
    #[DataProvider('acceptsValidSpecificationProvider')]
    #[TestDox('accepts valid structured property specification without violations')]
    public function testAcceptsValidPropertySpecification(PropertySpecificationDto $dto): void
    {
        static::assertCount(0, $this->validate($dto));
    }

    /**
     * @return iterable<string, array{PropertySpecificationDto}>
     */
    public static function acceptsValidSpecificationProvider(): iterable
    {
        yield 'single primitive type' => [
            new PropertySpecificationDto('layout', 'string', false, false, 'Layout', 'Layout variant.', null, null, null),
        ];

        yield 'array with item schema' => [
            new PropertySpecificationDto('areas', 'array', false, false, 'Areas', 'Areas.', null, null, null, items: new PropertySpecificationDto('items', 'string', false, false, 'Area', 'Area.', null, null, null)),
        ];

        yield 'union including array with item schema' => [
            new PropertySpecificationDto('areas', ['string', 'array'], false, false, 'Areas', 'Areas.', null, null, null, items: new PropertySpecificationDto('items', 'string', false, false, 'Area', 'Area.', null, null, null)),
        ];

        yield 'union type without object' => [
            new PropertySpecificationDto('size', ['integer', 'string'], false, false, 'Size', 'Flexible size.', null, null, null),
        ];

        yield 'object type with properties' => [
            new PropertySpecificationDto(
                'columns',
                'object',
                false,
                false,
                'Columns',
                'Responsive column settings.',
                null,
                null,
                null,
                [
                    'xs' => new PropertySpecificationDto('xs', 'integer', false, false, 'XS', 'Columns for extra small screens.', null, null, null),
                ],
            ),
        ];

        yield 'union including object with properties' => [
            new PropertySpecificationDto(
                'columns',
                ['integer', 'object'],
                false,
                false,
                'Columns',
                'Either a fixed number or responsive map.',
                null,
                null,
                null,
                [
                    'xs' => new PropertySpecificationDto('xs', 'integer', false, false, 'XS', 'Columns for extra small screens.', null, null, null),
                ],
            ),
        ];
    }

    public function testRejectsUnionWithoutItemsAndInvalidNestedItems(): void
    {
        $union = new PropertySpecificationDto('areas', ['string', 'array'], false, false, 'Areas', 'Areas.', null, null, null);
        $nested = new PropertySpecificationDto('areas', 'array', false, false, 'Areas', 'Areas.', null, null, null, items: new PropertySpecificationDto('items', 'array', false, false, 'Row', 'Row.', null, null, null));

        foreach ([$union, $nested] as $index => $dto) {
            $violations = $this->validate($dto);
            static::assertCount(1, $violations);
            static::assertSame($index === 0 ? 'items' : 'items.items', $violations->get(0)->getPropertyPath());
            static::assertSame((new StructuredPropertyType())->arrayRequiresItemsMessage, $violations->get(0)->getMessage());
            static::assertNull($violations->get(0)->getCode());
        }
    }

    public function testRejectsItemsOnATypeWithoutArray(): void
    {
        $item = new PropertySpecificationDto('items', 'string', false, false, 'Entry', 'Entry.', null, null, null);
        $dto = new PropertySpecificationDto('label', 'string', false, false, 'Label', 'Label.', null, null, null, items: $item);

        $violations = $this->validate($dto);

        static::assertCount(1, $violations);
        static::assertSame('items', $violations->get(0)->getPropertyPath());
        static::assertSame((new StructuredPropertyType())->itemsRequireArrayTypeMessage, $violations->get(0)->getMessage());
    }

    #[DataProvider('rejectsInvalidSpecificationProvider')]
    #[TestDox('rejects invalid structured property specification with violation at $expectedPath')]
    public function testRejectsInvalidPropertySpecification(PropertySpecificationDto $dto, string $expectedPath): void
    {
        $violations = $this->validate($dto);

        static::assertGreaterThanOrEqual(1, $violations->count());
        static::assertSame($expectedPath, $violations->get(0)->getPropertyPath());
    }

    /**
     * @return iterable<string, array{PropertySpecificationDto, string}>
     */
    public static function rejectsInvalidSpecificationProvider(): iterable
    {
        yield 'empty type list' => [
            new PropertySpecificationDto('columns', [], false, false, 'Columns', 'Columns.', null, null, null),
            'type',
        ];

        yield 'duplicate type entries' => [
            new PropertySpecificationDto('columns', ['integer', 'integer'], false, false, 'Columns', 'Columns.', null, null, null),
            'type',
        ];

        yield 'object without properties' => [
            new PropertySpecificationDto('columns', 'object', false, false, 'Columns', 'Columns.', null, null, null),
            'properties',
        ];

        yield 'array without item schema' => [
            new PropertySpecificationDto('areas', 'array', false, false, 'Areas', 'Areas.', null, null, null),
            'items',
        ];

        yield 'item schema without array type' => [
            new PropertySpecificationDto('areas', 'string', false, false, 'Areas', 'Areas.', null, null, null, items: new PropertySpecificationDto('items', 'string', false, false, 'Area', 'Area.', null, null, null)),
            'items',
        ];

        yield 'properties without object type' => [
            new PropertySpecificationDto(
                'columns',
                'integer',
                false,
                false,
                'Columns',
                'Columns.',
                null,
                null,
                null,
                [
                    'xs' => new PropertySpecificationDto('xs', 'integer', false, false, 'XS', 'Columns for extra small screens.', null, null, null),
                ],
            ),
            'properties',
        ];
    }

    #[TestDox('throws UnexpectedTypeException when constraint type is wrong')]
    public function testThrowsOnWrongConstraintType(): void
    {
        $validator = new StructuredPropertyTypeValidator();
        $validator->initialize(static::createStub(ExecutionContextInterface::class));

        $this->expectExceptionObject(new UnexpectedTypeException(new NotBlank(), StructuredPropertyType::class));
        $validator->validate(
            new PropertySpecificationDto('x', 'string', false, false, 'X', 'X.', null, null, null),
            new NotBlank(),
        );
    }

    #[TestDox('throws UnexpectedTypeException when value type is wrong')]
    public function testThrowsOnWrongValueType(): void
    {
        $validator = new StructuredPropertyTypeValidator();
        $validator->initialize(static::createStub(ExecutionContextInterface::class));

        $this->expectExceptionObject(new UnexpectedTypeException('not-a-dto', PropertySpecificationDto::class));
        $validator->validate('not-a-dto', new StructuredPropertyType());
    }

    private function validate(PropertySpecificationDto $dto): ConstraintViolationListInterface
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        return $validator->validate($dto);
    }
}
