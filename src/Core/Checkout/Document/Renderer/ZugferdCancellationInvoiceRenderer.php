<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Renderer;

use Doctrine\DBAL\Connection;
use horstoeko\zugferd\codelists\ZugferdInvoiceType;
use Psr\Clock\ClockInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Event\DocumentOrderCriteriaEvent;
use Shopware\Core\Checkout\Document\Event\ZugferdCancellationInvoiceOrdersEvent;
use Shopware\Core\Checkout\Document\Service\DocumentConfigLoader;
use Shopware\Core\Checkout\Document\Service\ReferenceInvoiceLoader;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Document\Zugferd\ZugferdBuilder;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('after-sales')]
class ZugferdCancellationInvoiceRenderer extends AbstractDocumentRenderer
{
    public const TYPE = 'zugferd_cancellation_invoice';

    /**
     * @internal
     *
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        protected EntityRepository $orderRepository,
        protected DocumentConfigLoader $documentConfigLoader,
        protected EventDispatcherInterface $eventDispatcher,
        protected NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        protected ReferenceInvoiceLoader $referenceInvoiceLoader,
        protected Connection $connection,
        protected ZugferdBuilder $documentBuilder,
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

        $ids = \array_map(fn (DocumentGenerateOperation $operation) => $operation->getOrderId(), $operations);

        if ($ids === []) {
            return $result;
        }

        $referenceInvoices = [];
        $orders = new OrderCollection();

        foreach ($operations as $operation) {
            try {
                $orderId = $operation->getOrderId();
                $invoice = $this->referenceInvoiceLoader->load($orderId, $operation->getReferencedDocumentId(), $rendererConfig->deepLinkCode);

                if ($invoice === []) {
                    throw DocumentException::referencedInvoiceNotFound(self::TYPE, $orderId);
                }

                $documentRefer = json_decode($invoice['config'], true, 512, \JSON_THROW_ON_ERROR);

                $referenceInvoices[$orderId] = [
                    ...$invoice,
                    'documentNumber' => $invoice['documentNumber'] ?? $documentRefer['documentNumber'],
                    'config' => $documentRefer,
                ];

                $order = $this->fetchOrder(
                    $operation,
                    $invoice['orderVersionId'],
                    $context,
                    $rendererConfig
                );

                $orders->add($order);
                $operation->setReferencedDocumentId($invoice['id']);

                if ($order->getVersionId()) {
                    $operation->setOrderVersionId($order->getVersionId());
                }
            } catch (\Throwable $exception) {
                $result->addError($operation->getOrderId(), $exception);
            }
        }

        $this->eventDispatcher->dispatch(new ZugferdCancellationInvoiceOrdersEvent(
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

                $referenceDocument = $referenceInvoices[$orderId] ?? null;

                if ($referenceDocument === null) {
                    throw DocumentException::referencedInvoiceNotFound(self::TYPE, $orderId);
                }

                $adjustedOrder = $this->handlePrices($order);

                $this->createDocument(
                    $result,
                    $adjustedOrder,
                    $operation,
                    $referenceDocument,
                    $context
                );
            } catch (\Throwable $exception) {
                $result->addError($orderId, $exception);
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $referenceDocument
     */
    protected function createDocument(
        RendererResult $renderResult,
        OrderEntity $order,
        DocumentGenerateOperation $operation,
        array $referenceDocument,
        Context $context
    ): void {
        $forceDocumentCreation = $operation->getConfig()['forceDocumentCreation'] ?? true;

        if (!$forceDocumentCreation && $order->getDocuments()?->first()) {
            return;
        }

        $config = clone $this->documentConfigLoader->load(
            StornoRenderer::TYPE,
            $order->getSalesChannelId(),
            $context
        );

        $config->merge($operation->getConfig());
        $config->merge(['fileTypes' => [ZugferdRenderer::FILE_EXTENSION]]);

        $number = $config->getDocumentNumber()
            ?: $this->getNumber($context, $order, $operation);

        $now = $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $config->merge([
            'documentDate' => $operation->getConfig()['documentDate'] ?? $now,
            'documentNumber' => $number,
            'custom' => [
                'stornoNumber' => $number,
                'invoiceNumber' => $referenceDocument['documentNumber'],
            ],
        ]);

        if ($operation->isStatic()) {
            $doc = new RenderedDocument(
                $number,
                $config->buildName(),
                $operation->getFileType(),
                $config->jsonSerialize()
            );

            $renderResult->addSuccess($order->getId(), $doc);

            return;
        }

        $content = $this->documentBuilder->buildDocumentWithType(
            $order,
            $config,
            $context,
            ZugferdInvoiceType::CORRECTION,
            $referenceDocument,
        );

        $renderResult->addSuccess(
            $order->getId(),
            new RenderedDocument(
                $number,
                $config->buildName(),
                ZugferdRenderer::FILE_EXTENSION,
                $config->jsonSerialize(),
                ZugferdRenderer::FILE_CONTENT_TYPE,
                $content
            )
        );
    }

