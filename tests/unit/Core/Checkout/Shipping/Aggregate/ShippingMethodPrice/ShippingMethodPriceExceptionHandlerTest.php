<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceExceptionHandler;
use Shopware\Core\Checkout\Shipping\ShippingException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ShippingMethodPriceExceptionHandler::class)]
class ShippingMethodPriceExceptionHandlerTest extends TestCase
{
    public function testPriority(): void
    {
        $handler = new ShippingMethodPriceExceptionHandler();

        static::assertSame(0, $handler->getPriority());
    }

    public function testSqlExceptionHandled(): void
    {
        $message = 'An exception occurred while executing a query: SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'foo-bar\' for key \'shipping_method_price.uniq.shipping_method_quantity_start\'';

        $e = new \Exception($message);

        $handler = new ShippingMethodPriceExceptionHandler();
        $result = $handler->matchException($e);

        static::assertInstanceOf(ShippingException::class, $result);
        static::assertSame('Shipping method price quantity already exists.', $result->getMessage());
    }

    public function testNonAlignedSqlExceptionNotHandled(): void
    {
        $message = 'An exception occurred while executing a query: SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'foo-bar\' for key \'product.uniq.product.product_number__version_id\'';
        $e = new \Exception($message);

        $handler = new ShippingMethodPriceExceptionHandler();
        $result = $handler->matchException($e);

        static::assertNull($result);
    }
}
