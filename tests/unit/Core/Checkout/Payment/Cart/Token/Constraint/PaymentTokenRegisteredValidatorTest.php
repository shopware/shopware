<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Cart\Token\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Cart\Token\Constraint\PaymentTokenRegistered;
use Shopware\Core\Checkout\Payment\Cart\Token\Constraint\PaymentTokenRegisteredValidator;
use Shopware\Core\Checkout\Payment\Cart\Token\PaymentTokenLifecycle;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\IsNull;
use Symfony\Component\Validator\Context\ExecutionContext;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[CoversClass(PaymentTokenRegisteredValidator::class)]
#[Package('checkout')]
class PaymentTokenRegisteredValidatorTest extends TestCase
{
    private MockObject&PaymentTokenLifecycle $paymentTokenLifecycle;

    private PaymentTokenRegisteredValidator $validator;

    private ExecutionContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentTokenLifecycle = $this->createMock(PaymentTokenLifecycle::class);
        $this->validator = new PaymentTokenRegisteredValidator($this->paymentTokenLifecycle);
        $this->context = new ExecutionContext(
            $this->createMock(ValidatorInterface::class),
            null,
            $this->createMock(TranslatorInterface::class),
        );
        $this->validator->initialize($this->context);
    }

    public function testThrowsOnUnexpectedConstraintType(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate('some-value', new IsNull());
    }

    public function testNullAndEmptyDoNotTriggerLifecycleAndNoViolation(): void
    {
        $this->paymentTokenLifecycle
            ->expects($this->never())
            ->method('isRegistered');

        // null value
        $this->validator->validate(null, new PaymentTokenRegistered());
        static::assertEmpty($this->context->getViolations());

        // empty string value
        $this->validator->validate('', new PaymentTokenRegistered());
        static::assertEmpty($this->context->getViolations());

        // integer value
        $this->validator->validate(1, new PaymentTokenRegistered());
        static::assertEmpty($this->context->getViolations());

        // bool value
        $this->validator->validate(true, new PaymentTokenRegistered());
        static::assertEmpty($this->context->getViolations());
    }

    public function testRegisteredTokenProducesNoViolation(): void
    {
        $this->paymentTokenLifecycle
            ->expects($this->once())
            ->method('isRegistered')
            ->with('token-id-123')
            ->willReturn(true);

        $this->validator->validate('token-id-123', new PaymentTokenRegistered());

        static::assertEmpty($this->context->getViolations());
    }

    public function testUnregisteredTokenAddsViolation(): void
    {
        $this->paymentTokenLifecycle
            ->expects($this->once())
            ->method('isRegistered')
            ->with('token-id-456')
            ->willReturn(false);

        $this->validator->validate('token-id-456', new PaymentTokenRegistered());

        static::assertCount(1, $this->context->getViolations());
        static::assertSame(PaymentTokenRegistered::PAYMENT_TOKEN_NOT_REGISTERED, $this->context->getViolations()->get(0)->getCode());
    }
}
