<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingCostCollection;
use Shopware\Core\Checkout\Customer\ProspectiveCustomer;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\Context\LanguageInfo;

/**
 * The recalculated checkout of the current context, as returned by `/store-api/checkout/draft`.
 * The optional parts are `null` when the request did not ask for them.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
final class CheckoutDraft extends Struct
{
    /**
     * @internal
     */
    public function __construct(
        public readonly Cart $cart,
        public readonly ?ProspectiveCustomer $prospectiveCustomer,
        public readonly ShippingMethodEntity $shippingMethod,
        public readonly PaymentMethodEntity $paymentMethod,
        public readonly CurrencyEntity $currency,
        public readonly LanguageInfo $language,
        public readonly ?ShippingMethodCollection $shippingMethods,
        public readonly ?PaymentMethodCollection $paymentMethods,
        public readonly ?ShippingCostCollection $shippingCosts,
    ) {
    }

    public function getApiAlias(): string
    {
        return 'checkout_draft';
    }
}
