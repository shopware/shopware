<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Cart\Token\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
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
#[Package('framework')]
#[CoversClass(PaymentTokenRegisteredValidator::class)]
class PaymentTokenRegisteredValidatorTest extends TestCase
{
    private Stub&PaymentTokenLifecycle $paymentTokenLifecycle;

    private PaymentTokenRegisteredValidator $validator;

    private ExecutionContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentTokenLifecycle = static::createStub(PaymentTokenLifecycle::class);
        $this->context = new ExecutionContext(
            static::createStub(ValidatorInterface::class),
            null,
            static::createStub(TranslatorInterface::class),
        );
        $this->validator = $this->buildValidator($this->paymentTokenLifecycle);
    }

    public function testThrowsOnUnexpectedConstraintType(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate('some-value', new IsNull());
    }

    public function testNullAndEmptyDoNotTriggerLifecycleAndNoViolation(): void
    {
        $paymentTokenLifecycle = $this->createMock(PaymentTokenLifecycle::class);
        $paymentTokenLifecycle
            ->expects($this->never())
            ->method('isRegistered');
        $validator = $this->buildValidator($paymentTokenLifecycle);

        // null value
        $validator->validate(null, new PaymentTokenRegistered());
        static::assertEmpty($this->context->getViolations());

        // empty string value
        $validator->validate('', new PaymentTokenRegistered());
        static::assertEmpty($this->context->getViolations());

        // integer value
        $validator->validate(1, new PaymentTokenRegistered());
        static::assertEmpty($this->context->getViolations());

        // bool value
        $validator->validate(true, new PaymentTokenRegistered());
        static::assertEmpty($this->context->getViolations());
    }

    public function testRegisteredTokenProducesNoViolation(): void
    {
        $paymentTokenLifecycle = $this->createMock(PaymentTokenLifecycle::class);
        $paymentTokenLifecycle
            ->expects($this->once())
            ->method('isRegistered')
            ->with('token-id-123')
            ->willReturn(true);
        $validator = $this->buildValidator($paymentTokenLifecycle);

        $validator->validate('token-id-123', new PaymentTokenRegistered());

        static::assertEmpty($this->context->getViolations());
    }

    public function testUnregisteredTokenAddsViolation(): void
    {
        $paymentTokenLifecycle = $this->createMock(PaymentTokenLifecycle::class);
        $paymentTokenLifecycle
            ->expects($this->once())
            ->method('isRegistered')
            ->with('token-id-456')
            ->willReturn(false);
        $validator = $this->buildValidator($paymentTokenLifecycle);

        $validator->validate('token-id-456', new PaymentTokenRegistered());

        static::assertCount(1, $this->context->getViolations());
        static::assertSame(PaymentTokenRegistered::PAYMENT_TOKEN_NOT_REGISTERED, $this->context->getViolations()->get(0)->getCode());
    }

    private function buildValidator(PaymentTokenLifecycle $paymentTokenLifecycle): PaymentTokenRegisteredValidator
    {
        $validator = new PaymentTokenRegisteredValidator($paymentTokenLifecycle);
        $validator->initialize($this->context);

        return $validator;
    }
}
