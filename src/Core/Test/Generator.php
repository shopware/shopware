<?php declare(strict_types=1);

namespace Shopware\Core\Test;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\ContextSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\TaxFreeConfig;
use Shopware\Core\Framework\Log\Package;
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
use Shopware\Core\Test\Integration\PaymentHandler\SyncTestPaymentHandler;

/**
 * @internal
 */
#[Package('checkout')]
class Generator extends TestCase
{
    /**
     * @param string[] $ruleIds
     * @param string[] $languageIdChain
     * @param array<string, string[]> $areaRuleIds
     * @param array<array-key, mixed> $overrides
     */
    public static function createSalesChannelContext(
        ?ContextSource $source = null,
        ?string $token = null,
        ?string $domainId = null,
        ?SalesChannelEntity $salesChannel = null,
        ?CurrencyEntity $currency = null,
        ?CustomerGroupEntity $currentCustomerGroup = null,
        ?TaxCollection $taxRules = null,
        ?CustomerEntity $customer = null,
        ?PaymentMethodEntity $paymentMethod = null,
        ?ShippingMethodEntity $shippingMethod = null,
        ?ShippingLocation $shippingLocation = null,
        ?CashRoundingConfig $itemRounding = null,
        ?CashRoundingConfig $totalRounding = null,
        ?array $ruleIds = null,
        ?array $languageIdChain = null,
        ?string $versionId = null,
        ?float $currencyFactor = null,
        ?bool $considerInheritance = null,
        ?string $taxState = null,
        ?CashRoundingConfig $rounding = null,
        ?array $areaRuleIds = null,
        ?DeliveryTimeEntity $deliveryTime = null,
        ?CountryEntity $country = null,
        ?CountryStateEntity $countryState = null,
        ?CustomerAddressEntity $customerAddress = null,
        ?array $overrides = [],
    ): SalesChannelContext {
        $source ??= new SystemSource();

        $token ??= Uuid::randomHex();

        $domainId ??= Uuid::randomHex();

        if (!$salesChannel) {
            $salesChannel = new SalesChannelEntity();
            $salesChannel->setId('ffa32a50e2d04cf38389a53f8d6cd594');
            $salesChannel->setNavigationCategoryId(Uuid::randomHex());
            $salesChannel->setTaxCalculationType(SalesChannelDefinition::CALCULATION_TYPE_HORIZONTAL);
            $salesChannel->setPaymentMethodId($paymentMethod?->getId() ?? '19d144ffe15f4772860d59fca7f207c1');
            $salesChannel->setShippingMethodId($shippingMethod?->getId() ?? '8beeb66e9dda46b18891a059257a590e');
        }

        if (!$currency) {
            $currency = new CurrencyEntity();
            $currency->setId('4c8eba11bd3546d786afbed481a6e665');
            $currency->setFactor(1);
        }

        if (!$currentCustomerGroup) {
            $currentCustomerGroup = new CustomerGroupEntity();
            $currentCustomerGroup->setId(TestDefaults::FALLBACK_CUSTOMER_GROUP);
            $currentCustomerGroup->setDisplayGross(true);
        }

        if (!$taxRules) {
            $tax = new TaxEntity();
            $tax->setId('4926035368e34d9fa695e017d7a231b9');
            $tax->setName('test');
            $tax->setTaxRate(19.0);

            $taxRules = new TaxCollection([$tax]);
        }

        if (!$customer) {
            $customer = new CustomerEntity();
            $customer->setId(Uuid::randomHex());
            $customer->setGroup($currentCustomerGroup);
        }

        if (!$paymentMethod) {
            $paymentMethod = new PaymentMethodEntity();
            $paymentMethod->setId('19d144ffe15f4772860d59fca7f207c1');
            $paymentMethod->setHandlerIdentifier(SyncTestPaymentHandler::class);
            $paymentMethod->setName('Generated Payment');
            $paymentMethod->setActive(true);
        }

        if (!$shippingMethod) {
            if (!$deliveryTime) {
                $deliveryTime = new DeliveryTimeEntity();
                $deliveryTime->setMin(1);
                $deliveryTime->setMax(2);
                $deliveryTime->setUnit(DeliveryTimeEntity::DELIVERY_TIME_DAY);
            }

            $shippingMethod = new ShippingMethodEntity();
            $shippingMethod->setDeliveryTime($deliveryTime);
            $shippingMethod->setId('8beeb66e9dda46b18891a059257a590e');
        }

        if (!$shippingLocation) {
            if (!$country) {
                $country = new CountryEntity();
                $country->setId('5cff02b1029741a4891c430bcd9e3603');
                $country->setCustomerTax(new TaxFreeConfig(false, Defaults::CURRENCY, 0));
                $country->setCompanyTax(new TaxFreeConfig(false, Defaults::CURRENCY, 0));
                $country->setName('Germany');
            }

            if (!$countryState) {
                $countryState = new CountryStateEntity();
                $countryState->setId('bd5e2dcf547e4df6bb1ff58a554bc69e');
                $countryState->setCountryId($country->getId());
            }

            if (!$customerAddress) {
                $customerAddress = new CustomerAddressEntity();
                $customerAddress->setId(Uuid::randomHex());
            }

            $customerAddress->setCountry($country);
            $customerAddress->setCountryState($countryState);

            $shippingLocation = ShippingLocation::createFromAddress($customerAddress);
        }

        $itemRounding ??= new CashRoundingConfig(2, 0.01, true);

        $totalRounding ??= new CashRoundingConfig(2, 0.01, true);

        $ruleIds ??= [];

        $languageIdChain ??= [Defaults::LANGUAGE_SYSTEM];

        $versionId ??= Defaults::LIVE_VERSION;

        $currencyFactor ??= 1;

        $considerInheritance ??= true;

        $taxState ??= CartPrice::TAX_STATE_GROSS;

        $rounding ??= new CashRoundingConfig(2, 0.01, true);

        $areaRuleIds ??= [];

        $salesChannelContext = new SalesChannelContext(
            $source,
            $token,
            $domainId,
            $salesChannel,
            $currency,
            $currentCustomerGroup,
            $taxRules,
            $customer,
            $paymentMethod,
            $shippingMethod,
            $shippingLocation,
            $itemRounding,
            $totalRounding,
            $ruleIds,
            $languageIdChain,
            $versionId,
            $currencyFactor,
            $considerInheritance,
            $taxState,
            $rounding,
            $areaRuleIds
        );

        if ($overrides) {
            $salesChannelContext->assign($overrides);
        }

        return $salesChannelContext;
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

    public static function createCartWithDelivery(): Cart
    {
        $cart = static::createCart();

        $shippingMethod = new ShippingMethodEntity();
        $calculatedPrice = new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection());
        $deliveryDate = new DeliveryDate(new \DateTime(), new \DateTime());

        $deliveryPositionCollection = new DeliveryPositionCollection();
        foreach ($cart->getLineItems() as $lineItem) {
            $deliveryPosition = new DeliveryPosition(
                'anyIdentifier',
                $lineItem,
                $lineItem->getQuantity(),
                $calculatedPrice,
                $deliveryDate
            );

            $lineItem->setDeliveryInformation(new DeliveryInformation(1000, 10.0, false, 2, null, 10.0, 10.0, 10.0));

            $deliveryPositionCollection->add($deliveryPosition);
        }

        $delivery = new Delivery(
            $deliveryPositionCollection,
            $deliveryDate,
            $shippingMethod,
            new ShippingLocation(new CountryEntity(), null, null),
            $calculatedPrice
        );

        $cart->addDeliveries(new DeliveryCollection([$delivery]));

        return $cart;
    }
}
