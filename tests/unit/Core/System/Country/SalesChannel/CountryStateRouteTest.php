<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Country\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Adapter\Cache\Event\AddCacheTagEvent;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateCollection;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Country\Event\CountryStateCriteriaEvent;
use Shopware\Core\System\Country\SalesChannel\CountryStateRoute;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Tax\TaxCollection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(CountryStateRoute::class)]
class CountryStateRouteTest extends TestCase
{
    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());

        $this->salesChannelContext = new SalesChannelContext(
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
    }

    public function testLoad(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects(static::exactly(2))
            ->method('dispatch')
            ->with(static::callback(static function ($event) use (&$index) {
                switch ($index) {
                    case 0:
                        ++$index;
                        static::assertInstanceOf(AddCacheTagEvent::class, $event);

                        return true;
                    case 1:
                        ++$index;
                        static::assertInstanceOf(CountryStateCriteriaEvent::class, $event);

                        return true;
                    default:
                        static::fail('Unexpected event dispatched');
                }
            }));

        $countryStateRepository = $this->createMock(EntityRepository::class);
        $countryStateRepository->expects(static::once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                'country_state',
                0,
                new CountryStateCollection(),
                null,
                new Criteria(),
                $this->salesChannelContext->getContext(),
            ));

        $route = new CountryStateRoute($countryStateRepository, $dispatcher);
        $route->load(Uuid::randomHex(), new Request(), new Criteria(), $this->salesChannelContext);
    }
}
