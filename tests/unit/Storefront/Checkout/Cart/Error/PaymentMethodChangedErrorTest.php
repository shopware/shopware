<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Checkout\Cart\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Storefront\Checkout\Cart\Error\PaymentMethodChangedError;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PaymentMethodChangedError::class)]
class PaymentMethodChangedErrorTest extends TestCase
{
    public function testConstruct(): void
    {
        $error = new PaymentMethodChangedError(
            oldPaymentMethodName: 'Invoice',
            newPaymentMethodName: 'Cash on delivery',
            oldPaymentMethodId: 'old-payment-method-id',
            newPaymentMethodId: 'new-payment-method-id',
            reason: 'Blocked by rule',
        );

        static::assertSame(
            'Invoice payment is not available for your current cart, the payment was changed to Cash on delivery. Reason: Blocked by rule',
            $error->getMessage()
        );
        static::assertSame('payment-method-changed-old-payment-method-id-new-payment-method-id', $error->getId());
        static::assertSame('payment-method-changed', $error->getMessageKey());
        static::assertSame(0, $error->getLevel());
        static::assertFalse($error->blockOrder());
        static::assertTrue($error->isPersistent());
        static::assertSame([
            'oldPaymentMethodId' => 'old-payment-method-id',
            'oldPaymentMethodName' => 'Invoice',
            'newPaymentMethodId' => 'new-payment-method-id',
            'newPaymentMethodName' => 'Cash on delivery',
            'reason' => 'Blocked by rule',
        ], $error->getParameters());
        static::assertSame('old-payment-method-id', $error->getOldPaymentMethodId());
        static::assertSame('Invoice', $error->getOldPaymentMethodName());
        static::assertSame('new-payment-method-id', $error->getNewPaymentMethodId());
        static::assertSame('Cash on delivery', $error->getNewPaymentMethodName());
        static::assertSame('Blocked by rule', $error->getReason());
    }

    public function testConstructWithoutIdsAndReasonThrows(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Passing null for $oldPaymentMethodId, $newPaymentMethodId, or $reason is deprecated and will not be allowed in v6.8.0.0. Please provide valid string values for both parameters.'
        ));

        new PaymentMethodChangedError('Invoice', 'Cash on delivery');
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testIdUsesNamesWhenMajorFlagIsInactive(): void
    {
        $error = new PaymentMethodChangedError(
            oldPaymentMethodName: 'Invoice',
            newPaymentMethodName: 'Cash on delivery',
            oldPaymentMethodId: 'old-payment-method-id',
            newPaymentMethodId: 'new-payment-method-id',
            reason: 'Blocked by rule',
        );

        static::assertSame('payment-method-changed-Invoice-Cash on delivery', $error->getId());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    #[IgnoreDeprecations]
    public function testConstructWithoutIdsAndReasonWhenMajorFlagIsInactive(): void
    {
        $this->expectUserDeprecationMessage('Passing null for $oldPaymentMethodId, $newPaymentMethodId, or $reason is deprecated and will not be allowed in v6.8.0.0. Please provide valid string values for both parameters.');

        $error = new PaymentMethodChangedError('Invoice', 'Cash on delivery');

        static::assertSame(
            'Invoice payment is not available for your current cart, the payment was changed to Cash on delivery. Reason: No reason provided.',
            $error->getMessage()
        );
        static::assertSame('payment-method-changed-Invoice-Cash on delivery', $error->getId());
        static::assertSame([
            'oldPaymentMethodId' => null,
            'oldPaymentMethodName' => 'Invoice',
            'newPaymentMethodId' => null,
            'newPaymentMethodName' => 'Cash on delivery',
            'reason' => null,
        ], $error->getParameters());
        static::assertNull($error->getOldPaymentMethodId());
        static::assertNull($error->getNewPaymentMethodId());
        static::assertNull($error->getReason());
    }
}
