<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Style\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\Dto\StyleOptionSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Validation\TypedStyleOption;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Validation\TypedStyleOptionValidator;
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
            new StyleOptionSpecificationDto('boolean', null, null, null, null, null, null),
        ];

        yield 'string enum (align-self)' => [
            new StyleOptionSpecificationDto('string', ['start', 'center', 'end'], null, null, 'start', null, null),
        ];

        yield 'integer range (col-span)' => [
            new StyleOptionSpecificationDto('integer', null, ['min' => 1, 'max' => 12], null, null, null, null),
        ];

        yield 'string with maxLength and default (margin)' => [
            new StyleOptionSpecificationDto('string', null, null, 64, '0', null, null),
        ];

        yield 'number with open-ended range' => [
            new StyleOptionSpecificationDto('number', null, ['min' => 0], null, null, null, null),
        ];

        yield 'integer with an in-range default' => [
            new StyleOptionSpecificationDto('integer', null, ['min' => 1, 'max' => 12], null, 6, null, null),
        ];

        yield 'a valid adminUI passthrough block' => [
            new StyleOptionSpecificationDto('boolean', null, null, null, null, null, ['component' => 'switch']),
        ];

        yield 'string default exactly at the implicit 255 cap (no maxLength declared)' => [
            new StyleOptionSpecificationDto('string', null, null, null, str_repeat('a', 255), null, null),
        ];

        yield 'breakpointAware boolean is valid' => [
            new StyleOptionSpecificationDto('boolean', null, null, null, null, true, null),
        ];

        yield 'breakpointAware null (absent) is valid' => [
            new StyleOptionSpecificationDto('boolean', null, null, null, null, null, null),
        ];
    }

    #[DataProvider('rejectsInvalidOptionProvider')]
    #[TestDox('rejects $_dataName with a violation at $expectedPath containing $expectedMessage')]
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
        yield 'enum is not an array' => [
            new StyleOptionSpecificationDto('string', 'not-an-array', null, null, null, null, null),
            'enum',
            'enum must be an array',
        ];

        yield 'enum is not a list' => [
            new StyleOptionSpecificationDto('string', ['a' => 'b'], null, null, null, null, null),
            'enum',
            'enum must be a list',
        ];

        yield 'enum is empty' => [
            new StyleOptionSpecificationDto('string', [], null, null, null, null, null),
            'enum',
            'enum must not be empty',
        ];

        yield 'enum values mismatch declared type' => [
            new StyleOptionSpecificationDto('integer', ['sm', 'md'], null, null, null, null, null),
            'enum',
            'enum values must all match',
        ];

        yield 'range is not an array' => [
            new StyleOptionSpecificationDto('integer', null, 'not-an-array', null, null, null, null),
            'range',
            'range must be an array',
        ];

        yield 'range on a string type' => [
            new StyleOptionSpecificationDto('string', null, ['min' => 1, 'max' => 4], null, null, null, null),
            'range',
            'range is only valid for the numeric',
        ];

        yield 'range bounds are non-numeric' => [
            new StyleOptionSpecificationDto('integer', null, ['min' => 'a'], null, null, null, null),
            'range',
            'range bounds must be numeric',
        ];

        yield 'range min exceeds max' => [
            new StyleOptionSpecificationDto('integer', null, ['min' => 12, 'max' => 1], null, null, null, null),
            'range',
            'min must not exceed max',
        ];

        yield 'maxLength on a non-string type' => [
            new StyleOptionSpecificationDto('integer', null, null, 10, null, null, null),
            'maxLength',
            'maxLength is only valid for the "string"',
        ];

        yield 'maxLength is not an integer' => [
            new StyleOptionSpecificationDto('string', null, null, '64', null, null, null),
            'maxLength',
            'maxLength must be a positive integer',
        ];

        yield 'maxLength is not positive' => [
            new StyleOptionSpecificationDto('string', null, null, 0, null, null, null),
            'maxLength',
            'maxLength must be a positive integer',
        ];

        yield 'default mismatches declared type' => [
            new StyleOptionSpecificationDto('integer', null, null, null, 'two', null, null),
            'default',
            'default must match the declared type',
        ];

        yield 'default outside the enum' => [
            new StyleOptionSpecificationDto('string', ['start', 'center'], null, null, 'end', null, null),
            'default',
            'default must be one of the enum values',
        ];

        yield 'default below the range minimum' => [
            new StyleOptionSpecificationDto('integer', null, ['min' => 1, 'max' => 12], null, 0, null, null),
            'default',
            'default must be within the declared range',
        ];

        yield 'default above the range maximum' => [
            new StyleOptionSpecificationDto('integer', null, ['min' => 1, 'max' => 12], null, 99, null, null),
            'default',
            'default must be within the declared range',
        ];

        yield 'default longer than maxLength' => [
            new StyleOptionSpecificationDto('string', null, null, 4, 'toolong', null, null),
            'default',
            'default must not exceed maxLength',
        ];

        yield 'string default longer than the implicit 255 cap (no maxLength declared)' => [
            new StyleOptionSpecificationDto('string', null, null, null, str_repeat('a', 256), null, null),
            'default',
            'default must not exceed maxLength',
        ];

        yield 'adminUI is not an array' => [
            new StyleOptionSpecificationDto('string', null, null, null, null, null, 'not-an-array'),
            'adminUI',
            'adminUI must be an array',
        ];

        yield 'type is not a primitive' => [
            new StyleOptionSpecificationDto('object', null, null, null, null, null, null),
            'type',
            'not a valid choice',
        ];

        yield 'breakpointAware is not a boolean' => [
            new StyleOptionSpecificationDto('string', null, null, null, null, 'true', null),
            'breakpointAware',
            'breakpointAware must be a boolean',
        ];
    }

    #[TestDox('emits a breakpointAware violation even when the declared type is invalid')]
    public function testEmitsBreakpointAwareViolationEvenWhenTypeIsInvalid(): void
    {
        // type 'object' triggers the non-primitive early-return; 'true' (string) is a non-bool breakpointAware.
        // If validateBreakpointAware ran after the early-return, no breakpointAware violation would appear.
        $dto = new StyleOptionSpecificationDto('object', null, null, null, null, 'true', null);

        $violations = $this->validate($dto);

        $paths = [];
        foreach ($violations as $violation) {
            $paths[] = $violation->getPropertyPath();
        }

        static::assertContains('breakpointAware', $paths);
    }

    #[TestDox('throws UnexpectedTypeException when the constraint type is wrong')]
    public function testThrowsOnWrongConstraintType(): void
    {
        $validator = new TypedStyleOptionValidator();
        $validator->initialize(static::createStub(ExecutionContextInterface::class));

        $this->expectExceptionObject(new UnexpectedTypeException(new NotBlank(), TypedStyleOption::class));
        $validator->validate(
            new StyleOptionSpecificationDto('string', null, null, null, null, null, null),
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
