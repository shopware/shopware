<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Cart\PaymentHandler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\CashPayment;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\DebitPayment;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
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
#[CoversClass(DebitPayment::class)]
class DebitPaymentTest extends TestCase
{
    protected function setUp(): void
    {
        Feature::skipTestIfInActive('v6.7.0.0', $this);
        if (!\is_a(DebitPayment::class, AbstractPaymentHandler::class, true)) {
            static::markTestSkipped(sprintf('Class %s must extend %s', DebitPayment::class, AbstractPaymentHandler::class));
        }
    }

    public function testPay(): void
    {
        $transactionId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $stateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $stateHandler
            ->expects(static::once())
            ->method('process')
            ->with($transactionId, $context);

        $payment = new DebitPayment($stateHandler);
        $reponse = $payment->pay(
            new Request(),
            new PaymentTransactionStruct($transactionId),
            $context,
            null,
        );

        static::assertNull($reponse);
    }

    public function testSupports(): void
    {
        $payment = new CashPayment(
            $this->createMock(OrderTransactionStateHandler::class)
        );

        foreach (PaymentHandlerType::cases() as $case) {
            static::assertFalse($payment->supports(
                $case,
                Uuid::randomHex(),
                Context::createDefaultContext()
            ));
        }
    }
}
