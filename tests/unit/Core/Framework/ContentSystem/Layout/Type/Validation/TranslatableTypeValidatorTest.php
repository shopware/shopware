<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\PropertySpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Validation\TranslatableType;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Validation\TranslatableTypeValidator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TranslatableTypeValidator::class)]
class TranslatableTypeValidatorTest extends TestCase
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

        yield 'non-translatable on integer type' => [
            new PropertySpecificationDto('count', 'integer', false, false, 'Count', 'A count.', null, null, null),
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
        yield 'translatable on integer type' => [
            new PropertySpecificationDto('count', 'integer', false, true, 'Count', 'A count.', null, null, null),
            'translatable',
        ];

        yield 'translatable on FQCN type' => [
            new PropertySpecificationDto('product', 'Shopware\Core\Content\Product\ProductEntity', false, true, 'Product', 'A product.', null, null, null),
            'translatable',
        ];

        yield 'translatable on union type' => [
            new PropertySpecificationDto('label', ['string', 'integer'], false, true, 'Label', 'A label.', null, null, null),
            'translatable',
        ];

        // The lone scalar declaration only: a single-member list still declares a union, which the stored
        // language map is not a shape of.
        yield 'translatable on a single-member list of string' => [
            new PropertySpecificationDto('text', ['string'], false, true, 'Text', 'Text content.', null, null, null),
            'translatable',
        ];
    }

    /**
     * @param class-string $expectedType
     */
    #[DataProvider('throwsUnexpectedTypeProvider')]
    #[TestDox('throws UnexpectedTypeException when $_dataName')]
    public function testThrowsUnexpectedType(mixed $value, Constraint $constraint, mixed $expectedInvalidValue, string $expectedType): void
    {
        $validator = new TranslatableTypeValidator();
        $validator->initialize(static::createStub(ExecutionContextInterface::class));

        $this->expectExceptionObject(new UnexpectedTypeException($expectedInvalidValue, $expectedType));
        $validator->validate($value, $constraint);
    }

    /**
     * @return iterable<string, array{mixed, Constraint, mixed, class-string}>
     */
    public static function throwsUnexpectedTypeProvider(): iterable
    {
        yield 'the constraint is not a TranslatableType' => [
            new PropertySpecificationDto('x', 'string', false, false, 'X', 'X.', null, null, null),
            new NotBlank(),
            new NotBlank(),
            TranslatableType::class,
        ];

        yield 'the value is not a PropertySpecificationDto' => [
            'not-a-dto',
            new TranslatableType(),
            'not-a-dto',
            PropertySpecificationDto::class,
        ];
    }

    private function validate(PropertySpecificationDto $dto): ConstraintViolationListInterface
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        return $validator->validate($dto);
    }
}
