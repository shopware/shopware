<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Wishlist;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlist\CustomerWishlistEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLoadWishlistRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\LoadWishlistRouteResponse;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Shopware\Storefront\Page\Page;
use Shopware\Storefront\Page\Wishlist\WishlistPageLoader;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(WishlistPageLoader::class)]
class WishlistPageLoaderTest extends TestCase
{
    #[DataProvider('pageParameterProvider')]
    public function testLoadUsesNonNegativeOffsetForPageParameter(string $pageParameter, int $expectedOffset): void
    {
        $context = Generator::generateSalesChannelContext();
        $customer = new CustomerEntity();

        $genericLoader = static::createStub(GenericPageLoaderInterface::class);
        $genericLoader->method('load')->willReturn(new Page());

        $wishlistRoute = $this->createMock(AbstractLoadWishlistRoute::class);
        $wishlistRoute
            ->expects($this->once())
            ->method('load')
            ->with(
                static::isInstanceOf(Request::class),
                $context,
                static::callback(static fn (Criteria $criteria) => $criteria->getOffset() === $expectedOffset),
                $customer,
            )
            ->willReturn(new LoadWishlistRouteResponse(
                new CustomerWishlistEntity(),
                new EntitySearchResult('product', 0, new ProductCollection(), null, new Criteria(), Context::createDefaultContext())
            ));

        $loader = new WishlistPageLoader($genericLoader, $wishlistRoute, new CollectingEventDispatcher());

        $loader->load(new Request(['p' => $pageParameter]), $context, $customer);
    }

    public static function pageParameterProvider(): \Generator
    {
        yield 'negative page falls back to the first page' => ['-5', 0];
        yield 'zero page falls back to the first page' => ['0', 0];
        yield 'non-numeric page falls back to the first page' => ['foo', 0];
        yield 'valid page is converted to its offset' => ['3', 48];
    }
}
