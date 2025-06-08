<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Cart\PaymentHandler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\CashPayment;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CashPayment::class)]
class CashPaymentTest extends TestCase
{
    public function testPay(): void
    {
        $payment = new CashPayment();
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
        $payment = new CashPayment();

        foreach (PaymentHandlerType::cases() as $case) {
            static::assertFalse($payment->supports(
                $case,
                Uuid::randomHex(),
                Context::createDefaultContext()
            ));
        }
    }
}
