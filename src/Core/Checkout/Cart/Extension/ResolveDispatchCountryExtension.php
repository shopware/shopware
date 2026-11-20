<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Extension;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryEntity;

/**
 * Resolves the id of the country the goods are dispatched from, by default the shop owner's country.
 * An empty string means unknown.
 *
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<string>
 */
#[Package('checkout')]
final class ResolveDispatchCountryExtension extends Extension
{
    public const NAME = 'checkout.tax.resolve-dispatch-country';

    /**
     * @internal Shopware owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly string $salesChannelId,
        public readonly CountryEntity $deliveryCountry,
        public readonly ?CustomerEntity $customer,
        /**
         * Only set for documents
         */
        public readonly ?OrderEntity $order,
    ) {
    }
}
