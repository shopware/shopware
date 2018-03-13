<?php declare(strict_types=1);

namespace Shopware\StorefrontApi\Context;

use Shopware\Api\Currency\Struct\CurrencyBasicStruct;
use Shopware\Api\Customer\Struct\CustomerBasicStruct;
use Shopware\Api\Customer\Struct\CustomerGroupBasicStruct;
use Shopware\Api\Payment\Struct\PaymentMethodBasicStruct;
use Shopware\Api\Shipping\Struct\ShippingMethodBasicStruct;
use Shopware\Api\Shop\Struct\ShopDetailStruct;
use Shopware\Api\Tax\Collection\TaxBasicCollection;
use Shopware\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Context\Struct\StorefrontContext;

class StorefrontApiContext extends StorefrontContext
{
    /**
     * @var string|null
     */
    protected $cartToken;

    /**
     * @var string
     */
    protected $contextToken;

    public function __construct(
        string $contextToken,
        ShopDetailStruct $shop,
        CurrencyBasicStruct $currency,
        CustomerGroupBasicStruct $currentCustomerGroup,
        CustomerGroupBasicStruct $fallbackCustomerGroup,
        TaxBasicCollection $taxRules,
        PaymentMethodBasicStruct $paymentMethod,
        ShippingMethodBasicStruct $shippingMethod,
        ShippingLocation $shippingLocation,
        ?string $cartToken,
        ?CustomerBasicStruct $customer,
        array $contextRulesIds = []
    ) {
        parent::__construct(
            $shop,
            $currency,
            $currentCustomerGroup,
            $fallbackCustomerGroup,
            $taxRules,
            $paymentMethod,
            $shippingMethod,
            $shippingLocation,
            $customer,
            $contextRulesIds
        );
        $this->cartToken = $cartToken;
        $this->contextToken = $contextToken;
    }

    public function getCartToken(): ?string
    {
        return $this->cartToken;
    }

    public function getContextToken(): string
    {
        return $this->contextToken;
    }
}
