<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheKeyEvent;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Tax\TaxCollection;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(StoreApiRouteCacheKeyEvent::class)]
class StoreApiRouteCacheKeyEventTest extends TestCase
{
    private SalesChannelContext $context;

    private Request $request;

    private StoreApiRouteCacheKeyEvent $defaultEvent;

    private SalesChannelEntity $salesChannelEntity;

    protected function setUp(): void
    {
        $this->request = new Request();
        $this->salesChannelEntity = new SalesChannelEntity();
        $this->salesChannelEntity->setId(Uuid::randomHex());
        $this->context = new SalesChannelContext(
            new Context(new SalesChannelApiSource(Uuid::randomHex())),
            Uuid::randomHex(),
            null,
            $this->salesChannelEntity,
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

        $this->defaultEvent = new StoreApiRouteCacheKeyEvent([], $this->request, $this->context, null);
    }

    public function testGetPartsWillReturnConstructorValue(): void
    {
        $parts = [
            Uuid::randomHex(),
            Uuid::randomHex(),
        ];
        $event = new StoreApiRouteCacheKeyEvent($parts, $this->request, $this->context, null);
        static::assertEquals($parts, $event->getParts());
    }

    public function testSetPartsWillGetPartsReturnSetterValue(): void
    {
        static::assertEquals([], $this->defaultEvent->getParts());
        $parts = [
            Uuid::randomHex(),
            Uuid::randomHex(),
        ];
        $this->defaultEvent->setParts($parts);
        static::assertEquals($parts, $this->defaultEvent->getParts());
    }

    public function testGetRequestWillReturnCorrectRequest(): void
    {
        static::assertEquals($this->request, $this->defaultEvent->getRequest());
    }

    public function testGetCriteriaWithCriteriaWillReturnCriteria(): void
    {
        $criteria = new Criteria();
        $event = new StoreApiRouteCacheKeyEvent([], $this->request, $this->context, $criteria);
        static::assertEquals($criteria, $event->getCriteria());
    }

    public function testGetCriteriaWithNullInCriteriaWillReturnNull(): void
    {
        static::assertNull($this->defaultEvent->getCriteria());
    }

    public function testGetSalesChannelIdWillReturnChannelIdFromGivenContext(): void
    {
        static::assertEquals($this->salesChannelEntity->getId(), $this->defaultEvent->getSalesChannelId());
    }

    public function testDisableCachingWillDisableCache(): void
    {
        static::assertTrue($this->defaultEvent->shouldCache());
        $this->defaultEvent->disableCaching();
        static::assertFalse($this->defaultEvent->shouldCache());
    }
}
