<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Tax;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class TaxDetectorTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testUseGrossPrices(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $customerGroup = $this->createMock(CustomerGroupEntity::class);
        $customerGroup->expects(static::once())->method('getDisplayGross')->willReturn(true);
        $context->expects(static::once())->method('getCurrentCustomerGroup')->willReturn($customerGroup);

        $detector = $this->getContainer()->get(TaxDetector::class);
        static::assertTrue($detector->useGross($context));
    }

    public function testDoNotUseGrossPrices(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $customerGroup = $this->createMock(CustomerGroupEntity::class);
        $customerGroup->expects(static::once())->method('getDisplayGross')->willReturn(false);
        $context->expects(static::once())->method('getCurrentCustomerGroup')->willReturn($customerGroup);

        $detector = $this->getContainer()->get(TaxDetector::class);
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

        $detector = $this->getContainer()->get(TaxDetector::class);
        static::assertTrue($detector->isNetDelivery($context));
    }

    public function testIsNetDeliveryWithCompanyFreeTax(): void
    {
        $context = $this->createMock(SalesChannelContext::class);

        $countryRepository = $this->getContainer()->get('country.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('iso', 'DE'));
        $criteria->setLimit(1);

        $country = $countryRepository->search($criteria, Context::createDefaultContext())->first();
        $countryRepository->update([
            [
                'id' => $country->getId(),
                'taxFree' => false,
                'companyTaxFree' => true,
                'vatIdPattern' => '(DE)?[0-9]{9}',
            ],
        ], Context::createDefaultContext());
        $country = $countryRepository->search($criteria, Context::createDefaultContext())->first();

        $customer = $this->createMock(CustomerEntity::class);
        $customer->expects(static::once())->method('getCompany')->willReturn('ABC Company');
        $customer->expects(static::once())->method('getVatIds')->willReturn(['DE123123123']);

        $context->expects(static::once())->method('getShippingLocation')->willReturn(
            ShippingLocation::createFromCountry($country)
        );

        $context->expects(static::once())->method('getCustomer')->willReturn(
            $customer
        );

        $taxDetector = $this->getContainer()->get(TaxDetector::class);

        static::assertTrue($taxDetector->isNetDelivery($context));
    }

    public function testIsNotNetDeliveryWithCompanyFreeTax(): void
    {
        $context = $this->createMock(SalesChannelContext::class);

        $countryRepository = $this->getContainer()->get('country.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('iso', 'DE'));
        $criteria->setLimit(1);

        $country = $countryRepository->search($criteria, Context::createDefaultContext())->first();
        $countryRepository->update([
            [
                'id' => $country->getId(),
                'taxFree' => false,
                'companyTaxFree' => false,
                'vatIdPattern' => '(DE)?[0-9]{9}',
                'checkVatIdPattern' => false,
            ],
        ], Context::createDefaultContext());
        $country = $countryRepository->search($criteria, Context::createDefaultContext())->first();

        $context->expects(static::once())->method('getShippingLocation')->willReturn(
            ShippingLocation::createFromCountry($country)
        );

        $detector = $this->getContainer()->get(TaxDetector::class);
        static::assertFalse($detector->isNetDelivery($context));
    }

    public function testIsNotNetDeliveryWithCompanyFreeTaxAndVatIdPattern(): void
    {
        $context = $this->createMock(SalesChannelContext::class);

        $countryRepository = $this->getContainer()->get('country.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('iso', 'DE'));
        $criteria->setLimit(1);

        $deCountry = $countryRepository->search($criteria, Context::createDefaultContext())->first();
        $countryRepository->update([
            [
                'id' => $deCountry->getId(),
                'taxFree' => false,
                'companyTaxFree' => true,
                'vatIdPattern' => '(DE)?[0-9]{9}',
                'checkVatIdPattern' => false,
            ],
        ], Context::createDefaultContext());
        $deCountry = $countryRepository->search($criteria, Context::createDefaultContext())->first();

        $customer = $this->createMock(CustomerEntity::class);
        $customer->expects(static::once())->method('getCompany')->willReturn('ABC Company');
        $customer->expects(static::once())->method('getVatIds')->willReturn(['VN123123']);

        $context->expects(static::once())->method('getShippingLocation')->willReturn(
            ShippingLocation::createFromCountry($deCountry)
        );

        $context->expects(static::once())->method('getCustomer')->willReturn(
            $customer
        );

        $detector = $this->getContainer()->get(TaxDetector::class);
        static::assertFalse($detector->isNetDelivery($context));
    }

    public function testIsNotNetDelivery(): void
    {
        $context = $this->createMock(SalesChannelContext::class);

        $countryRepository = $this->getContainer()->get('country.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('iso', 'DE'));
        $criteria->setLimit(1);

        $country = $countryRepository->search($criteria, Context::createDefaultContext())->first();
        $countryRepository->update([
            [
                'id' => $country->getId(),
                'taxFree' => false,
                'companyTaxFree' => false,
                'vatIdPattern' => '(DE)?[0-9]{9}',
                'checkVatIdPattern' => false,
            ],
        ], Context::createDefaultContext());
        $country = $countryRepository->search($criteria, Context::createDefaultContext())->first();

        $context->expects(static::once())->method('getShippingLocation')->willReturn(
            ShippingLocation::createFromCountry($country)
        );

        $detector = $this->getContainer()->get(TaxDetector::class);
        static::assertFalse($detector->isNetDelivery($context));
    }
}
