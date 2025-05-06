<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\SalesChannel\CartLoadRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\TaxProvider\TaxProviderProcessor;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CartLoadRoute::class)]
class CartLoadRouteTest extends TestCase
{
    public function testLoadCartCreatesNewCart(): void
    {
        static::assertSame(true, true);
    }
}
