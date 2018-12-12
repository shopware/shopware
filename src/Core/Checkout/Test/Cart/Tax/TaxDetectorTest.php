<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Tax;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\System\Country\CountryEntity;

class TaxDetectorTest extends TestCase
{
    public function testUseGrossPrices(): void
    {
        $context = $this->createMock(CheckoutContext::class);
        $customerGroup = $this->createMock(CustomerGroupEntity::class);
        $customerGroup->expects(static::once())->method('getDisplayGross')->will(static::returnValue(true));
        $context->expects(static::once())->method('getCurrentCustomerGroup')->will(static::returnValue($customerGroup));

        $detector = new TaxDetector();
        static::assertTrue($detector->useGross($context));
    }

    public function testDoNotUseGrossPrices(): void
    {
        $context = $this->createMock(CheckoutContext::class);
        $customerGroup = $this->createMock(CustomerGroupEntity::class);
        $customerGroup->expects(static::once())->method('getDisplayGross')->will(static::returnValue(false));
        $context->expects(static::once())->method('getCurrentCustomerGroup')->will(static::returnValue($customerGroup));

        $detector = new TaxDetector();
        static::assertFalse($detector->useGross($context));
    }

    public function testIsNetDelivery(): void
    {
        $context = $this->createMock(CheckoutContext::class);

        $country = new CountryEntity();
        $country->setTaxFree(true);

        $context->expects(static::once())->method('getShippingLocation')->will(
            static::returnValue(
            ShippingLocation::createFromCountry($country)
        ));

        $detector = new TaxDetector();
        static::assertTrue($detector->isNetDelivery($context));
    }

    public function testIsNotNetDelivery(): void
    {
        $context = $this->createMock(CheckoutContext::class);

        $country = new CountryEntity();
        $country->setTaxFree(false);

        $context->expects(static::once())->method('getShippingLocation')->will(
            static::returnValue(
            ShippingLocation::createFromCountry($country)
        ));

        $detector = new TaxDetector();
        static::assertFalse($detector->isNetDelivery($context));
    }
}
