<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Extension;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryEntity;

/**
 * Resolves the VAT IDs that count for the intra-community exemption,
 * by default all VAT IDs of the customer, or the ones saved on the order for documents.
 *
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<list<string>>
 */
#[Package('checkout')]
final class ResolveVatIdsExtension extends Extension
{
    public const NAME = 'checkout.tax.resolve-vat-ids';

    /**
     * @internal Shopware owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly string $salesChannelId,
        /**
         * The delivery country, or the billing country of a cart whose products are all digital, for the taxes.
         */
        public readonly CountryEntity $taxCountry,
        public readonly ?CustomerEntity $customer,
        /**
         * Only set for documents.
         */
        public readonly ?OrderEntity $order,
    ) {
    }
}
