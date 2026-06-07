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
#[CoversClass(PromotionNotEligibleError::class)]
#[Package('checkout')]
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
