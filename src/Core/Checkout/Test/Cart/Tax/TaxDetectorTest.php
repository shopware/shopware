<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Tax;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class TaxDetectorTest extends TestCase
{
    public function testUseGrossPrices(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $customerGroup = $this->createMock(CustomerGroupEntity::class);
        $customerGroup->expects(static::once())->method('getDisplayGross')->willReturn(true);
        $context->expects(static::once())->method('getCurrentCustomerGroup')->willReturn($customerGroup);

        $detector = new TaxDetector();
        static::assertTrue($detector->useGross($context));
    }

    public function testDoNotUseGrossPrices(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $customerGroup = $this->createMock(CustomerGroupEntity::class);
        $customerGroup->expects(static::once())->method('getDisplayGross')->willReturn(false);
        $context->expects(static::once())->method('getCurrentCustomerGroup')->willReturn($customerGroup);

        $detector = new TaxDetector();
        static::assertFalse($detector->useGross($context));
    }

    public function testIsNetDelivery(): void
    {
        $context = $this->createMock(SalesChannelContext::class);

        $country = new CountryEntity();
        $country->setTaxFree(true);

        $context->expects(static::once())->method('getShippingLocation')->willReturn(
            ShippingLocation::createFromCountry($country)
        );

        $detector = new TaxDetector();
        static::assertTrue($detector->isNetDelivery($context));
    }

    public function testIsNotNetDelivery(): void
    {
        $context = $this->createMock(SalesChannelContext::class);

        $country = new CountryEntity();
        $country->setTaxFree(false);

        $context->expects(static::once())->method('getShippingLocation')->willReturn(
            ShippingLocation::createFromCountry($country)
        );

        $detector = new TaxDetector();
        static::assertFalse($detector->isNetDelivery($context));
    }
}
