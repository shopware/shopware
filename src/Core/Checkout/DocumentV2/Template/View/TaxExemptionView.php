<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Template\View;

use Shopware\Core\Checkout\DocumentV2\Template\Enum\TaxCategory;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * The VAT exemption an e-invoice states for the whole order, with the data its category requires.
 * Without one, every position keeps the category of its tax rate.
 *
 * @internal
 */
#[Package('after-sales')]
final readonly class TaxExemptionView
{
    public function __construct(
        public TaxCategory $taxCategory,
        public string $buyerVatId,
        public OrderAddressEntity $deliveryAddress,
    ) {
    }

    /**
     * Category K needs the seller and the buyer VAT ID (BR-IC-02) and a delivery address with zip code
     * (BR-IC-12, BR-DE-11), without one of them the e-invoice keeps the rate-based category.
     */
    public static function fromIntraCommunitySupply(?string $buyerVatId, ?OrderAddressEntity $deliveryAddress, ?string $sellerVatId): ?self
    {
        if ($buyerVatId === null || ($sellerVatId ?? '') === '' || $deliveryAddress === null || ($deliveryAddress->getZipcode() ?? '') === '') {
            return null;
        }

        return new self(TaxCategory::INTRA_COMMUNITY_SUPPLY, $buyerVatId, $deliveryAddress);
    }
}
