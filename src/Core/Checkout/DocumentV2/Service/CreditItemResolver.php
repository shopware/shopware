<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Selects the credit line items a new credit note may still bill: the order's credit items minus
 * the ones the referenced invoice already carried and the ones earlier credit notes already
 * credited.
 *
 * @internal
 */
#[Package('after-sales')]
final readonly class CreditItemResolver
{
    private const CREDIT_NOTE_TECHNICAL_NAMES = [
        'credit_note',
        'zugferd_credit_note',
        'zugferd_embedded_credit_note',
    ];

    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @throws DocumentV2Exception
     */
    public function resolve(OrderEntity $order, string $referencedInvoiceId): OrderLineItemCollection
    {
        $creditItems = ($order->getLineItems() ?? new OrderLineItemCollection())
            ->filterByType(LineItem::CREDIT_LINE_ITEM_TYPE);

        if ($creditItems->count() === 0) {
            throw DocumentV2Exception::noCreditLineItems($order->getId());
        }

        $processedIds = [
            ...$this->getCreditIdsOnInvoiceDocument($referencedInvoiceId),
            ...$this->getPreviouslyCreditedIdsForInvoice($referencedInvoiceId),
        ];

        $unprocessed = $creditItems->filter(
            static fn (OrderLineItemEntity $item): bool => !\in_array($item->getId(), $processedIds, true),
        );

        if ($unprocessed->count() === 0) {
            throw DocumentV2Exception::noUnprocessedCreditLineItems($order->getId());
        }

        return $unprocessed;
    }

    /**
     * @return list<string>
     */
    private function getCreditIdsOnInvoiceDocument(string $referencedInvoiceId): array
    {
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

        /**
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
     * @return list<string>
     */
    private function getPreviouslyCreditedIdsForInvoice(string $referencedInvoiceId): array
    {
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
