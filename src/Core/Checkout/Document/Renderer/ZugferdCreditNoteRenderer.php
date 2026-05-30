<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Renderer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use horstoeko\zugferd\codelists\ZugferdInvoiceType;
use Psr\Clock\ClockInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Event\DocumentOrderCriteriaEvent;
use Shopware\Core\Checkout\Document\Event\ZugferdCreditNoteOrdersEvent;
use Shopware\Core\Checkout\Document\Service\DocumentConfigLoader;
use Shopware\Core\Checkout\Document\Service\ReferenceInvoiceLoader;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Document\Zugferd\ZugferdBuilder;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('after-sales')]
final class ZugferdCreditNoteRenderer extends AbstractDocumentRenderer
{
    public const TYPE = 'zugferd_credit_note';

    /**
     * @internal
     *
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly DocumentConfigLoader $documentConfigLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        private readonly ReferenceInvoiceLoader $referenceInvoiceLoader,
        private readonly Connection $connection,
        private readonly ZugferdBuilder $documentBuilder,
        private readonly ClockInterface $clock,
    ) {
    }

    public function supports(): string
    {
        return self::TYPE;
    }

    public function getDecorated(): AbstractDocumentRenderer
    {
        throw new DecorationPatternException(self::class);
    }

    public function render(array $operations, Context $context, DocumentRendererConfig $rendererConfig): RendererResult
    {
        $result = new RendererResult();

        if ($context->getVersionId() !== Defaults::LIVE_VERSION) {
            foreach ($operations as $operation) {
                $result->addError(
                    $operation->getOrderId(),
                    DocumentException::generationError(
                        'Credit notes can only be generated from the LIVE order context.'
                    )
                );
            }

            return $result;
        }

        $ids = \array_map(static fn (DocumentGenerateOperation $operation) => $operation->getOrderId(), $operations);

        if ($ids === []) {
            return $result;
        }

        $referenceInvoices = [];
        $orders = new OrderCollection();

        foreach ($operations as $operation) {
            try {
                $orderId = $operation->getOrderId();
                $invoice = $this->referenceInvoiceLoader->load(
                    $orderId,
                    $operation->getReferencedDocumentId(),
                    $rendererConfig->deepLinkCode
                );

                if ($invoice === []) {
                    throw DocumentException::generationError(
                        'Cannot generate ZUGFeRD credit note document because no invoice document exists. OrderId: ' . $orderId
                    );
                }

                $documentRefer = json_decode($invoice['config'], true, 512, \JSON_THROW_ON_ERROR);

                $referenceInvoices[$orderId] = [
                    ...$invoice,
                    'documentNumber' => $invoice['documentNumber'] ?? $documentRefer['documentNumber'],
                    'config' => $documentRefer,
                ];

                $order = $this->getOrder($operation, Defaults::LIVE_VERSION, $context, $rendererConfig);
                $orders->add($order);

                $operation->setReferencedDocumentId($invoice['id']);
            } catch (\Throwable $exception) {
                $result->addError($operation->getOrderId(), $exception);
            }
        }

        $this->eventDispatcher->dispatch(new ZugferdCreditNoteOrdersEvent(
            $orders,
            $context,
            $operations
        ));

        foreach ($orders as $order) {
            $orderId = $order->getId();

            try {
                $operation = $operations[$orderId] ?? null;

                if ($operation === null) {
                    continue;
                }

                $forceDocumentCreation = $operation->getConfig()['forceDocumentCreation'] ?? true;

                if (!$forceDocumentCreation && $order->getDocuments()?->first()) {
                    continue;
                }

                $referenceDocument = $referenceInvoices[$orderId] ?? null;

                if ($referenceDocument === null) {
                    throw DocumentException::generationError(
                        'Cannot generate ZUGFeRD credit note document because no invoice document exists. OrderId: ' . $orderId
                    );
                }

                $liveLineItems = $order->getLineItems() ?? new OrderLineItemCollection();
                $liveCreditItems = $liveLineItems->filterByType(LineItem::CREDIT_LINE_ITEM_TYPE);

                if ($liveCreditItems->count() === 0) {
                    throw DocumentException::generationError(
                        'Cannot generate ZUGFeRD credit note document because no credit line items exists. OrderId: ' . $orderId
                    );
                }

                $referencedInvoiceId = $operation->getReferencedDocumentId();
                $invoiceCreditIds = $this->getCreditIdsOnInvoiceDocument($referencedInvoiceId);
                $creditNoteItemIds = $this->getPreviouslyCreditedIdsForInvoice($referencedInvoiceId);

                $creditItems = $liveCreditItems->filter(
                    static fn (OrderLineItemEntity $item) => !\in_array($item->getId(), $invoiceCreditIds, true)
                        && !\in_array($item->getId(), $creditNoteItemIds, true)
                );

                if ($creditItems->count() === 0) {
                    throw DocumentException::generationError(
                        'Cannot generate ZUGFeRD credit note document because no unprocessed credit line items exists. OrderId: ' . $orderId
                    );
                }

                $config = clone $this->documentConfigLoader->load(
                    CreditNoteRenderer::TYPE,
                    $order->getSalesChannelId(),
                    $context
                );

                $config->merge($operation->getConfig());
                $config->merge(['fileTypes' => [ZugferdRenderer::FILE_EXTENSION]]);

                $number = $config->getDocumentNumber() ?: $this->getNumber($context, $order, $operation);

                $config->merge([
                    'documentDate' => $operation->getConfig()['documentDate'] ?? $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'documentNumber' => $number,
                    'custom' => [
                        'creditNoteNumber' => $number,
                        'invoiceNumber' => $referenceDocument['documentNumber'],
                    ],
                ]);

                if ($operation->getOrderVersionId() === Defaults::LIVE_VERSION) {
                    $operation->setOrderVersionId($this->orderRepository->createVersion($order->getId(), $context, 'document'));
                }

                if ($operation->isStatic()) {
                    $result->addSuccess(
                        $orderId,
                        new RenderedDocument($number, $config->buildName(), $operation->getFileType(), $config->jsonSerialize())
                    );

                    continue;
                }

                $creditOrder = $this->prepareCreditOrder($order, $creditItems);
                $content = $this->documentBuilder->buildDocumentWithType(
                    $creditOrder,
                    $config,
                    $context,
                    ZugferdInvoiceType::CREDITNOTE,
                    $referenceDocument
                );

                $result->addSuccess(
                    $orderId,
                    new RenderedDocument(
                        $number,
                        $config->buildName(),
                        ZugferdRenderer::FILE_EXTENSION,
                        $config->jsonSerialize(),
                        ZugferdRenderer::FILE_CONTENT_TYPE,
                        $content
                    )
                );
            } catch (\Throwable $exception) {
                $result->addError($orderId, $exception);
            }
        }

        return $result;
    }

    private function getOrder(
        DocumentGenerateOperation $operation,
        string $versionId,
        Context $context,
        DocumentRendererConfig $rendererConfig
    ): OrderEntity {
        $languageId = $this->getOrdersLanguageId(
            [$operation->getOrderId()],
            $versionId,
            $this->connection
        )[0]['language_id'];

        $languageIdChain = \array_values(
            \array_unique(
                \array_filter([$languageId, ...$context->getLanguageIdChain()])
            )
        );

        $order = $this->loadOrder($operation, $versionId, $context, $languageIdChain, $rendererConfig);

        if ($order === null) {
            throw DocumentException::orderNotFound($operation->getOrderId());
        }

        return $order;
    }

    /**
     * @param list<string> $languageIdChain
     */
    private function loadOrder(
        DocumentGenerateOperation $operation,
        string $versionId,
        Context $context,
        array $languageIdChain,
        DocumentRendererConfig $rendererConfig,
    ): ?OrderEntity {
        $versionContext = $context->createWithVersionId($versionId)->assign([
            'languageIdChain' => $languageIdChain,
        ]);

        $criteria = OrderDocumentCriteriaFactory::create(
            [$operation->getOrderId()],
            $rendererConfig->deepLinkCode,
            self::TYPE
        );
        $criteria->getAssociation('lineItems')->addFilter(
            new EqualsFilter('type', LineItem::CREDIT_LINE_ITEM_TYPE)
        );

        $this->eventDispatcher->dispatch(new DocumentOrderCriteriaEvent(
            $criteria,
            $context,
            [$operation->getOrderId() => $operation],
            $rendererConfig,
            self::TYPE
        ));

        return $this->orderRepository->search($criteria, $versionContext)->getEntities()->first();
    }

