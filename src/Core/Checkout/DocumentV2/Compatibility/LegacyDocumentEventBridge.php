<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Compatibility;

use Shopware\Core\Checkout\Document\Event\CreditNoteOrdersEvent;
use Shopware\Core\Checkout\Document\Event\DeliveryNoteOrdersEvent;
use Shopware\Core\Checkout\Document\Event\DocumentOrderEvent;
use Shopware\Core\Checkout\Document\Event\InvoiceOrdersEvent;
use Shopware\Core\Checkout\Document\Event\StornoOrdersEvent;
use Shopware\Core\Checkout\Document\Event\ZugferdCancellationInvoiceOrdersEvent;
use Shopware\Core\Checkout\Document\Event\ZugferdCreditNoteOrdersEvent;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Service\HtmlRenderer;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Document\Zugferd\ZugferdInvoiceOrdersEvent;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Dispatches the document generation v1 order events from the v2 pipeline, so subscribers written against v1
 * keep running while both implementations coexist. Delete this class together with the v1 domain in v6.9.0.
 *
 * Version 1 modelled the ZUGFeRD variants as separate document types with their own renderers and events,
 * while v2 models them as formats of the four base types. The ZUGFeRD events are therefore resolved from the
 * requested formats rather than the type. Requesting a PDF alongside a ZUGFeRD format dispatches both the base
 * and the ZUGFeRD event, which is what the v1 embedded renderers did as well: they delegated to the invoice
 * renderer and to the electronic renderer, and each of those dispatched its own event.
 *
 * This is just a compatibility temporary bridge and will be removed together with the v1 document domain
 *
 * @internal
 */
#[Package('after-sales')]
final readonly class LegacyDocumentEventBridge
{
    private const ZUGFERD_FORMATS = [
        DocumentFormat::ZUGFERD_XML->value,
        DocumentFormat::ZUGFERD_EMBEDDED_PDF->value,
    ];

    /**
     * @var array<string, class-string<DocumentOrderEvent>>
     */
    private const TYPE_EVENTS = [
        DocumentType::INVOICE->value => InvoiceOrdersEvent::class,
        DocumentType::CANCELLATION_INVOICE->value => StornoOrdersEvent::class,
        DocumentType::CREDIT_NOTE->value => CreditNoteOrdersEvent::class,
        DocumentType::DELIVERY_NOTE->value => DeliveryNoteOrdersEvent::class,
    ];

    /**
     * @var array<string, class-string<DocumentOrderEvent>>
     */
    private const ZUGFERD_TYPE_EVENTS = [
        DocumentType::INVOICE->value => ZugferdInvoiceOrdersEvent::class,
        DocumentType::CANCELLATION_INVOICE->value => ZugferdCancellationInvoiceOrdersEvent::class,
        DocumentType::CREDIT_NOTE->value => ZugferdCreditNoteOrdersEvent::class,
    ];

    public function __construct(private EventDispatcherInterface $eventDispatcher)
    {
    }

    /**
     * Must be called directly after the order is loaded and before the data providers run, so that entity
     * changes made by subscribers - line item rewrites above all - reach both the Twig scope and the render
     * data the providers derive from the same order.
     */
    public function dispatchOrderEvents(
        OrderEntity $order,
        DocumentGenerationRequest $generationRequest,
        string $orderVersionId,
        bool $preview,
        Context $context,
    ): void {
        $eventClasses = $this->resolveEventClasses($generationRequest);

        if ($eventClasses === []) {
            return;
        }

        $orders = new OrderCollection([$order]);
        $operations = [
            $order->getId() => $this->createOperation($generationRequest, $orderVersionId, $preview),
        ];

        foreach ($eventClasses as $eventClass) {
            $this->eventDispatcher->dispatch(new $eventClass($orders, $context, $operations));
        }
    }

    /**
     * @return list<class-string<DocumentOrderEvent>>
     */
    private function resolveEventClasses(DocumentGenerationRequest $generationRequest): array
    {
        $documentType = $generationRequest->documentType;
        $eventClasses = [];

        if (isset(self::TYPE_EVENTS[$documentType])) {
            $eventClasses[] = self::TYPE_EVENTS[$documentType];
        }

        $rendersZugferd = array_intersect(self::ZUGFERD_FORMATS, $generationRequest->requestedFormats) !== [];

        if ($rendersZugferd && isset(self::ZUGFERD_TYPE_EVENTS[$documentType])) {
            $eventClasses[] = self::ZUGFERD_TYPE_EVENTS[$documentType];
        }

        return $eventClasses;
    }

    /**
     * The operation is keyed by order id by the caller, because v1 subscribers index into it without checking
     * and would fatal on a missing entry. Its config carries only the fields v2 knows: there is no equivalent
     * of the v1 `custom` bag, and leaving the key out lets subscribers take their existing null branch instead
     * of acting on an empty array.
     */
    private function createOperation(
        DocumentGenerationRequest $generationRequest,
        string $orderVersionId,
        bool $preview,
    ): DocumentGenerateOperation {
        $config = array_filter([
            'documentNumber' => $generationRequest->documentNumber,
            'documentDate' => $generationRequest->documentDate,
            'documentComment' => $generationRequest->documentComment,
        ], static fn (?string $value): bool => $value !== null);

        $operation = new DocumentGenerateOperation(
            orderId: $generationRequest->orderId,
            fileType: $this->resolveFileType($generationRequest->requestedFormats),
            config: $config,
            referencedDocumentId: $generationRequest->referencedDocumentId,
            preview: $preview,
        );

        $operation->setOrderVersionId($orderVersionId);

        return $operation;
    }

    /**
     * @param list<string> $requestedFormats
     */
    private function resolveFileType(array $requestedFormats): string
    {
        $pdfFormats = [DocumentFormat::PDF->value, DocumentFormat::ZUGFERD_EMBEDDED_PDF->value];

        if (array_intersect($pdfFormats, $requestedFormats) !== []) {
            return FileTypes::PDF;
        }

        if (\in_array(DocumentFormat::ZUGFERD_XML->value, $requestedFormats, true)) {
            return FileTypes::XML;
        }

        return HtmlRenderer::FILE_EXTENSION;
    }
}
