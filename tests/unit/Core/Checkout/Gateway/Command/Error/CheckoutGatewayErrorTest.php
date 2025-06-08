<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Gateway\Command\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Gateway\Error\CheckoutGatewayError;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(CheckoutGatewayError::class)]
#[Package('checkout')]
class CheckoutGatewayErrorTest extends TestCase
{
    public function testConstruct(): void
    {
        $error = new CheckoutGatewayError('test', Error::LEVEL_NOTICE, true);

        static::assertSame('test', $error->getMessage());
        static::assertSame(Error::LEVEL_NOTICE, $error->getLevel());
        static::assertTrue($error->blockOrder());
        static::assertTrue(Uuid::isValid($error->getId()));
        static::assertSame('checkout-gateway-error', $error->getMessageKey());
        static::assertSame(['reason' => 'test'], $error->getParameters());
        static::assertFalse($error->isPersistent());
    }
}
