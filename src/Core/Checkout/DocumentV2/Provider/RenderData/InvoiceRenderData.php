<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Provider\RenderData;

use Shopware\Core\Checkout\DocumentV2\Config\DocumentCompanyInfo;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfig;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentDisplayOptions;
use Shopware\Core\Checkout\DocumentV2\Struct\AbstractRenderData;
use Shopware\Core\Checkout\DocumentV2\Zugferd\TypeCode;
use Shopware\Core\Checkout\DocumentV2\Zugferd\View\AllowanceChargeView;
use Shopware\Core\Checkout\DocumentV2\Zugferd\View\LineItemView;
use Shopware\Core\Checkout\DocumentV2\Zugferd\View\MonetarySummationView;
use Shopware\Core\Checkout\DocumentV2\Zugferd\View\PaymentMeansView;
use Shopware\Core\Checkout\DocumentV2\Zugferd\View\TaxBreakdownView;
use Shopware\Core\Checkout\DocumentV2\Zugferd\View\TradePartyView;
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
