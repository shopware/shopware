<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Provider\RenderData;

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
 * Cancellation-invoice ("storno") render data: an inverted invoice that additionally references the
 * original invoice. It mirrors the {@see InvoiceRenderData} field contract so the shared invoice and
 * Zugferd templates render it unchanged; the storno-specific numbers are carried in the legacy
 * `custom` map.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
final readonly class CancellationInvoiceRenderData extends AbstractRenderData
{
    /**
     * @param list<LineItemView> $lineItems
     * @param list<AllowanceChargeView> $allowanceCharges
     * @param list<TaxBreakdownView> $taxBreakdown
     * @param array<string, mixed> $custom
     */
    public function __construct(
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
        /**
         * @deprecated tag:v6.8.0 - feeds the legacy flat `config.custom.*` template contract
         */
        public array $custom = [],
    ) {
    }

    public static function fromInvoice(
        InvoiceRenderData $invoice,
        ?string $stornoNumber,
        string $referencedInvoiceNumber,
    ): self {
        return new self(
            typeCode: TypeCode::CANCELLATION_INVOICE,
            buyerReference: $invoice->buyerReference,
            buyer: $invoice->buyer,
            deliveryDate: $invoice->deliveryDate,
            lineItems: $invoice->lineItems,
            allowanceCharges: $invoice->allowanceCharges,
            taxBreakdown: $invoice->taxBreakdown,
            monetarySummation: $invoice->monetarySummation,
            paymentMeans: $invoice->paymentMeans,
            paymentDueDate: $invoice->paymentDueDate,
            intraCommunityDelivery: $invoice->intraCommunityDelivery,
            custom: [
                'stornoNumber' => $stornoNumber,
                'invoiceNumber' => $referencedInvoiceNumber,
            ],
        );
    }
}
