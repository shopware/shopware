<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\PropertySpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Validation\TypedDefault;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Validation\TypedDefaultValidator;
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
#[CoversClass(TypedDefaultValidator::class)]
class TypedDefaultValidatorTest extends TestCase
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
        yield 'string default on string type' => [
            new PropertySpecificationDto('label', 'string', false, false, 'Label', 'A label.', null, 'default', null),
        ];

        yield 'int default on integer type' => [
            new PropertySpecificationDto('count', 'integer', false, false, 'Count', 'A count.', null, 5, null),
        ];

        yield 'float default on number type' => [
            new PropertySpecificationDto('price', 'number', false, false, 'Price', 'A price.', null, 9.99, null),
        ];

        yield 'int default on number type' => [
            new PropertySpecificationDto('amount', 'number', false, false, 'Amount', 'An amount.', null, 10, null),
        ];

        yield 'bool default on boolean type' => [
            new PropertySpecificationDto('active', 'boolean', false, false, 'Active', 'Is active.', null, false, null),
        ];

        yield 'null default on any type' => [
            new PropertySpecificationDto('name', 'string', false, false, 'Name', 'A name.', null, null, null),
        ];

        yield 'null default on FQCN type' => [
            new PropertySpecificationDto('product', 'Shopware\Core\Content\Product\ProductEntity', false, false, 'Product', 'A product.', null, null, null),
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
        yield 'string default on integer type' => [
            new PropertySpecificationDto('count', 'integer', false, false, 'Count', 'A count.', null, 'hello', null),
            'default',
        ];

        yield 'bool default on string type' => [
            new PropertySpecificationDto('label', 'string', false, false, 'Label', 'A label.', null, true, null),
            'default',
        ];

        yield 'any default on FQCN type' => [
            new PropertySpecificationDto('product', 'Shopware\Core\Content\Product\ProductEntity', false, false, 'Product', 'A product.', null, 'value', null),
            'default',
        ];

        yield 'default on union type' => [
            new PropertySpecificationDto('size', ['integer', 'string'], false, false, 'Size', 'Flexible size.', null, 1, null),
            'default',
        ];
    }

    #[TestDox('throws UnexpectedTypeException when constraint type is wrong')]
    public function testThrowsOnWrongConstraintType(): void
    {
        $validator = new TypedDefaultValidator();
        $validator->initialize(static::createStub(ExecutionContextInterface::class));

        $this->expectExceptionObject(new UnexpectedTypeException(new NotBlank(), TypedDefault::class));
        $validator->validate(
            new PropertySpecificationDto('x', 'string', false, false, 'X', 'X.', null, null, null),
            new NotBlank(),
        );
    }

    #[TestDox('throws UnexpectedTypeException when value type is wrong')]
    public function testThrowsOnWrongValueType(): void
    {
        $validator = new TypedDefaultValidator();
        $validator->initialize(static::createStub(ExecutionContextInterface::class));

        $this->expectExceptionObject(new UnexpectedTypeException('not-a-dto', PropertySpecificationDto::class));
        $validator->validate('not-a-dto', new TypedDefault());
    }

    private function validate(PropertySpecificationDto $dto): ConstraintViolationListInterface
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        return $validator->validate($dto);
    }
}
