<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Routing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Storefront\Framework\Routing\StorefrontResponse;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Framework\Routing\StorefrontResponse
 */
class StorefrontResponseTest extends TestCase
{
    public function testInit(): void
    {
        $response = new StorefrontResponse();

        static::assertIsArray($response->getData());
        static::assertEmpty($response->getData());

        static::assertNull($response->getContext());
    }

    public function testSetData(): void
    {
        $response = new StorefrontResponse();

        $response->setData([]);
        static::assertEmpty($response->getData());

        /** @deprecated tag:v6.6.0 - This can be removed if parameter `$data` will be strictly typed to `array` in `setData` */
        $this->expectDeprecationMessageMatches('/deprecated functionality:/');
        $response->setData(null);
    }

    public function testSetContext(): void
    {
        $response = new StorefrontResponse();

        $salesChannelContext = $this->createSalesChannelContext();
        $response->setContext($salesChannelContext);
        $retrievedSalesChannelContext = $response->getContext();

        static::assertInstanceOf(SalesChannelContext::class, $retrievedSalesChannelContext);
        static::assertEquals('foo', $retrievedSalesChannelContext->getToken());
    }

    private function createSalesChannelContext(): SalesChannelContext
    {
        return new SalesChannelContext(
            Context::createDefaultContext(),
            'foo',
            'bar',
            new SalesChannelEntity(),
            new CurrencyEntity(),
            new CustomerGroupEntity(),
            new TaxCollection(),
            new PaymentMethodEntity(),
            new ShippingMethodEntity(),
            new ShippingLocation(new CountryEntity(), null, null),
            new CustomerEntity(),
            new CashRoundingConfig(2, 0.01, true),
            new CashRoundingConfig(2, 0.01, true)
        );
    }
}
