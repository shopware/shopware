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
     * @var array<string, mixed>
     */
    public array $custom;

    /**
     * @param list<string> $deliveryCountries
     * @param array<string, mixed> $legacyConfig
     */
    public function __construct(
        DocumentConfig $config,
        CompanyInfo $company,
        public string $documentDate,
        public string $documentNumber,
        public ?string $documentComment,
        public bool $intraCommunityDelivery,
        public bool $displayDivergentDeliveryAddress,
        public bool $displayLineItems,
        public bool $displayLineItemPosition,
        public bool $displayPrices,
        public array $deliveryCountries,
        array $legacyConfig = [],
    ) {
        $this->custom = ['invoiceNumber' => $documentNumber];

        parent::__construct($config, $company, $legacyConfig);
    }
}
