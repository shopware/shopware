<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Common;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\SyncTestPaymentHandler;
use Shopware\Core\Content\Product\Cart\ProductGateway;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\TaxFreeConfig;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\System\Tax\TaxEntity;

class Generator extends TestCase
{
    public static function createSalesChannelContext(
        ?Context $baseContext = null,
        ?CustomerGroupEntity $currentCustomerGroup = null,
        ?CustomerGroupEntity $fallbackCustomerGroup = null,
        ?SalesChannelEntity $salesChannel = null,
        ?CurrencyEntity $currency = null,
        ?TaxCollection $taxes = null,
        ?CountryEntity $country = null,
        ?CountryStateEntity $state = null,
        ?CustomerAddressEntity $shipping = null,
        ?PaymentMethodEntity $paymentMethod = null,
        ?ShippingMethodEntity $shippingMethod = null,
        ?CustomerEntity $customer = null
    ): SalesChannelContext {
        if (!$baseContext) {
            $baseContext = Context::createDefaultContext();
        }
        if ($salesChannel === null) {
            $salesChannel = new SalesChannelEntity();
            $salesChannel->setId('ffa32a50e2d04cf38389a53f8d6cd594');
            $salesChannel->setNavigationCategoryId(Uuid::randomHex());
            $salesChannel->setTaxCalculationType(SalesChannelDefinition::CALCULATION_TYPE_HORIZONTAL);
        }

        $currency = $currency ?: (new CurrencyEntity())->assign([
            'id' => '4c8eba11bd3546d786afbed481a6e665',
            'factor' => 1,
            'decimalPrecision' => 2,
        ]);

        $currency->setFactor(1);

        if (!$currentCustomerGroup) {
            $currentCustomerGroup = new CustomerGroupEntity();
            $currentCustomerGroup->setId(Defaults::FALLBACK_CUSTOMER_GROUP);
            $currentCustomerGroup->setDisplayGross(true);
        }

        if (!$fallbackCustomerGroup) {
            $fallbackCustomerGroup = new CustomerGroupEntity();
            $fallbackCustomerGroup->setId(Defaults::FALLBACK_CUSTOMER_GROUP);
            $currentCustomerGroup->setDisplayGross(true);
        }

        if (!$taxes) {
            $tax = new TaxEntity();
            $tax->setId('4926035368e34d9fa695e017d7a231b9');
            $tax->setName('test');
            $tax->setTaxRate(19.0);

            $taxes = new TaxCollection([$tax]);
        }

        if (!$country) {
            $country = new CountryEntity();
            $country->setId('5cff02b1029741a4891c430bcd9e3603');
            if (Feature::isActive('FEATURE_NEXT_14114')) {
                $country->setCustomerTax(new TaxFreeConfig(false, Defaults::CURRENCY, 0));
                $country->setCompanyTax(new TaxFreeConfig(false, Defaults::CURRENCY, 0));
            } else {
                $country->setTaxFree(false);
                $country->setCompanyTaxFree(false);
            }
            $country->setName('Germany');
        }
        if (!$state) {
            $state = new CountryStateEntity();
            $state->setId('bd5e2dcf547e4df6bb1ff58a554bc69e');
            $state->setCountryId($country->getId());
        }

        if (!$shipping) {
            $shipping = new CustomerAddressEntity();
            $shipping->setCountry($country);
            $shipping->setCountryState($state);
        }

        if (!$paymentMethod) {
            $paymentMethod = (new PaymentMethodEntity())->assign(
                [
                    'id' => '19d144ffe15f4772860d59fca7f207c1',
                    'handlerIdentifier' => SyncTestPaymentHandler::class,
                    'name' => 'Generated Payment',
                    'active' => true,
                ]
            );
        }

        if (!$shippingMethod) {
            $deliveryTime = new DeliveryTimeEntity();
            $deliveryTime->setMin(1);
            $deliveryTime->setMax(2);
            $deliveryTime->setUnit(DeliveryTimeEntity::DELIVERY_TIME_DAY);

            $shippingMethod = new ShippingMethodEntity();
            $shippingMethod->setDeliveryTime($deliveryTime);
            $shippingMethod->setId('8beeb66e9dda46b18891a059257a590e');
        }

        if (!$customer) {
            $customer = (new CustomerEntity())->assign(['id' => Uuid::randomHex()]);
            $customer->setId(Uuid::randomHex());
            $customer->setGroup($currentCustomerGroup);
        }

        return new SalesChannelContext(
            $baseContext,
            Uuid::randomHex(),
            Uuid::randomHex(),
            $salesChannel,
            $currency,
            $currentCustomerGroup,
            $fallbackCustomerGroup,
            $taxes,
            $paymentMethod,
            $shippingMethod,
            ShippingLocation::createFromAddress($shipping),
            $customer,
            new CashRoundingConfig(2, 0.01, true),
            new CashRoundingConfig(2, 0.01, true),
            []
        );
    }

    public static function createGrossPriceDetector(): TaxDetector
    {
        return (new self())->createTaxDetector(true, false);
    }

    public static function createNetPriceDetector(): TaxDetector
    {
        return (new self())->createTaxDetector(false, false);
    }

    public static function createNetDeliveryDetector(): TaxDetector
    {
        return (new self())->createTaxDetector(false, true);
    }

    /**
     * @param QuantityPriceDefinition[] $priceDefinitions indexed by product number
     */
    public function createProductPriceGateway($priceDefinitions): ProductGateway
    {
        /** @var MockObject|ProductGateway $mock */
        $mock = $this->createMock(ProductGateway::class);
        $mock
            ->method('get')
            ->willReturn($priceDefinitions);

        return $mock;
    }

    public static function createCart(): Cart
    {
        $cart = new Cart('test', 'test');
        $cart->setLineItems(
            new LineItemCollection([
                (new LineItem('A', 'product', 'A', 27))
                    ->setPrice(new CalculatedPrice(10, 270, new CalculatedTaxCollection(), new TaxRuleCollection(), 27)),
                (new LineItem('B', 'test', 'B', 5))
                    ->setGood(false)
                    ->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())),
            ])
        );
        $cart->setPrice(
            new CartPrice(
                275.0,
                275.0,
                0,
                new CalculatedTaxCollection(),
                new TaxRuleCollection(),
                CartPrice::TAX_STATE_GROSS
            )
        );

        return $cart;
    }

    private function createTaxDetector(bool $useGross, bool $isNetDelivery): TaxDetector
    {
        /** @var MockObject|TaxDetector $mock */
        $mock = $this->createMock(TaxDetector::class);
        $mock
            ->method('useGross')
            ->willReturn($useGross);

        $mock
            ->method('isNetDelivery')
            ->willReturn($isNetDelivery);

        return $mock;
    }
}
