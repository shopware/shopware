<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Product\QuickView;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRouteResponse;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Page\Product\QuickView\MinimalQuickViewPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MinimalQuickViewPageLoader::class)]
class MinimalQuickViewPageLoaderTest extends TestCase
{
    public function testItTellsTheRouteToSkipTheBreadcrumb(): void
    {
        // the quick view renders no breadcrumb, so it must not pay for building one
        $request = new Request([], [], ['productId' => Uuid::randomHex()]);

        $this->load($request, $passedRequest);

        static::assertNotNull($passedRequest);
        static::assertTrue($passedRequest->attributes->get(ProductDetailRoute::SKIP_BREADCRUMB));
    }

    public function testItIgnoresAClientSuppliedReferrerCategory(): void
    {
        // the quick view renders no category path, so a referrer in the url must not change the resolved category
        $request = new Request(
            [ProductDetailRoute::REFERRER_CATEGORY_ID => Uuid::randomHex()],
            [],
            ['productId' => Uuid::randomHex()]
        );

        $this->load($request, $passedRequest);

        static::assertNotNull($passedRequest);
        static::assertTrue($passedRequest->attributes->has(ProductDetailRoute::REFERRER_CATEGORY_ID));
        static::assertNull($passedRequest->attributes->get(ProductDetailRoute::REFERRER_CATEGORY_ID));
    }

    public function testItDoesNotMutateTheCallersRequest(): void
    {
        $request = new Request([], [], ['productId' => Uuid::randomHex()]);

        $this->load($request, $passedRequest);

        static::assertNotSame($request, $passedRequest);
        static::assertFalse($request->attributes->has(ProductDetailRoute::SKIP_BREADCRUMB));
    }

    public function testItRequiresAProductId(): void
    {
        $this->expectException(RoutingException::class);

        $this->load(new Request(), $passedRequest);
    }

    private function load(Request $request, ?Request &$passedRequest): void
    {
        $passedRequest = null;

        $product = new SalesChannelProductEntity();
        $product->setId(Uuid::randomHex());
        $product->setUniqueIdentifier('product');

        $productRoute = static::createStub(AbstractProductDetailRoute::class);
        $productRoute
            ->method('load')
            ->willReturnCallback(function (string $productId, Request $loadRequest, SalesChannelContext $context, Criteria $criteria) use (&$passedRequest, $product): ProductDetailRouteResponse {
                $passedRequest = $loadRequest;

                return new ProductDetailRouteResponse($product, null);
            });

        $loader = new MinimalQuickViewPageLoader(new EventDispatcher(), $productRoute);

        $loader->load($request, Generator::generateSalesChannelContext());
    }
}
