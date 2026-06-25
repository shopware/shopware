<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Style\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\Dto\StyleOptionSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Validation\TypedStyleOption;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Validation\TypedStyleOptionValidator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[CoversClass(TypedStyleOptionValidator::class)]
class TypedStyleOptionValidatorTest extends TestCase
{
    #[DataProvider('acceptsValidOptionProvider')]
    #[TestDox('accepts a well-formed style option declaration without violations')]
    public function testAcceptsValidOption(StyleOptionSpecificationDto $dto): void
    {
        static::assertCount(0, $this->validate($dto));
    }

    /**
     * @return iterable<string, array{StyleOptionSpecificationDto}>
     */
    public static function acceptsValidOptionProvider(): iterable
    {
        yield 'boolean without bounds' => [
            new StyleOptionSpecificationDto('boolean', null, null, null, null, null),
        ];

        yield 'string enum (align-self)' => [
            new StyleOptionSpecificationDto('string', ['start', 'center', 'end'], null, null, 'start', null),
        ];

        yield 'integer range (col-span)' => [
            new StyleOptionSpecificationDto('integer', null, ['min' => 1, 'max' => 12], null, null, null),
        ];

        yield 'string with maxLength and default (margin)' => [
            new StyleOptionSpecificationDto('string', null, null, 64, '0', null),
        ];

        yield 'number with open-ended range' => [
            new StyleOptionSpecificationDto('number', null, ['min' => 0], null, null, null),
        ];
    }

    #[DataProvider('rejectsInvalidOptionProvider')]
    #[TestDox('rejects $_dataName with a violation at $expectedPath')]
    public function testRejectsInvalidOption(StyleOptionSpecificationDto $dto, string $expectedPath, string $expectedMessage): void
    {
        $violations = $this->validate($dto);

        static::assertGreaterThanOrEqual(1, $violations->count());
        static::assertSame($expectedPath, $violations->get(0)->getPropertyPath());
        static::assertStringContainsString($expectedMessage, (string) $violations->get(0)->getMessage());
    }

    /**
     * @return iterable<string, array{StyleOptionSpecificationDto, string, string}>
     */
    public static function rejectsInvalidOptionProvider(): iterable
    {
        yield 'enum is not a list' => [
            new StyleOptionSpecificationDto('string', ['a' => 'b'], null, null, null, null), // @phpstan-ignore argument.type (intentionally invalid: associative array instead of list)
            'enum',
            'enum must be a list',
        ];

        yield 'enum values mismatch declared type' => [
            new StyleOptionSpecificationDto('integer', ['sm', 'md'], null, null, null, null),
            'enum',
            'enum values must all match',
        ];

        yield 'range on a string type' => [
            new StyleOptionSpecificationDto('string', null, ['min' => 1, 'max' => 4], null, null, null),
            'range',
            'range is only valid for the numeric',
        ];

        yield 'range bounds are non-numeric' => [
            new StyleOptionSpecificationDto('integer', null, ['min' => 'a'], null, null, null),
            'range',
            'range bounds must be numeric',
        ];

        yield 'range min exceeds max' => [
            new StyleOptionSpecificationDto('integer', null, ['min' => 12, 'max' => 1], null, null, null),
            'range',
            'range bounds must be numeric',
        ];

        yield 'maxLength on a non-string type' => [
            new StyleOptionSpecificationDto('integer', null, null, 10, null, null),
            'maxLength',
            'maxLength is only valid for the "string"',
        ];

        yield 'default mismatches declared type' => [
            new StyleOptionSpecificationDto('integer', null, null, null, 'two', null),
            'default',
            'default must match the declared type',
        ];

        yield 'type is not a primitive' => [
            new StyleOptionSpecificationDto('object', null, null, null, null, null),
            'type',
            'not a valid choice',
        ];
    }

    #[TestDox('throws UnexpectedTypeException when the constraint type is wrong')]
    public function testThrowsOnWrongConstraintType(): void
    {
        $validator = new TypedStyleOptionValidator();
        $validator->initialize(static::createStub(ExecutionContextInterface::class));

        $this->expectExceptionObject(new UnexpectedTypeException(new NotBlank(), TypedStyleOption::class));
        $validator->validate(
            new StyleOptionSpecificationDto('string', null, null, null, null, null),
            new NotBlank(),
        );
    }

    #[TestDox('throws UnexpectedTypeException when the value type is wrong')]
    public function testThrowsOnWrongValueType(): void
    {
        $validator = new TypedStyleOptionValidator();
        $validator->initialize(static::createStub(ExecutionContextInterface::class));

        $this->expectExceptionObject(new UnexpectedTypeException('not-a-dto', StyleOptionSpecificationDto::class));
        $validator->validate('not-a-dto', new TypedStyleOption());
    }

    private function validate(StyleOptionSpecificationDto $dto): ConstraintViolationListInterface
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        return $validator->validate($dto);
    }
}
