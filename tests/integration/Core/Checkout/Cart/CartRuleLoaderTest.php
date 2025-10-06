<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart;

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
class CartRuleLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    #[DataProvider('taxConfigProvider')]
    public function testTaxFreeConfig(string $accountType, bool $taxCustomerConfig, bool $taxBusinessConfig, string $expectedTaxConfig): void
    {
        /** @var CartRuleLoader $cartRuleLoader */
        $cartRuleLoader = static::getContainer()->get(CartRuleLoader::class);

        $customerGroup = new CustomerGroupEntity();
        $customerGroup->setId('test-id');
        $customerGroup->setDisplayGross(true);

        $customer = new CustomerEntity();
        $customer->setAccountType($accountType);
        $customer->setId('test-id');
        $customer->setGroup($customerGroup);
        $customer->setCompany('test-company');
        $customer->setVatIds(['DE123456789']);

        $country = new CountryEntity();
        $country->setId(Uuid::randomHex());
        $country->setActive(true);
        $country->setShippingAvailable(true);
        $country->setCheckVatIdPattern(false);

        $currey = new CurrencyEntity();
        $currey->setId(Uuid::randomHex());
        $currey->setTaxFreeFrom(0.0);
        $currey->setFactor(1.5);

        $taxCustomerConfig = new TaxFreeConfig($taxCustomerConfig);
        $country->setCustomerTax($taxCustomerConfig);

        $taxBusinessConfig = new TaxFreeConfig($taxBusinessConfig);
        $country->setCompanyTax($taxBusinessConfig);

        $salesChannelContext = Generator::generateSalesChannelContext(currency: $currey, currentCustomerGroup: $customerGroup, customer: $customer, country: $country);

        $cart = new Cart('test');

        $result = $cartRuleLoader->loadByCart($salesChannelContext, $cart, new CartBehavior());
        static::assertSame($result->getCart()->getPrice()->getTaxStatus(), $expectedTaxConfig);
    }

    public static function taxConfigProvider(): \Generator
    {
        yield [
            CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            true,
            false,
            CartPrice::TAX_STATE_FREE,
        ];

        yield [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            true,
            false,
            CartPrice::TAX_STATE_GROSS,
        ];

        yield [
            CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            false,
            true,
            CartPrice::TAX_STATE_GROSS,
        ];

        yield [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            false,
            true,
            CartPrice::TAX_STATE_FREE,
        ];

        yield [
            CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            false,
            false,
            CartPrice::TAX_STATE_GROSS,
        ];

        yield [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            false,
            false,
            CartPrice::TAX_STATE_GROSS,
        ];

        yield [
            CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            true,
            true,
            CartPrice::TAX_STATE_FREE,
        ];

        yield [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            true,
            true,
            CartPrice::TAX_STATE_FREE,
        ];
    }
}
