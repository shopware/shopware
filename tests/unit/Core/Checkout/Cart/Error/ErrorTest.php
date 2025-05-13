<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(Error::class)]
#[Package('checkout')]
class ErrorTest extends TestCase
{
    public function testSerialization(): void
    {
        $error = new ShippingMethodBlockedError('foo');

        static::assertEquals('foo', $error->getName());
        dump($error->getTrace());

        $serialized = serialize($error);

        $unserialized = unserialize($serialized);
        static::assertInstanceOf(ShippingMethodBlockedError::class, $unserialized);

        static::assertEquals('foo', $unserialized->getName());
    }
}
