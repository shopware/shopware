<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Document\Renderer\ZugferdCreditNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdEmbeddedCreditNoteRenderer;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Reduces an order to the credit note it should render: only the unprocessed credit line items,
 * with their prices inverted to positive and totals recomputed.
 *
 * "Unprocessed" means credits that are neither already contained in the referenced invoice nor
 * already credited by a previous credit note. Both exclusions are resolved against the document
 * table.
 *
 * @internal
 */
#[Package('after-sales')]
final readonly class CreditItemResolver
{
    /**
     * Document type technical names that credit line items can already have been credited by: the v2
     * credit-note type (which also matches v1 plain credit notes) plus the two v1 ZUGFeRD credit-note
     * renderers, so v1 and v2 documents dedup against each other.
     */
    private const CREDIT_NOTE_TECHNICAL_NAMES = [
        DocumentType::CREDIT_NOTE->value,
        ZugferdCreditNoteRenderer::TYPE,
        ZugferdEmbeddedCreditNoteRenderer::TYPE,
    ];

    public function __construct(
        private Connection $connection,
    ) {
    }

    public function apply(OrderEntity $order, ?string $referencedInvoiceId): void
    {
        $creditItems = ($order->getLineItems() ?? new OrderLineItemCollection())
            ->filterByType(LineItem::CREDIT_LINE_ITEM_TYPE);

        if ($creditItems->count() === 0) {
            throw DocumentV2Exception::noCreditItems($order->getId());
        }

        $invoiceCreditIds = $this->getCreditIdsOnInvoiceDocument($referencedInvoiceId);
        $creditNoteItemIds = $this->getPreviouslyCreditedIdsForInvoice($referencedInvoiceId);

        $unprocessed = $creditItems->filter(
            static fn (OrderLineItemEntity $item) => !\in_array($item->getId(), $invoiceCreditIds, true)
                && !\in_array($item->getId(), $creditNoteItemIds, true)
        );

        if ($unprocessed->count() === 0) {
            throw DocumentV2Exception::noUnprocessedCreditItems($order->getId());
        }

        $this->prepareCreditOrder($order, $unprocessed);
    }

    private function prepareCreditOrder(OrderEntity $order, OrderLineItemCollection $creditItems): void
    {
        $this->invertLineItemPrices($creditItems);

        $creditItemsCalculatedPrice = $creditItems->getPrices()->sum();
        $totalPrice = $creditItemsCalculatedPrice->getTotalPrice();
        $taxAmount = $creditItemsCalculatedPrice->getCalculatedTaxes()->getAmount();

        if ($order->getPrice()->hasNetPrices()) {
            $price = new CartPrice(
                $totalPrice,
                $totalPrice + $taxAmount,
                $totalPrice,
                $creditItemsCalculatedPrice->getCalculatedTaxes(),
                $creditItemsCalculatedPrice->getTaxRules(),
                $order->getTaxStatus() ?? $order->getPrice()->getTaxStatus(),
            );
        } else {
            $price = new CartPrice(
                $totalPrice - $taxAmount,
                $totalPrice,
                $totalPrice,
                $creditItemsCalculatedPrice->getCalculatedTaxes(),
                $creditItemsCalculatedPrice->getTaxRules(),
                $order->getTaxStatus() ?? $order->getPrice()->getTaxStatus(),
            );
        }

        $order->setLineItems($creditItems);
        $order->setPrice($price);
        $order->setShippingTotal(0.0);
        $order->setPositionPrice($price->getPositionPrice());
        $order->setAmountNet($price->getNetPrice());
        $order->setAmountTotal($price->getTotalPrice());
    }

    private function invertLineItemPrices(OrderLineItemCollection $lineItems): void
    {
        foreach ($lineItems as $lineItem) {
            $lineItem->setUnitPrice($lineItem->getUnitPrice() * -1);
            $lineItem->setTotalPrice($lineItem->getTotalPrice() * -1);

            $lineItemPrice = $lineItem->getPrice();

            if ($lineItemPrice !== null) {
                $lineItem->setPrice($this->invertCalculatedPrice($lineItemPrice));
            }

            $children = $lineItem->getChildren();

            if ($children !== null) {
                $this->invertLineItemPrices($children);
            }
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
            $price->getUnitPrice() * -1,
            $price->getTotalPrice() * -1,
            $calculatedTaxes,
            $price->getTaxRules(),
            $price->getQuantity(),
            $price->getReferencePrice(),
            $price->getListPrice(),
            $price->getRegulationPrice(),
        );
    }

    /**
     * @return list<string> credit item ids already contained in the referenced invoice
     */
    private function getCreditIdsOnInvoiceDocument(?string $referencedInvoiceId): array
    {
        if ($referencedInvoiceId === null) {
            return [];
        }

        $sql = '
            SELECT
                oli.id AS id
            FROM
                document AS d
                INNER JOIN order_line_item AS oli ON oli.order_id = d.order_id AND oli.order_version_id = d.order_version_id
            WHERE
                d.id = :referencedInvoiceId
                AND oli.type = :creditType
                AND d.order_version_id != :liveVersionId;
        ';

        /*
         * Documents with order_version_id = LIVE_VERSION are intentionally excluded here,
         * because under certain (rare) circumstances, the order_version_id of the invoice document
         * can be LIVE_VERSION instead of an actual snapshot unique version ID.
         *
         * This makes it possible to still generate credit notes for invoice documents that have
         * been created with a LIVE_VERSION order_version_id.
         *
         * It also comes with a drawback: if the invoice already contained a credit item,
         * the new credit note will include it again. Unfortunately, this is the best we can do
         * to still support these special cases and is still better than failing the credit note generation,
         * which might be needed years later for a business case.
         */
        $binaryIds = $this->connection->fetchFirstColumn($sql, [
            'referencedInvoiceId' => Uuid::fromHexToBytes($referencedInvoiceId),
            'creditType' => LineItem::CREDIT_LINE_ITEM_TYPE,
            'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
        ]);

        return array_map(static fn ($id): string => Uuid::fromBytesToHex($id), $binaryIds);
    }

    /**
     * @return list<string> credit item ids already credited on previous credit notes for the invoice
     */
    private function getPreviouslyCreditedIdsForInvoice(?string $referencedInvoiceId): array
    {
        if ($referencedInvoiceId === null) {
            return [];
        }

        $sql = '
            SELECT
                oli.id AS id
            FROM
                document AS d
                INNER JOIN document_type AS dt ON dt.id = d.document_type_id
                INNER JOIN order_line_item AS oli ON oli.order_id = d.order_id AND oli.order_version_id = d.order_version_id
            WHERE
                d.referenced_document_id = :referencedInvoiceId
                AND dt.technical_name IN (:creditTechnicalName)
                AND oli.type = :creditType;
        ';

        $binaryIds = $this->connection->fetchFirstColumn($sql, [
            'referencedInvoiceId' => Uuid::fromHexToBytes($referencedInvoiceId),
            'creditTechnicalName' => self::CREDIT_NOTE_TECHNICAL_NAMES,
            'creditType' => LineItem::CREDIT_LINE_ITEM_TYPE,
        ], [
            'creditTechnicalName' => ArrayParameterType::STRING,
        ]);

        return array_map(static fn ($id): string => Uuid::fromBytesToHex($id), $binaryIds);
    }
}
