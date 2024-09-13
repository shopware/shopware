<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Cart\PaymentHandler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\DefaultPayment;
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
#[CoversClass(DefaultPayment::class)]
class DefaultPaymentTest extends TestCase
{
    protected function setUp(): void
    {
        Feature::skipTestIfInActive('v6.7.0.0', $this);
        if (!\is_a(DefaultPayment::class, AbstractPaymentHandler::class, true)) {
            static::markTestSkipped(\sprintf('Class %s must extend %s', DefaultPayment::class, AbstractPaymentHandler::class));
        }
    }

    public function testPay(): void
    {
        $payment = new DefaultPayment();
        $reponse = $payment->pay(
            new Request(),
            new PaymentTransactionStruct(Uuid::randomHex()),
            Context::createDefaultContext(),
            null,
        );

        static::assertNull($reponse);
    }

    public function testSupports(): void
    {
        $payment = new DefaultPayment();

        foreach (PaymentHandlerType::cases() as $case) {
            static::assertFalse($payment->supports(
                $case,
                Uuid::randomHex(),
                Context::createDefaultContext()
            ));
        }
    }
}
