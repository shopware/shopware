<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Provider\RenderData;

use Shopware\Core\Checkout\DocumentV2\Template\Enum\TypeCode;
use Shopware\Core\Framework\Log\Package;

/**
 * Cancellation-invoice ("storno") render data: an inverted {@see InvoiceRenderData} that additionally
 * references the original invoice. It reuses the full invoice contract so the shared invoice/Zugferd
 * templates render it unchanged; the storno-specific numbers are carried in the legacy `custom` map.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
final readonly class CancellationInvoiceRenderData extends InvoiceRenderData
{
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
