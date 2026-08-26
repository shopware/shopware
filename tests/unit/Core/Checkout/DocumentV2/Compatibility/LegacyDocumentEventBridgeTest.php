<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Compatibility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Event\CreditNoteOrdersEvent;
use Shopware\Core\Checkout\Document\Event\DeliveryNoteOrdersEvent;
use Shopware\Core\Checkout\Document\Event\DocumentOrderEvent;
use Shopware\Core\Checkout\Document\Event\InvoiceOrdersEvent;
use Shopware\Core\Checkout\Document\Event\StornoOrdersEvent;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Zugferd\ZugferdInvoiceOrdersEvent;
use Shopware\Core\Checkout\DocumentV2\Compatibility\LegacyDocumentEventBridge;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(LegacyDocumentEventBridge::class)]
class LegacyDocumentEventBridgeTest extends TestCase
{
    public function testDispatchesTheMatchingV1EventForEachBaseDocumentType(): void
    {
        $expectations = [
            DocumentType::INVOICE->value => InvoiceOrdersEvent::class,
            DocumentType::CANCELLATION_INVOICE->value => StornoOrdersEvent::class,
            DocumentType::CREDIT_NOTE->value => CreditNoteOrdersEvent::class,
            DocumentType::DELIVERY_NOTE->value => DeliveryNoteOrdersEvent::class,
        ];

        foreach ($expectations as $documentType => $eventClass) {
            $dispatched = $this->dispatch($this->createRequest($documentType, [DocumentFormat::PDF->value]));

            static::assertCount(1, $dispatched, $documentType);
            static::assertInstanceOf($eventClass, $dispatched[0], $documentType);
        }
    }

    public function testDispatchesNothingForATypeThatOnlyExistsInV2(): void
    {
        $dispatched = $this->dispatch($this->createRequest('quotes', [DocumentFormat::PDF->value]));

        static::assertSame([], $dispatched);
    }

    public function testDispatchesTheZugferdEventAlongsideTheBaseEventWhenAZugferdFormatIsRequested(): void
    {
        $dispatched = $this->dispatch($this->createRequest(
            DocumentType::INVOICE->value,
            [DocumentFormat::PDF->value, DocumentFormat::ZUGFERD_XML->value],
        ));

        static::assertCount(2, $dispatched);
        static::assertInstanceOf(InvoiceOrdersEvent::class, $dispatched[0]);
        static::assertInstanceOf(ZugferdInvoiceOrdersEvent::class, $dispatched[1]);
    }

    public function testDispatchesNoZugferdEventForDeliveryNotesWhichHaveNoZugferdVariant(): void
    {
        $dispatched = $this->dispatch($this->createRequest(
            DocumentType::DELIVERY_NOTE->value,
            [DocumentFormat::PDF->value],
        ));

        static::assertCount(1, $dispatched);
        static::assertInstanceOf(DeliveryNoteOrdersEvent::class, $dispatched[0]);
    }

    public function testTheOperationIsKeyedByOrderIdSoV1SubscribersCanIndexIntoIt(): void
    {
        $order = $this->createOrder();
        $request = $this->createRequest(DocumentType::INVOICE->value, [DocumentFormat::PDF->value]);

        $dispatched = $this->dispatch($request, $order, orderVersionId: 'order-version-id', preview: true);

        $operations = $dispatched[0]->getOperations();

        static::assertArrayHasKey($order->getId(), $operations);

        $operation = $operations[$order->getId()];

        static::assertSame($request->orderId, $operation->getOrderId());
        static::assertSame(FileTypes::PDF, $operation->getFileType());
        static::assertSame('order-version-id', $operation->getOrderVersionId());
        static::assertTrue($operation->isPreview());
    }

    public function testTheOperationConfigCarriesTheRequestFieldsButNoCustomBag(): void
    {
        $request = new DocumentGenerationRequest(
            orderId: Uuid::randomHex(),
            documentType: DocumentType::INVOICE->value,
            requestedFormats: [DocumentFormat::PDF->value],
            documentNumber: '1001',
            documentComment: 'a comment',
            documentDate: '2026-08-26 10:00:00.000',
        );

        $config = $this->dispatch($request)[0]->getOperations()[$request->orderId]->getConfig();

        static::assertSame('1001', $config['documentNumber']);
        static::assertSame('a comment', $config['documentComment']);
        static::assertSame('2026-08-26 10:00:00.000', $config['documentDate']);
        static::assertArrayNotHasKey('custom', $config);
    }

    public function testSubscriberChangesToTheOrderAreVisibleToTheCaller(): void
    {
        $order = $this->createOrder();
        $replacement = new OrderLineItemEntity();
        $replacement->setId(Uuid::randomHex());

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            InvoiceOrdersEvent::class,
            static function (InvoiceOrdersEvent $event) use ($replacement): void {
                foreach ($event->getOrders() as $eventOrder) {
                    $eventOrder->setLineItems(new OrderLineItemCollection([$replacement]));
                }
            },
        );

        $bridge = new LegacyDocumentEventBridge($eventDispatcher);
        $bridge->dispatchOrderEvents(
            $order,
            $this->createRequest(DocumentType::INVOICE->value, [DocumentFormat::PDF->value]),
            Defaults::LIVE_VERSION,
            false,
            Context::createDefaultContext(),
        );

        static::assertSame([$replacement], array_values($order->getLineItems()?->getElements() ?? []));
    }

    /**
     * @param list<string> $requestedFormats
     */
    private function createRequest(string $documentType, array $requestedFormats): DocumentGenerationRequest
    {
        return new DocumentGenerationRequest(
            orderId: Uuid::randomHex(),
            documentType: $documentType,
            requestedFormats: $requestedFormats,
        );
    }

    private function createOrder(): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setLineItems(new OrderLineItemCollection());

        return $order;
    }

    /**
     * @return list<DocumentOrderEvent>
     */
    private function dispatch(
        DocumentGenerationRequest $request,
        ?OrderEntity $order = null,
        string $orderVersionId = Defaults::LIVE_VERSION,
        bool $preview = false,
    ): array {
        $dispatched = [];

        $eventDispatcher = new EventDispatcher();
        foreach ([
            InvoiceOrdersEvent::class,
            StornoOrdersEvent::class,
            CreditNoteOrdersEvent::class,
            DeliveryNoteOrdersEvent::class,
            ZugferdInvoiceOrdersEvent::class,
        ] as $eventClass) {
            $eventDispatcher->addListener(
                $eventClass,
                static function (DocumentOrderEvent $event) use (&$dispatched): void {
                    $dispatched[] = $event;
                },
            );
        }

        $bridge = new LegacyDocumentEventBridge($eventDispatcher);
        $bridge->dispatchOrderEvents(
            $order ?? $this->createOrderFor($request),
            $request,
            $orderVersionId,
            $preview,
            Context::createDefaultContext(),
        );

        return $dispatched;
    }

    private function createOrderFor(DocumentGenerationRequest $request): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId($request->orderId);

        return $order;
    }
}
