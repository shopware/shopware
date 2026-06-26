<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Extension\ProductSearchRouteExtension;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchRouteResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Examples\ProductSearchRouteExample;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ProductSearchRouteExtension::class)]
#[CoversClass(ProductSearchRouteExample::class)]
class ProductSearchRouteExtensionTest extends TestCase
{
    public function testSubscriberResolvesSearch(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new ProductSearchRouteExample());

        $coreCalled = false;
        $result = (new ExtensionDispatcher($dispatcher))->publish(
            name: ProductSearchRouteExtension::NAME,
            extension: new ProductSearchRouteExtension(new Request(), $context, new Criteria()),
            function: static function () use (&$coreCalled): ProductSearchRouteResponse {
                $coreCalled = true;

                return new ProductSearchRouteResponse(new ProductListingResult(
                    ProductDefinition::ENTITY_NAME,
                    0,
                    new ProductCollection(),
                    null,
                    new Criteria(),
                    Context::createDefaultContext(),
                ));
            },
        );

        static::assertFalse($coreCalled, 'The core product search must be skipped when a subscriber resolves it.');
        static::assertInstanceOf(ProductSearchRouteResponse::class, $result);
    }
}
