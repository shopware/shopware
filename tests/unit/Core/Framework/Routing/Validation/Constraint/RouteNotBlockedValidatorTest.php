<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Routing\Validation\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Routing\Validation\Constraint\RouteNotBlocked;
use Shopware\Core\Framework\Routing\Validation\Constraint\RouteNotBlockedValidator;
use Shopware\Core\Framework\Routing\Validation\RouteBlocklistService;
use Shopware\Core\Framework\Validation\Constraint\Uuid;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

/**
 * @internal
 */
#[CoversClass(RouteNotBlockedValidator::class)]
class RouteNotBlockedValidatorTest extends TestCase
{
    public function testValidateThrowsExceptionForWrongConstraintType(): void
    {
        $wrongConstraint = new Uuid();
        $blocklistService = $this->createMock(RouteBlocklistService::class);

        $validator = new RouteNotBlockedValidator($blocklistService);

        $this->expectExceptionObject(
            RoutingException::unexpectedType($wrongConstraint, RouteNotBlockedValidator::class)
        );

        $validator->validate('test', $wrongConstraint);
    }

    public function testValidateAllowsEmptyValue(): void
    {
        $blocklistService = $this->createMock(RouteBlocklistService::class);
        $blocklistService->expects($this->never())->method('isPathBlocked');

        $constraint = new RouteNotBlocked();
        $validator = new RouteNotBlockedValidator($blocklistService);

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');
        $validator->initialize($context);

        $validator->validate('', $constraint);
    }

    public function testValidateAllowsNullValue(): void
    {
        $blocklistService = $this->createMock(RouteBlocklistService::class);
        $blocklistService->expects($this->never())->method('isPathBlocked');

        $constraint = new RouteNotBlocked();
        $validator = new RouteNotBlockedValidator($blocklistService);

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');
        $validator->initialize($context);

        $validator->validate(null, $constraint);
    }

    public function testValidateAddsViolationForNonStringValue(): void
    {
        $blocklistService = $this->createMock(RouteBlocklistService::class);
        $constraint = new RouteNotBlocked();

        $violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $violationBuilder->expects($this->once())->method('addViolation');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->once())
            ->method('buildViolation')
            ->with(RouteNotBlocked::INVALID_TYPE_MESSAGE)
            ->willReturn($violationBuilder);

        $validator = new RouteNotBlockedValidator($blocklistService);
        $validator->initialize($context);

        $validator->validate(123, $constraint);
    }

    public function testValidateAllowsNonBlockedPath(): void
    {
        $blocklistService = $this->createMock(RouteBlocklistService::class);
        $blocklistService->expects($this->once())
            ->method('isPathBlocked')
            ->with('category')
            ->willReturn(false);

        $constraint = new RouteNotBlocked();
        $validator = new RouteNotBlockedValidator($blocklistService);

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');
        $validator->initialize($context);

        $validator->validate('category', $constraint);
    }

    public function testValidateAddsViolationForBlockedPath(): void
    {
        $blocklistService = $this->createMock(RouteBlocklistService::class);
        $blocklistService->expects($this->once())
            ->method('isPathBlocked')
            ->with('maintenance')
            ->willReturn(true);

        $constraint = new RouteNotBlocked();

        $violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $violationBuilder->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnSelf();
        $violationBuilder->expects($this->once())
            ->method('setCode')
            ->with(RouteNotBlocked::ROUTE_BLOCKED)
            ->willReturnSelf();
        $violationBuilder->expects($this->once())
            ->method('addViolation');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->once())
            ->method('buildViolation')
            ->with($constraint->getMessage())
            ->willReturn($violationBuilder);

        $validator = new RouteNotBlockedValidator($blocklistService);
        $validator->initialize($context);

        $validator->validate('maintenance', $constraint);
    }
}
