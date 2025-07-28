<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\TaxFreeConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
class CartRuleLoaderTaxTypeTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testDetectTaxTypeB2CCustomerWithB2CTaxFreeEnabledAndB2BTaxFreeDisabled(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        
        $country = new CountryEntity();
        $country->setCustomerTax(new TaxFreeConfig(true, Defaults::CURRENCY, 0)); // B2C tax free enabled
        $country->setCompanyTax(new TaxFreeConfig(false, Defaults::CURRENCY, 0)); // B2B tax free disabled

        // B2C customer (no company)
        $customer = new CustomerEntity();
        // No company set for B2C customer

        $shippingLocation = ShippingLocation::createFromCountry($country);
        
        $context->expects($this->once())->method('getShippingLocation')->willReturn($shippingLocation);
        $context->expects($this->once())->method('getCustomer')->willReturn($customer);
        
        // Mock currency to avoid tax free amount check
        $currency = new CurrencyEntity();
        $currency->setTaxFreeFrom(0);
        $context->expects($this->once())->method('getCurrency')->willReturn($currency);

        $cartRuleLoader = static::getContainer()->get(CartRuleLoader::class);
        
        // Use reflection to access the private detectTaxType method
        $reflectionClass = new \ReflectionClass($cartRuleLoader);
        $detectTaxTypeMethod = $reflectionClass->getMethod('detectTaxType');
        $detectTaxTypeMethod->setAccessible(true);
        
        $result = $detectTaxTypeMethod->invoke($cartRuleLoader, $context, 0);
        
        // B2C customer should get tax free when B2C tax free is enabled
        static::assertSame(CartPrice::TAX_STATE_FREE, $result);
    }

    public function testDetectTaxTypeB2BCustomerWithB2CTaxFreeEnabledAndB2BTaxFreeDisabled(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        
        $country = new CountryEntity();
        $country->setCustomerTax(new TaxFreeConfig(true, Defaults::CURRENCY, 0)); // B2C tax free enabled
        $country->setCompanyTax(new TaxFreeConfig(false, Defaults::CURRENCY, 0)); // B2B tax free disabled

        // B2B customer (has company)
        $customer = new CustomerEntity();
        $customer->setCompany('Test Company GmbH');

        $shippingLocation = ShippingLocation::createFromCountry($country);
        
        $context->expects($this->once())->method('getShippingLocation')->willReturn($shippingLocation);
        $context->expects($this->once())->method('getCustomer')->willReturn($customer);
        
        // Mock currency to avoid tax free amount check
        $currency = new CurrencyEntity();
        $currency->setTaxFreeFrom(0);
        $context->expects($this->once())->method('getCurrency')->willReturn($currency);

        $cartRuleLoader = static::getContainer()->get(CartRuleLoader::class);
        
        // Use reflection to access the private detectTaxType method
        $reflectionClass = new \ReflectionClass($cartRuleLoader);
        $detectTaxTypeMethod = $reflectionClass->getMethod('detectTaxType');
        $detectTaxTypeMethod->setAccessible(true);
        
        $result = $detectTaxTypeMethod->invoke($cartRuleLoader, $context, 0);
        
        // B2B customer should NOT get tax free when B2B tax free is disabled
        // even if B2C tax free is enabled
        static::assertNotSame(CartPrice::TAX_STATE_FREE, $result);
    }
}
