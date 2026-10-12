<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Promotion\Cart\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Promotion\Cart\Error\PromotionNotEligibleError;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PromotionNotEligibleError::class)]
class PromotionNotEligibleErrorTest extends TestCase
{
    public function testDefaultConstructor(): void
    {
        $error = new PromotionNotEligibleError('test-promotion');

        static::assertSame('promotion-not-eligible', $error->getMessageKey());
        static::assertSame('promotion-not-eligible', $error->getId());
        static::assertSame([], $error->getRuleIds());
        static::assertSame(Error::LEVEL_NOTICE, $error->getLevel());
        static::assertFalse($error->isPersistent());
        static::assertFalse($error->blockOrder());
        static::assertSame(['name' => 'test-promotion'], $error->getParameters());
    }

    public function testNotLoggedInReason(): void
    {
        $error = new PromotionNotEligibleError('my-promo', 'not-logged-in');

        static::assertSame('promotion-not-eligible-not-logged-in', $error->getMessageKey());
        static::assertSame('promotion-not-eligible', $error->getId());
        static::assertSame([], $error->getRuleIds());
    }

    public function testSpecificProductsReason(): void
    {
        $error = new PromotionNotEligibleError('my-promo', 'specific-products');

        static::assertSame('promotion-not-eligible-specific-products', $error->getMessageKey());
        static::assertSame('promotion-not-eligible', $error->getId());
    }

    public function testReasonProducesReasonSpecificMessageKey(): void
    {
        $error = new PromotionNotEligibleError('my-promo', 'some-reason');

        static::assertSame('promotion-not-eligible-some-reason', $error->getMessageKey());
        static::assertSame('promotion-not-eligible', $error->getId());
        static::assertFalse($error->isPersistent());
    }

    public function testAlreadyRedeemedReasonIsPersistent(): void
    {
        $error = new PromotionNotEligibleError('TESTCODE', 'already-redeemed', [], true);

        static::assertSame('promotion-not-eligible-already-redeemed', $error->getMessageKey());
        static::assertSame('promotion-not-eligible', $error->getId());
        static::assertSame(['name' => 'TESTCODE'], $error->getParameters());
        // must be persistent so a code removed by the collector survives cart recalculation
        static::assertTrue($error->isPersistent());
    }

    public function testWithRuleIds(): void
    {
        $error = new PromotionNotEligibleError('my-promo', null, ['rule-id-1', 'rule-id-2']);

        static::assertSame('promotion-not-eligible', $error->getMessageKey());
        static::assertSame(['rule-id-1', 'rule-id-2'], $error->getRuleIds());
    }

    public function testNullReasonProducesBaseKey(): void
    {
        $error = new PromotionNotEligibleError('my-promo', null);

        static::assertSame('promotion-not-eligible', $error->getMessageKey());
    }
}
