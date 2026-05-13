<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Shipping\Cart\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ShippingMethodBlockedError::class)]
class ShippingMethodBlockedErrorTest extends TestCase
{
    public function testConstruct(): void
    {
        $error = new ShippingMethodBlockedError(
            id: Uuid::randomHex(),
            name: 'FOO',
            reason: 'BAR',
        );

        static::assertSame('Shipping method FOO not available. Reason: BAR', $error->getMessage());
        static::assertFalse($error->isPersistent());
        static::assertSame([
            'id' => $error->getShippingMethodId(),
            'name' => 'FOO',
            'reason' => 'BAR',
        ], $error->getParameters());
        static::assertSame('FOO', $error->getName());
        static::assertTrue($error->blockOrder());
        static::assertSame('shipping-method-blocked-' . $error->getShippingMethodId(), $error->getId());
        static::assertSame(10, $error->getLevel());
        static::assertSame('shipping-method-blocked-with-reason', $error->getMessageKey());
    }

    #[DataProvider('messageKeyProvider')]
    public function testMessageKey(?string $reason, string $messageKey): void
    {
        $error = new ShippingMethodBlockedError(
            id: Uuid::randomHex(),
            name: 'FOO',
            reason: $reason,
        );

        static::assertSame($messageKey, $error->getMessageKey());
    }

    public static function messageKeyProvider(): \Generator
    {
        yield 'unknown reason' => ['BAR', 'shipping-method-blocked-with-reason'];
        yield 'no shipping costs found' => [ShippingMethodBlockedError::REASON_NO_SHIPPING_COSTS_FOUND, 'shipping-method-blocked-no-shipping-costs-found'];
        yield 'not allowed' => [ShippingMethodBlockedError::REASON_NOT_ALLOWED, 'shipping-method-blocked-not-allowed'];
        yield 'rule not matching or inactive' => [ShippingMethodBlockedError::REASON_RULE_NOT_MATCHING_OR_INACTIVE, 'shipping-method-blocked-rule-not-matching-or-inactive'];
    }
}
