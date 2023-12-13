<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Shipping\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\Hook\ShippingMethodRouteHook;
use Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRouteResponse;
use Shopware\Core\Checkout\Shipping\SalesChannel\SortedShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
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
#[CoversClass(SortedShippingMethodRoute::class)]
class SortedShippingMethodRouteTest extends TestCase
{
    private MockObject&AbstractShippingMethodRoute $decorated;

    private MockObject&ScriptExecutor $executor;

    private SortedShippingMethodRoute $sortedRoute;

    private SalesChannelContext $context;

    private ShippingMethodRouteResponse $response;

    protected function setUp(): void
    {
        $this->decorated = $this->createMock(AbstractShippingMethodRoute::class);
        $this->executor = $this->createMock(ScriptExecutor::class);
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());
        $salesChannel->setShippingMethodId(Uuid::randomHex());
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId($salesChannel->getShippingMethodId());
        $this->context = new SalesChannelContext(
            new Context(new SalesChannelApiSource(Uuid::randomHex())),
            Uuid::randomHex(),
            null,
            $salesChannel,
            new CurrencyEntity(),
            new CustomerGroupEntity(),
            new TaxCollection(),
            new PaymentMethodEntity(),
            $shippingMethod,
            new ShippingLocation(new CountryEntity(), null, null),
            new CustomerEntity(),
            new CashRoundingConfig(1, 1.1, true),
            new CashRoundingConfig(1, 1.1, true),
        );
        $this->response = new ShippingMethodRouteResponse(
            new EntitySearchResult(
                'entity',
                1,
                new ShippingMethodCollection([$shippingMethod]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );
        $this->sortedRoute = new SortedShippingMethodRoute($this->decorated, $this->executor);
    }

    public function testTriggersScriptHookExecution(): void
    {
        $this->decorated
            ->expects(static::once())
            ->method('load')
            ->willReturn($this->response);

        $this->executor->method('execute')->with(static::callback(fn (ShippingMethodRouteHook $hook) => $hook->getCollection() === $this->response->getShippingMethods()
            && $hook->getSalesChannelContext() === $this->context
            && $hook->isOnlyAvailable()));

        $response = $this->sortedRoute->load(new Request(['onlyAvailable' => true]), $this->context, new Criteria());
        static::assertCount(1, $response->getShippingMethods());
    }
}