    private function fetchOrder(DocumentGenerateOperation $operation, string $versionId, Context $context, DocumentRendererConfig $rendererConfig): OrderEntity
    {
        $orderId = $operation->getOrderId();

        ['language_id' => $languageId] = $this->getOrdersLanguageId(
            [$orderId],
            $versionId,
            $this->connection
        )[0];

        $versionContext = $context->createWithVersionId($versionId)->assign([
            'languageIdChain' => \array_values(\array_unique(\array_filter([
                $languageId,
                ...$context->getLanguageIdChain(),
            ]))),
        ]);

        $criteria = OrderDocumentCriteriaFactory::create(
            [$orderId],
            $rendererConfig->deepLinkCode,
            self::TYPE
        );

        $this->eventDispatcher->dispatch(new DocumentOrderCriteriaEvent(
            $criteria,
            $context,
            [$operation->getOrderId() => $operation],
            $rendererConfig,
            self::TYPE
        ));

        $order = $this->orderRepository->search($criteria, $versionContext)
            ->getEntities()
            ->first();

        if ($order === null) {
            throw DocumentException::orderNotFound($orderId);
        }

        return $order;
    }

    private function handlePrices(OrderEntity $order): OrderEntity
    {
        $this->invertLineItemPrices($order->getLineItems());

        foreach ($order->getPrice()->getCalculatedTaxes()->sortByTax()->getElements() as $tax) {
            $tax->setTax($tax->getTax() * -1);
            $tax->setPrice($tax->getPrice() * -1);
        }

        foreach ($order->getDeliveries() ?? [] as $delivery) {
            $delivery->setShippingCosts($this->invertCalculatedPrice($delivery->getShippingCosts()));
        }

        $order->setShippingTotal($order->getShippingTotal() * -1);
        $order->setAmountNet($order->getAmountNet() * -1);
        $order->setAmountTotal($order->getAmountTotal() * -1);

        $currentOrderCartPrice = $order->getPrice();

        $cartPrice = new CartPrice(
            $currentOrderCartPrice->getNetPrice() * -1,
            $currentOrderCartPrice->getTotalPrice() * -1,
            $currentOrderCartPrice->getPositionPrice() * -1,
            $currentOrderCartPrice->getCalculatedTaxes(),
            $currentOrderCartPrice->getTaxRules(),
            $currentOrderCartPrice->getTaxStatus(),
            $currentOrderCartPrice->getRawTotal() * -1,
        );

        $order->setPrice($cartPrice);

        return $order;
    }

    private function invertLineItemPrices(?OrderLineItemCollection $lineItems): void
    {
        if ($lineItems === null) {
            return;
        }

        foreach ($lineItems as $lineItem) {
            $lineItem->setTotalPrice($lineItem->getTotalPrice() * -1);
            $lineItem->setQuantity($lineItem->getQuantity() * -1);

            $lineItemPrice = $lineItem->getPrice();

            if ($lineItemPrice !== null) {
                $lineItem->setPrice($this->invertCalculatedPrice($lineItemPrice));
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
            'document_' . StornoRenderer::TYPE,
            $context,
            $order->getSalesChannelId(),
            $operation->isPreview()
        );
    }
}