    private function prepareCreditOrder(OrderEntity $order, OrderLineItemCollection $creditItems): OrderEntity
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
        $order->setDeliveries(new OrderDeliveryCollection());
        $order->setPrice($price);
        $order->setShippingTotal(0.0);
        $order->setPositionPrice($price->getPositionPrice());
        $order->setAmountNet($price->getNetPrice());
        $order->setAmountTotal($price->getTotalPrice());

        return $order;
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

    private function getNumber(Context $context, OrderEntity $order, DocumentGenerateOperation $operation): string
    {
        return $this->numberRangeValueGenerator->getValue(
            'document_' . CreditNoteRenderer::TYPE,
            $context,
            $order->getSalesChannelId(),
            $operation->isPreview()
        );
    }

    /**
     * @return list<string>
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
                AND oli.type = :creditType;
        ';

        $binaryIds = $this->connection->fetchFirstColumn($sql, [
            'referencedInvoiceId' => Uuid::fromHexToBytes($referencedInvoiceId),
            'creditType' => LineItem::CREDIT_LINE_ITEM_TYPE,
        ]);

        return array_map(static fn ($id): string => Uuid::fromBytesToHex($id), $binaryIds);
    }

    /**
     * @return list<string>
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
            'creditTechnicalName' => [CreditNoteRenderer::TYPE, self::TYPE, ZugferdEmbeddedCreditNoteRenderer::TYPE],
            'creditType' => LineItem::CREDIT_LINE_ITEM_TYPE,
        ], [
            'creditTechnicalName' => ArrayParameterType::STRING,
        ]);

        return array_map(static fn ($id): string => Uuid::fromBytesToHex($id), $binaryIds);
    }
}
