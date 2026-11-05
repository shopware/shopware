<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Checkout\Cart\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Storefront\Checkout\Cart\Error\ShippingMethodChangedError;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ShippingMethodChangedError::class)]
class ShippingMethodChangedErrorTest extends TestCase
{
    public function testConstruct(): void
    {
        $error = new ShippingMethodChangedError(
            oldShippingMethodName: 'Standard',
            newShippingMethodName: 'Express',
            oldShippingMethodId: 'old-shipping-method-id',
            newShippingMethodId: 'new-shipping-method-id',
            reason: 'Blocked by rule',
        );

        static::assertSame(
            'Standard shipping is not available for your current cart, the shipping was changed to Express. Reason: Blocked by rule',
            $error->getMessage()
        );
        static::assertSame('shipping-method-changed-old-shipping-method-id-new-shipping-method-id', $error->getId());
        static::assertSame('shipping-method-changed', $error->getMessageKey());
        static::assertSame(0, $error->getLevel());
        static::assertFalse($error->blockOrder());
        static::assertTrue($error->isPersistent());
        static::assertSame([
            'oldShippingMethodId' => 'old-shipping-method-id',
            'oldShippingMethodName' => 'Standard',
            'newShippingMethodId' => 'new-shipping-method-id',
            'newShippingMethodName' => 'Express',
            'reason' => 'Blocked by rule',
        ], $error->getParameters());
        static::assertSame('old-shipping-method-id', $error->getOldShippingMethodId());
        static::assertSame('Standard', $error->getOldShippingMethodName());
        static::assertSame('new-shipping-method-id', $error->getNewShippingMethodId());
        static::assertSame('Express', $error->getNewShippingMethodName());
        static::assertSame('Blocked by rule', $error->getReason());
    }

    public function testConstructWithoutIdsAndReasonThrows(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Passing null for $oldShippingMethodId, $newShippingMethodId, or $reason is deprecated and will not be allowed in v6.8.0.0. Please provide valid string values for both parameters.'
        ));

        new ShippingMethodChangedError('Standard', 'Express');
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testIdUsesNamesWhenMajorFlagIsInactive(): void
    {
        $error = new ShippingMethodChangedError(
            oldShippingMethodName: 'Standard',
            newShippingMethodName: 'Express',
            oldShippingMethodId: 'old-shipping-method-id',
            newShippingMethodId: 'new-shipping-method-id',
            reason: 'Blocked by rule',
        );

        static::assertSame('shipping-method-changed-Standard-Express', $error->getId());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConstructWithoutIdsAndReasonFallsBackWhenMajorFlagIsInactive(): void
    {
        $error = new ShippingMethodChangedError('Standard', 'Express');

        static::assertSame(
            'Standard shipping is not available for your current cart, the shipping was changed to Express. Reason: No reason provided.',
            $error->getMessage()
        );
        static::assertSame('shipping-method-changed-Standard-Express', $error->getId());
        static::assertSame([
            'oldShippingMethodId' => null,
            'oldShippingMethodName' => 'Standard',
            'newShippingMethodId' => null,
            'newShippingMethodName' => 'Express',
            'reason' => null,
        ], $error->getParameters());
        static::assertNull($error->getOldShippingMethodId());
        static::assertNull($error->getNewShippingMethodId());
        static::assertNull($error->getReason());
    }
}
