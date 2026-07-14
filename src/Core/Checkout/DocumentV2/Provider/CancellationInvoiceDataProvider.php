<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Provider;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Document\Service\ReferenceInvoiceLoader;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\InvoiceRenderData;
use Shopware\Core\Checkout\DocumentV2\Template\Enum\TypeCode;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
final readonly class CancellationInvoiceDataProvider extends AbstractDocumentDataProvider
{
    final public const KEY = 'storno';

    public function __construct(
        private InvoiceDataProvider $invoiceDataProvider,
        private ReferenceInvoiceLoader $referenceInvoiceLoader,
    ) {
    }

    public function getKey(): string
    {
        return self::KEY;
    }

    public function getDocumentTypes(): array
    {
        return [
            DocumentType::CANCELLATION_INVOICE->value,
        ];
    }

    public function enrichOrderCriteria(Criteria $criteria): void
    {
        $this->invoiceDataProvider->enrichOrderCriteria($criteria);
    }

    public function provideRenderingData(
        OrderEntity $order,
        DocumentGenerationRequest $generationRequest,
        Context $context,
    ): InvoiceRenderData {
        $referencedInvoiceNumber = $this->resolveReferencedInvoiceNumber(
            $order->getId(),
            $generationRequest->referencedDocumentId,
        );

        $this->invertOrderPrices($order);

        $invoice = $this->invoiceDataProvider->provideRenderingData($order, $generationRequest, $context);

        return $invoice->with(
            typeCode: TypeCode::CANCELLATION_INVOICE,
            custom: [
                'stornoNumber' => $invoice->documentNumber,
                'invoiceNumber' => $referencedInvoiceNumber,
            ],
        );
    }

    private function resolveReferencedInvoiceNumber(string $orderId, ?string $referencedDocumentId): string
    {
        $invoice = $this->referenceInvoiceLoader->load($orderId, $referencedDocumentId);

        if ($invoice === []) {
            throw DocumentV2Exception::referencedInvoiceNotFound($orderId);
        }

        $number = $invoice['documentNumber'] ?? null;

        if ($number === null || $number === '') {
            $config = json_decode($invoice['config'] ?? '[]', true, 512, \JSON_THROW_ON_ERROR);
            $number = $config['documentNumber'] ?? null;
        }

        if (!\is_string($number) || $number === '') {
            throw DocumentV2Exception::referencedInvoiceNumberMissing($orderId);
        }

        return $number;
    }

    /**
     * Inverts the order into a cancellation: quantities are negated while the item net price stays
     * positive, which EN16931 requires (BR-27 forbids a negative item net price; the reversal is
     * expressed through the negative quantity instead).
     */
    private function invertOrderPrices(OrderEntity $order): void
    {
        $this->invertLineItemPrices($order->getLineItems());

        foreach ($order->getPrice()->getCalculatedTaxes() as $tax) {
            $tax->setTax($tax->getTax() * -1);
            $tax->setPrice($tax->getPrice() * -1);
        }

        foreach ($order->getDeliveries() ?? [] as $delivery) {
            $delivery->setShippingCosts($this->invertCalculatedPrice($delivery->getShippingCosts()));
        }

        $order->setShippingTotal($order->getShippingTotal() * -1);
        $order->setAmountNet($order->getAmountNet() * -1);
        $order->setAmountTotal($order->getAmountTotal() * -1);

        $price = $order->getPrice();
        $order->setPrice(new CartPrice(
            $price->getNetPrice() * -1,
            $price->getTotalPrice() * -1,
            $price->getPositionPrice() * -1,
            $price->getCalculatedTaxes(),
            $price->getTaxRules(),
            $price->getTaxStatus(),
            $price->getRawTotal() * -1,
        ));
    }

    private function invertLineItemPrices(?OrderLineItemCollection $lineItems): void
    {
        if ($lineItems === null) {
            return;
        }

        foreach ($lineItems as $lineItem) {
            $lineItem->setQuantity($lineItem->getQuantity() * -1);
            $lineItem->setTotalPrice($lineItem->getTotalPrice() * -1);

            $price = $lineItem->getPrice();

            if ($price !== null) {
                $lineItem->setPrice($this->invertCalculatedPrice($price));
            }

            $this->invertLineItemPrices($lineItem->getChildren());
        }
    }

    private function invertCalculatedPrice(CalculatedPrice $price): CalculatedPrice
    {
        $calculatedTaxes = $price->getCalculatedTaxes();

        foreach ($calculatedTaxes as $calculatedTax) {
            $calculatedTax->setTax($calculatedTax->getTax() * -1);
            $calculatedTax->setPrice($calculatedTax->getPrice() * -1);
        }

        return new CalculatedPrice(
            $price->getUnitPrice(),
            $price->getTotalPrice() * -1,
            $calculatedTaxes,
            $price->getTaxRules(),
            $price->getQuantity(),
            $price->getReferencePrice(),
            $price->getListPrice(),
            $price->getRegulationPrice(),
        );
    }
}
