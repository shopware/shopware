<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Validation\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfType;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfTypeValidator;
use Shopware\Core\Framework\Validation\Constraint\Uuid;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

/**
 * @internal
 */
#[CoversClass(ArrayOfTypeValidator::class)]
class ArrayOfTypeValidatorTest extends TestCase
{
    public function testValidateThrowsExceptionBecauseConstraintHasWrongClass(): void
    {
        $wrongConstraint = new Uuid();
        $this->expectExceptionObject(FrameworkException::unexpectedType($wrongConstraint, ArrayOfType::class));
        $validator = new ArrayOfTypeValidator();
        $validator->validate([], $wrongConstraint);
    }

    public function testValidateWithNullValue(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        $validator = new ArrayOfTypeValidator();
        $validator->initialize($context);
        $validator->validate(null, new ArrayOfType('string'));
    }

    public function testValidateWithNonArrayValue(): void
    {
        $violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $violationBuilder->expects($this->once())->method('addViolation');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->once())
            ->method('buildViolation')
            ->with(ArrayOfType::INVALID_TYPE_MESSAGE)
            ->willReturn($violationBuilder);

        $validator = new ArrayOfTypeValidator();
        $validator->initialize($context);

        // Validate a non-array value
        $validator->validate('not an array', new ArrayOfType('string'));
    }

    public function testValidateWithIsFunctionCheck(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        $validator = new ArrayOfTypeValidator();
        $validator->initialize($context);

        // Validate an array of strings which will pass is_string check
        $validator->validate(['test1', 'test2', 'test3'], new ArrayOfType('string'));
    }

    public function testValidateWithClassInstanceCheck(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        $validator = new ArrayOfTypeValidator();
        $validator->initialize($context);

        // Validate an array of objects which will pass class instance check
        $validator->validate([new \stdClass(), new \stdClass()], new ArrayOfType(\stdClass::class));
    }

    public function testValidateWithInvalidTypeAddsViolation(): void
    {
        $violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $violationBuilder->expects($this->once())->method('setCode')->with(Type::INVALID_TYPE_ERROR)->willReturn($violationBuilder);
        $violationBuilder->expects($this->exactly(2))->method('setParameter')->willReturn($violationBuilder);
        $violationBuilder->expects($this->once())->method('addViolation');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->once())
            ->method('buildViolation')
            ->with(ArrayOfType::INVALID_MESSAGE)
            ->willReturn($violationBuilder);

        $validator = new ArrayOfTypeValidator();
        $validator->initialize($context);

        // Validate an array with an invalid type
        $validator->validate(['test1', 123], new ArrayOfType('string'));
    }

    public function testValidateWithBooleanType(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        $validator = new ArrayOfTypeValidator();
        $validator->initialize($context);

        // Validate an array of booleans which will pass is_bool check
        $validator->validate([true, false], new ArrayOfType('boolean'));
    }
}
