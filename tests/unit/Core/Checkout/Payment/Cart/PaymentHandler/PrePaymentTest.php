<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Cart\PaymentHandler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PrePayment;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PrePayment::class)]
class PrePaymentTest extends TestCase
{
    protected function setUp(): void
    {
        Feature::skipTestIfInActive('v6.7.0.0', $this);
        if (!\is_a(PrePayment::class, AbstractPaymentHandler::class, true)) {
            static::markTestSkipped(sprintf('Class %s must extend %s', PrePayment::class, AbstractPaymentHandler::class));
        }
    }

    public function testPay(): void
    {
        $payment = new PrePayment(
            $this->createMock(OrderTransactionStateHandler::class)
        );
        $response = $payment->pay(
            new Request(),
            new PaymentTransactionStruct(Uuid::randomHex()),
            Context::createDefaultContext(),
            null,
        );

        static::assertNull($response);
    }

    public function testSupports(): void
    {
        $payment = new PrePayment(
            $this->createMock(OrderTransactionStateHandler::class)
        );

        foreach (PaymentHandlerType::cases() as $case) {
            $supports = $payment->supports(
                $case,
                Uuid::randomHex(),
                Context::createDefaultContext()
            );

            static::assertSame($case === PaymentHandlerType::RECURRING, $supports);
        }
    }

    #[DoesNotPerformAssertions]
    public function testRecurring(): void
    {
        $payment = new PrePayment(
            $this->createMock(OrderTransactionStateHandler::class)
        );

        $payment->recurring(
            new PaymentTransactionStruct(Uuid::randomHex()),
            Context::createDefaultContext(),
        );
    }
}
