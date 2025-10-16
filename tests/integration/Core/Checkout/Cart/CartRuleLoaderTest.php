<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\TaxFreeConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CartRuleLoader::class)]
class CartRuleLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    #[DataProvider('taxConfigProvider')]
    public function testTaxFreeConfig(string $accountType, bool $taxCustomerConfig, bool $taxBusinessConfig, string $expectedTaxConfig): void
    {
        /** @var CartRuleLoader $cartRuleLoader */
        $cartRuleLoader = static::getContainer()->get(CartRuleLoader::class);

        $customerGroup = new CustomerGroupEntity();
        $customerGroup->setId(Uuid::randomHex());
        $customerGroup->setDisplayGross(true);

        $customer = new CustomerEntity();
        $customer->setAccountType($accountType);
        $customer->setId(Uuid::randomHex());
        $customer->setGroup($customerGroup);
        $customer->setCompany('test-company');
        $customer->setVatIds(['DE123456789']);

        $country = new CountryEntity();
        $country->setId(Uuid::randomHex());
        $country->setActive(true);
        $country->setShippingAvailable(true);
        $country->setCheckVatIdPattern(false);

        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setTaxFreeFrom(0.0);
        $currency->setFactor(1.5);

        $taxCustomerConfig = new TaxFreeConfig($taxCustomerConfig);
        $country->setCustomerTax($taxCustomerConfig);

        $taxBusinessConfig = new TaxFreeConfig($taxBusinessConfig);
        $country->setCompanyTax($taxBusinessConfig);

        $salesChannelContext = Generator::generateSalesChannelContext(currency: $currency, currentCustomerGroup: $customerGroup, customer: $customer, country: $country);

        $cart = new Cart('test');

        $result = $cartRuleLoader->loadByCart($salesChannelContext, $cart, new CartBehavior());
        static::assertSame($expectedTaxConfig, $result->getCart()->getPrice()->getTaxStatus());
    }

    public static function taxConfigProvider(): \Generator
    {
        yield 'customer account + customer tax free => tax-free' => [
            CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            true,
            false,
            CartPrice::TAX_STATE_FREE,
        ];

        yield 'business account + customer tax free => no tax-free' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            true,
            false,
            CartPrice::TAX_STATE_GROSS,
        ];

        yield 'customer account + business tax free => no tax-free' => [
            CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            false,
            true,
            CartPrice::TAX_STATE_GROSS,
        ];

        yield 'business tax + business tax free => tax-free' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            false,
            true,
            CartPrice::TAX_STATE_FREE,
        ];

        yield 'customer account + no free tax => no tax-free' => [
            CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            false,
            false,
            CartPrice::TAX_STATE_GROSS,
        ];

        yield 'business account + no free tax => no tax-free' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            false,
            false,
            CartPrice::TAX_STATE_GROSS,
        ];

        yield 'customer account + both free tax => tax-free' => [
            CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            true,
            true,
            CartPrice::TAX_STATE_FREE,
        ];

        yield 'business account + both free tax => tax-free' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            true,
            true,
            CartPrice::TAX_STATE_FREE,
        ];
    }
}
