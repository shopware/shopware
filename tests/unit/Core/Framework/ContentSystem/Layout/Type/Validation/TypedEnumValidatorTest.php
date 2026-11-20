<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\PropertySpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Validation\TypedEnum;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Validation\TypedEnumValidator;
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
#[CoversClass(TypedEnumValidator::class)]
class TypedEnumValidatorTest extends TestCase
{
    #[DataProvider('acceptsValidSpecificationProvider')]
    #[TestDox('accepts valid property specification without violations')]
    public function testAcceptsValidPropertySpecification(PropertySpecificationDto $dto): void
    {
        static::assertCount(0, $this->validate($dto));
    }

    /**
     * @return iterable<string, array{PropertySpecificationDto}>
     */
    public static function acceptsValidSpecificationProvider(): iterable
    {
        yield 'string enum on string type' => [
            new PropertySpecificationDto('layout', 'string', false, false, 'Layout', 'Layout variant.', ['a', 'b'], null, null),
        ];

        yield 'integer enum on integer type' => [
            new PropertySpecificationDto('size', 'integer', false, false, 'Size', 'Size value.', [1, 2, 3], null, null),
        ];

        yield 'null enum (absent)' => [
            new PropertySpecificationDto('name', 'string', false, false, 'Name', 'A name.', null, null, null),
        ];
    }

    #[DataProvider('rejectsInvalidSpecificationProvider')]
    #[TestDox('rejects invalid property specification with violation at $expectedPath')]
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
        yield 'enum on FQCN type' => [
            new PropertySpecificationDto('product', 'Shopware\Core\Content\Product\ProductEntity', false, false, 'Product', 'Product.', ['a'], null, null),
            'enum',
        ];

        yield 'enum is not a list' => [
            new PropertySpecificationDto('layout', 'string', false, false, 'Layout', 'Layout.', ['a' => 'b'], null, null), // @phpstan-ignore argument.type (intentionally invalid: associative array instead of list)
            'enum',
        ];

        yield 'string values on integer type' => [
            new PropertySpecificationDto('size', 'integer', false, false, 'Size', 'Size.', ['sm', 'md', 'lg'], null, null),
            'enum',
        ];

        yield 'mixed value types on string type' => [
            new PropertySpecificationDto('variant', 'string', false, false, 'Variant', 'Variant.', ['a', 1], null, null),
            'enum',
        ];

        yield 'enum on union type' => [
            new PropertySpecificationDto('size', ['integer', 'string'], false, false, 'Size', 'Flexible size.', [1, 2], null, null),
            'enum',
        ];
    }

    #[TestDox('throws UnexpectedTypeException when constraint type is wrong')]
    public function testThrowsOnWrongConstraintType(): void
    {
        $validator = new TypedEnumValidator();
        $validator->initialize(static::createStub(ExecutionContextInterface::class));

        $this->expectExceptionObject(new UnexpectedTypeException(new NotBlank(), TypedEnum::class));
        $validator->validate(
            new PropertySpecificationDto('x', 'string', false, false, 'X', 'X.', null, null, null),
            new NotBlank(),
        );
    }

    #[TestDox('throws UnexpectedTypeException when value type is wrong')]
    public function testThrowsOnWrongValueType(): void
    {
        $validator = new TypedEnumValidator();
        $validator->initialize(static::createStub(ExecutionContextInterface::class));

        $this->expectExceptionObject(new UnexpectedTypeException('not-a-dto', PropertySpecificationDto::class));
        $validator->validate('not-a-dto', new TypedEnum());
    }

    private function validate(PropertySpecificationDto $dto): ConstraintViolationListInterface
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        return $validator->validate($dto);
    }
}
