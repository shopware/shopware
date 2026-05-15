<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Config;

use Shopware\Core\Framework\Log\Package;

/**
 * Document rendering toggles consumed by Twig templates.
 *
 * Backed by the merchants `document_base_config.config` JSON; built by
 * {@see DocumentConfigLoader} and shared across renderers.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
final readonly class DocumentDisplayOptions
{
    /**
     * @param list<string> $deliveryCountries
     */
    public function __construct(
        public bool $displayHeader = false,
        public bool $displayFooter = false,
        public bool $displayPageCount = false,
        public bool $displayCompanyAddress = false,
        public bool $displayReturnAddress = false,
        public bool $displayCustomerVatId = false,
        public bool $displayLineItems = false,
        public bool $displayLineItemPosition = false,
        public bool $displayPrices = false,
        public bool $displayDivergentDeliveryAddress = false,
        public array $deliveryCountries = [],
    ) {
    }
}
