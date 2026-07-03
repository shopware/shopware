<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Provider\RenderData;

use Shopware\Core\Checkout\DocumentV2\Config\DocumentCompanyInfo;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfig;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentDisplayOptions;
use Shopware\Core\Checkout\DocumentV2\Struct\AbstractRenderData;
use Shopware\Core\Checkout\DocumentV2\Template\Enum\TypeCode;
use Shopware\Core\Checkout\DocumentV2\Template\View\AllowanceChargeView;
use Shopware\Core\Checkout\DocumentV2\Template\View\LineItemView;
use Shopware\Core\Checkout\DocumentV2\Template\View\MonetarySummationView;
use Shopware\Core\Checkout\DocumentV2\Template\View\PaymentMeansView;
use Shopware\Core\Checkout\DocumentV2\Template\View\TaxBreakdownView;
use Shopware\Core\Checkout\DocumentV2\Template\View\TradePartyView;
use Shopware\Core\Framework\Log\Package;

/**
 * Aggregated invoice render payload consumed by both the HTML and XML (ZUGFeRD/XRechnung)
 * invoice renderers.
 *
 * HTML rendering reads only the generic invoice fields here ({@see $intraCommunityDelivery},
 * {@see $documentDate}, etc.) and otherwise walks the raw `OrderEntity` in Twig. The XML
 * renderer additionally consumes the precomputed `Twig\View\*` structs, which are shaped
 * to the XRechnung schema and exposed under the shared Twig namespace so HTML templates
 * can opt in over time. One provider + one render-data DTO per document type is the
 * deliberate pattern: every DocumentV2 document type will ship both HTML and XML output, so
 * channel-specific DTOs would only double boilerplate.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
final readonly class InvoiceRenderData extends AbstractRenderData
{
    /**
     * @param array<string, string> $templatePaths
     * @param array<string, mixed> $custom
     * @param array<string, mixed> $legacyConfig
     * @param list<LineItemView> $lineItems
     * @param list<AllowanceChargeView> $allowanceCharges
     * @param list<TaxBreakdownView> $taxBreakdown
     */
    public function __construct(
        DocumentConfig $config,
        DocumentCompanyInfo $company,
        DocumentDisplayOptions $display,
        string $documentDate,
        string $documentNumber,
        ?string $documentComment,
        array $templatePaths,
        public TypeCode $typeCode,
        public string $buyerReference,
        public TradePartyView $buyer,
        public ?\DateTimeImmutable $deliveryDate,
        public array $lineItems,
        public array $allowanceCharges,
        public array $taxBreakdown,
        public MonetarySummationView $monetarySummation,
        public ?PaymentMeansView $paymentMeans,
        public ?\DateTimeImmutable $paymentDueDate,
        public bool $intraCommunityDelivery,
        array $custom = [],
        array $legacyConfig = [],
    ) {
        parent::__construct(
            $config,
            $company,
            $display,
            $documentDate,
            $documentNumber,
            $documentComment,
            $templatePaths,
            $custom,
            $legacyConfig,
        );
    }
}
