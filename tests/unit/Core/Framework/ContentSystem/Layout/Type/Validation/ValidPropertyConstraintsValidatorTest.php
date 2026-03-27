<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\PropertySpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Validation\ValidPropertyConstraints;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Validation\ValidPropertyConstraintsValidator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[CoversClass(ValidPropertyConstraintsValidator::class)]
class ValidPropertyConstraintsValidatorTest extends TestCase
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
        yield 'translatable on string type' => [
            new PropertySpecificationDto('text', 'string', false, true, 'Text', 'Text content.', null, null, null),
        ];

        yield 'enum on primitive type' => [
            new PropertySpecificationDto('layout', 'string', false, false, 'Layout', 'Layout variant.', ['a', 'b'], null, null),
        ];
    }

    #[DataProvider('rejectsInvalidSpecificationProvider')]
    #[TestDox('rejects invalid property specification with violation at $expectedPath')]
    public function testRejectsInvalidPropertySpecification(PropertySpecificationDto $dto, string $expectedPath): void
    {
        $violations = $this->validate($dto);

        static::assertCount(1, $violations);
        static::assertSame($expectedPath, $violations->get(0)->getPropertyPath());
    }

    /**
     * @return iterable<string, array{PropertySpecificationDto, string}>
     */
    public static function rejectsInvalidSpecificationProvider(): iterable
    {
        yield 'translatable on non-string type' => [
            new PropertySpecificationDto('count', 'integer', false, true, 'Count', 'A count.', null, null, null),
            'translatable',
        ];

        yield 'enum on FQCN type' => [
            new PropertySpecificationDto('product', 'Shopware\Core\Content\Product\ProductEntity', false, false, 'Product', 'Product.', ['a'], null, null),
            'enum',
        ];

        yield 'enum is not a list' => [
            new PropertySpecificationDto('layout', 'string', false, false, 'Layout', 'Layout.', ['a' => 'b'], null, null), // @phpstan-ignore argument.type (intentionally invalid: associative array instead of list)
            'enum',
        ];
    }

    #[TestDox('throws UnexpectedTypeException when constraint type is wrong')]
    public function testThrowsOnWrongConstraintType(): void
    {
        $validator = new ValidPropertyConstraintsValidator();
        $validator->initialize(static::createStub(ExecutionContextInterface::class));

        $this->expectExceptionObject(new UnexpectedTypeException(new NotBlank(), ValidPropertyConstraints::class));
        $validator->validate(
            new PropertySpecificationDto('x', 'string', false, false, 'X', 'X.', null, null, null),
            new NotBlank(),
        );
    }

    #[TestDox('throws UnexpectedTypeException when value type is wrong')]
    public function testThrowsOnWrongValueType(): void
    {
        $validator = new ValidPropertyConstraintsValidator();
        $validator->initialize(static::createStub(ExecutionContextInterface::class));

        $this->expectExceptionObject(new UnexpectedTypeException('not-a-dto', PropertySpecificationDto::class));
        $validator->validate('not-a-dto', new ValidPropertyConstraints());
    }

    private function validate(PropertySpecificationDto $dto): ConstraintViolationListInterface
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        return $validator->validate($dto);
    }
}
