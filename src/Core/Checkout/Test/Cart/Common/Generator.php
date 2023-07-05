<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Common;

use Shopware\Core\Test\Generator as NewGenerator;

/**
 * @internal
 *
 * @deprecated tag:v6.6.0 - Use `\Shopware\Core\Test\Generator` instead
 */
class Generator extends NewGenerator
{
    public static function createSalesChannelContext(
        ?Context $baseContext = null,
        ?CustomerGroupEntity $currentCustomerGroup = null,
        ?SalesChannelEntity $salesChannel = null,
        ?CurrencyEntity $currency = null,
        ?TaxCollection $taxes = null,
        ?CountryEntity $country = null,
        ?CountryStateEntity $state = null,
        ?CustomerAddressEntity $shipping = null,
        ?PaymentMethodEntity $paymentMethod = null,
        ?ShippingMethodEntity $shippingMethod = null,
        ?CustomerEntity $customer = null,
        ?string $token = null,
        ?string $domainId = null,
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
        ]);

        $currency->setFactor(1);

        if (!$currentCustomerGroup) {
            $currentCustomerGroup = new CustomerGroupEntity();
            $currentCustomerGroup->setId(TestDefaults::FALLBACK_CUSTOMER_GROUP);
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
            $country->setCustomerTax(new TaxFreeConfig(false, Defaults::CURRENCY, 0));
            $country->setCompanyTax(new TaxFreeConfig(false, Defaults::CURRENCY, 0));
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
            $token ?? Uuid::randomHex(),
            $domainId ?? Uuid::randomHex(),
            $salesChannel,
            $currency,
            $currentCustomerGroup,
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
        $cart = new Cart('test');
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
