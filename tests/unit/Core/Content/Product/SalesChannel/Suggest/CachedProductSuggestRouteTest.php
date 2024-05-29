<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Suggest;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Product\Events\ProductSuggestRouteCacheKeyEvent;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Suggest\AbstractProductSuggestRoute;
use Shopware\Core\Content\Product\SalesChannel\Suggest\CachedProductSuggestRoute;
use Shopware\Core\Content\Product\SalesChannel\Suggest\ProductSuggestRouteResponse;
use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Tax\TaxCollection;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 */
#[CoversClass(CachedProductSuggestRoute::class)]
class CachedProductSuggestRouteTest extends TestCase
{
    private MockObject&AbstractProductSuggestRoute $decorated;

    private MockObject&CacheInterface $cache;

    private EventDispatcher $eventDispatcher;

    private CachedProductSuggestRoute $cachedRout;

    private SalesChannelContext $context;

    protected function setUp(): void
    {
        Feature::skipTestIfActive('cache_rework', $this);
        $this->decorated = $this->createMock(AbstractProductSuggestRoute::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->eventDispatcher = new EventDispatcher();
        $this->context = new SalesChannelContext(
            new Context(new SalesChannelApiSource(Uuid::randomHex())),
            Uuid::randomHex(),
            null,
            new SalesChannelEntity(),
            new CurrencyEntity(),
            new CustomerGroupEntity(),
            new TaxCollection(),
            new PaymentMethodEntity(),
            new ShippingMethodEntity(),
            new ShippingLocation(new CountryEntity(), null, null),
            new CustomerEntity(),
            new CashRoundingConfig(1, 1.1, true),
            new CashRoundingConfig(1, 1.1, true)
        );

        $this->cachedRout = new CachedProductSuggestRoute(
            $this->decorated,
            $this->cache,
            $this->createMock(EntityCacheKeyGenerator::class),
            $this->createMock(AbstractCacheTracer::class),
            $this->eventDispatcher,
            []
        );
    }

    public function testLoadWithDisabledCacheWillCallDecoratedRoute(): void
    {
        $this->decorated
            ->expects(static::once())
            ->method('load')
            ->willReturn($this->createMock(ProductSuggestRouteResponse::class));
        $this->cache
            ->expects(static::never())
            ->method('get');
        $this->eventDispatcher->addListener(
            ProductSuggestRouteCacheKeyEvent::class,
            fn (ProductSuggestRouteCacheKeyEvent $event) => $event->disableCaching()
        );

        $this->cachedRout->load(new Request(), $this->context, new Criteria());
    }

    public function testLoadWithEnabledCacheWillReturnDataFromCache(): void
    {
        $this->decorated
            ->expects(static::never())
            ->method('load');
        $this->cache
            ->expects(static::once())
            ->method('get')
            ->willReturn(
                CacheValueCompressor::compress(
                    new ProductSuggestRouteResponse(new ProductEntity())
                )
            );
        $this->eventDispatcher->addListener(
            ProductSuggestRouteCacheKeyEvent::class,
            fn (ProductSuggestRouteCacheKeyEvent $event) => $event
        );

        $this->cachedRout->load(new Request(), $this->context, new Criteria());
    }
}
