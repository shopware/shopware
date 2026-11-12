<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Cart\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Cart\Error\PaymentMethodBlockedError;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PaymentMethodBlockedError::class)]
class PaymentMethodBlockedErrorTest extends TestCase
{
    public function testConstruct(): void
    {
        $error = new PaymentMethodBlockedError(
            id: Uuid::randomHex(),
            name: 'FOO',
            reason: 'BAR',
        );

        static::assertSame('Payment method FOO not available. Reason: BAR', $error->getMessage());
        static::assertFalse($error->isPersistent());
        static::assertSame([
            'id' => $error->getPaymentMethodId(),
            'name' => 'FOO',
            'reason' => 'BAR',
            'transactionPosition' => null,
        ], $error->getParameters());
        static::assertNull($error->getTransactionPosition());
        static::assertSame('FOO', $error->getName());
        static::assertTrue($error->blockOrder());
        static::assertSame('payment-method-blocked-' . $error->getPaymentMethodId(), $error->getId());
        static::assertSame(10, $error->getLevel());
        static::assertSame('payment-method-blocked', $error->getMessageKey());
    }

    public function testReferencesTheTransactionWithoutChangingTheId(): void
    {
        $paymentMethodId = Uuid::randomHex();

        $error = new PaymentMethodBlockedError(
            name: 'FOO',
            reason: 'BAR',
            id: $paymentMethodId,
            transactionPosition: 1,
        );

        static::assertSame(1, $error->getTransactionPosition());
        static::assertSame(1, $error->getParameters()['transactionPosition']);
        static::assertSame('payment-method-blocked-' . $paymentMethodId, $error->getId());
    }
}
