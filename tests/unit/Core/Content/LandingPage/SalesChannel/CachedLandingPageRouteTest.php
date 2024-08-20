<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\LandingPage\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\LandingPage\Event\LandingPageRouteCacheKeyEvent;
use Shopware\Core\Content\LandingPage\LandingPageEntity;
use Shopware\Core\Content\LandingPage\SalesChannel\AbstractLandingPageRoute;
use Shopware\Core\Content\LandingPage\SalesChannel\CachedLandingPageRoute;
use Shopware\Core\Content\LandingPage\SalesChannel\LandingPageRouteResponse;
use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
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
 * @deprecated tag:v6.7.0 - Remove full class
 *
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(CachedLandingPageRoute::class)]
class CachedLandingPageRouteTest extends TestCase
{
    private MockObject&AbstractLandingPageRoute $decorated;

    private MockObject&CacheInterface $cache;

    private EventDispatcher $eventDispatcher;

    private CachedLandingPageRoute $cachedRoute;

    private SalesChannelContext $context;

    private LandingPageRouteResponse $response;

    protected function setUp(): void
    {
        Feature::skipTestIfActive('cache_rework', $this);
        $this->decorated = $this->createMock(AbstractLandingPageRoute::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->eventDispatcher = new EventDispatcher();
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());
        $this->context = new SalesChannelContext(
            new Context(new SalesChannelApiSource(Uuid::randomHex())),
            Uuid::randomHex(),
            null,
            $salesChannel,
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
        $this->response = new LandingPageRouteResponse(
            new LandingPageEntity()
        );

        $this->cachedRoute = new CachedLandingPageRoute(
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
            ->willReturn($this->response);
        $this->cache
            ->expects(static::never())
            ->method('get');
        $this->eventDispatcher->addListener(
            LandingPageRouteCacheKeyEvent::class,
            fn (LandingPageRouteCacheKeyEvent $event) => $event->disableCaching()
        );

        $this->cachedRoute->load('', new Request(), $this->context);
    }

    public function testLoadWithEnabledCacheWillReturnDataFromCache(): void
    {
        $this->decorated
            ->expects(static::never())
            ->method('load');
        $this->cache
            ->expects(static::once())
            ->method('get')
            ->willReturn(CacheValueCompressor::compress($this->response));
        $this->eventDispatcher->addListener(
            LandingPageRouteCacheKeyEvent::class,
            fn (LandingPageRouteCacheKeyEvent $event) => $event
        );

        $this->cachedRoute->load('', new Request(), $this->context);
    }
}
