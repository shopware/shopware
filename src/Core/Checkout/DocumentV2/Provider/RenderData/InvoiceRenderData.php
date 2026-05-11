<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Provider\RenderData;

use Shopware\Core\Checkout\DocumentV2\Config\CompanyInfo;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfig;
use Shopware\Core\Checkout\DocumentV2\Struct\AbstractRenderData;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
final readonly class InvoiceRenderData extends AbstractRenderData
{
    /**
     * @param list<string> $deliveryCountries
     * @param array<string, mixed> $legacyConfig
     * @param array<string, mixed> $custom
     */
    public function __construct(
        DocumentConfig $config,
        CompanyInfo $company,
        string $documentDate,
        string $documentNumber,
        ?string $documentComment,
        public bool $intraCommunityDelivery,
        public bool $displayDivergentDeliveryAddress,
        public bool $displayLineItems,
        public bool $displayLineItemPosition,
        public bool $displayPrices,
        public array $deliveryCountries,
        array $legacyConfig = [],
        public array $custom = [],
    ) {
        parent::__construct(
            $config,
            $company,
            $documentDate,
            $documentNumber,
            $documentComment,
            $legacyConfig
        );
    }
}
