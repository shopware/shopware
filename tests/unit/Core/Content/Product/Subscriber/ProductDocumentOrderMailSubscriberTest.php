<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedCriteriaEvent;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Events\FlowSendMailActionEvent;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeCollection;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\Subscriber\ProductDocumentOrderMailSubscriber;
use Shopware\Core\Content\Shared\MailFlow\Event\MailFlowDataCriteriaEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDocumentOrderMailSubscriber::class)]
class ProductDocumentOrderMailSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame([
            CheckoutOrderPlacedCriteriaEvent::class => 'addProductDocumentAssociationsToCheckoutOrderCriteria',
            MailFlowDataCriteriaEvent::class => 'addProductDocumentAssociationsToMailFlowCriteria',
            'mail-flow.data.order.criteria.event' => 'addProductDocumentAssociationsToMailFlowCriteria',
            FlowSendMailActionEvent::class => 'attachProductDocumentsToOrderConfirmationMail',
        ], ProductDocumentOrderMailSubscriber::getSubscribedEvents());
    }

    public function testAddsProductDocumentAssociationsToCheckoutOrderCriteria(): void
    {
        $criteria = new Criteria();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());

        $event = new CheckoutOrderPlacedCriteriaEvent($criteria, $salesChannelContext);

        $this->createSubscriber()->addProductDocumentAssociationsToCheckoutOrderCriteria($event);

        $this->assertProductDocumentCriteria($criteria);
    }

    public function testAddsProductDocumentAssociationsToOrderMailFlowCriteria(): void
    {
        $criteria = new Criteria();
        $event = new MailFlowDataCriteriaEvent(OrderDefinition::ENTITY_NAME, $criteria, Context::createDefaultContext());

        $this->createSubscriber()->addProductDocumentAssociationsToMailFlowCriteria($event);

        $this->assertProductDocumentCriteria($criteria);
    }

    public function testIgnoresNonOrderMailFlowCriteria(): void
    {
        $criteria = new Criteria();
        $event = new MailFlowDataCriteriaEvent(CountryDefinition::ENTITY_NAME, $criteria, Context::createDefaultContext());

        $this->createSubscriber()->addProductDocumentAssociationsToMailFlowCriteria($event);

        static::assertSame([], $criteria->getAssociations());
    }

    public function testIgnoresNonCheckoutOrderPlacedFlows(): void
    {
        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->never())->method('getAttachment');

        $subscriber = $this->createSubscriber($mediaService);
        $event = $this->createFlowSendMailActionEvent(
            new OrderEntity(),
            $this->createOrderConfirmationMailTemplate(),
            'other.event'
        );

        $subscriber->attachProductDocumentsToOrderConfirmationMail($event);

        static::assertNull($event->getDataBag()->get('binAttachments'));
    }

    public function testIgnoresNonOrderConfirmationMailTemplates(): void
    {
        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->never())->method('getAttachment');

        $subscriber = $this->createSubscriber($mediaService);
        $event = $this->createFlowSendMailActionEvent(
            new OrderEntity(),
            $this->createMailTemplate('other.mail.type'),
        );

        $subscriber->attachProductDocumentsToOrderConfirmationMail($event);

        static::assertNull($event->getDataBag()->get('binAttachments'));
    }

    public function testIgnoresMissingOrderData(): void
    {
        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->never())->method('getAttachment');

        $subscriber = $this->createSubscriber($mediaService);
        $event = new FlowSendMailActionEvent(
            new DataBag(),
            $this->createOrderConfirmationMailTemplate(),
            new StorableFlow(CheckoutOrderPlacedEvent::EVENT_NAME, Context::createDefaultContext()),
        );

        $subscriber->attachProductDocumentsToOrderConfirmationMail($event);

        static::assertNull($event->getDataBag()->get('binAttachments'));
    }

    public function testIgnoresInvalidOrderData(): void
    {
        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->never())->method('getAttachment');

        $event = new FlowSendMailActionEvent(
            new DataBag(),
            $this->createOrderConfirmationMailTemplate(),
            new StorableFlow(CheckoutOrderPlacedEvent::EVENT_NAME, Context::createDefaultContext(), [], [OrderAware::ORDER => 'invalid-order-data']),
        );

        $this->createSubscriber($mediaService)->attachProductDocumentsToOrderConfirmationMail($event);

        static::assertNull($event->getDataBag()->get('binAttachments'));
    }

    public function testIgnoresMailTemplateWithoutType(): void
    {
        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->never())->method('getAttachment');

        $event = $this->createFlowSendMailActionEvent(new OrderEntity(), new MailTemplateEntity());

        $this->createSubscriber($mediaService)->attachProductDocumentsToOrderConfirmationMail($event);

        static::assertNull($event->getDataBag()->get('binAttachments'));
    }

    public function testIgnoresOrderWithoutLineItems(): void
    {
        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->never())->method('getAttachment');

        $event = $this->createFlowSendMailActionEvent(new OrderEntity(), $this->createOrderConfirmationMailTemplate());

        $this->createSubscriber($mediaService)->attachProductDocumentsToOrderConfirmationMail($event);

        static::assertNull($event->getDataBag()->get('binAttachments'));
    }

    public function testIgnoresNonProductLineItems(): void
    {
        $lineItem = new OrderLineItemEntity();
        $lineItem->setId(Uuid::randomHex());
        $lineItem->setType(LineItem::CUSTOM_LINE_ITEM_TYPE);

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->never())->method('getAttachment');

        $event = $this->createFlowSendMailActionEvent(
            $this->createOrder([$lineItem]),
            $this->createOrderConfirmationMailTemplate(),
        );

        $this->createSubscriber($mediaService)->attachProductDocumentsToOrderConfirmationMail($event);

        static::assertNull($event->getDataBag()->get('binAttachments'));
    }

    public function testIgnoresProductLineItemWithoutLoadedProduct(): void
    {
        $lineItem = new OrderLineItemEntity();
        $lineItem->setId(Uuid::randomHex());
        $lineItem->setType(LineItem::PRODUCT_LINE_ITEM_TYPE);

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->never())->method('getAttachment');

        $event = $this->createFlowSendMailActionEvent(
            $this->createOrder([$lineItem]),
            $this->createOrderConfirmationMailTemplate(),
        );

        $this->createSubscriber($mediaService)->attachProductDocumentsToOrderConfirmationMail($event);

        static::assertNull($event->getDataBag()->get('binAttachments'));
    }

    public function testAttachesProductDocumentsAndPreservesExistingAttachmentsAndDeduplicatesMedia(): void
    {
        $firstMedia = $this->createMedia('first-media-id', 'first');
        $secondMedia = $this->createMedia('second-media-id', 'second');

        $order = $this->createOrder([
            $this->createProductLineItem([
                $this->createProductDocument('first-document-id', $firstMedia),
                $this->createProductDocument('duplicate-document-id', $firstMedia),
            ]),
            $this->createProductLineItem([
                $this->createProductDocument('second-document-id', $secondMedia),
            ]),
        ]);

        $firstAttachment = ['content' => 'first-content', 'fileName' => 'first.pdf', 'mimeType' => 'application/pdf'];
        $secondAttachment = ['content' => 'second-content', 'fileName' => 'second.pdf', 'mimeType' => 'application/pdf'];

        $mediaService = $this->createMock(MediaService::class);
        $mediaService
            ->expects($this->exactly(2))
            ->method('getAttachment')
            ->willReturnCallback(static function (MediaEntity $media) use ($firstMedia, $secondMedia, $firstAttachment, $secondAttachment): array {
                return match ($media->getId()) {
                    $firstMedia->getId() => $firstAttachment,
                    $secondMedia->getId() => $secondAttachment,
                    default => throw new \RuntimeException('Unexpected media'),
                };
            });

        $event = $this->createFlowSendMailActionEvent($order, $this->createOrderConfirmationMailTemplate());
        $event->getDataBag()->set('binAttachments', [
            ['content' => 'existing-content', 'fileName' => 'existing.pdf', 'mimeType' => 'application/pdf'],
        ]);

        $this->createSubscriber($mediaService)->attachProductDocumentsToOrderConfirmationMail($event);

        static::assertSame([
            ['content' => 'existing-content', 'fileName' => 'existing.pdf', 'mimeType' => 'application/pdf'],
            $firstAttachment,
            $secondAttachment,
        ], $event->getDataBag()->all('binAttachments'));
    }

    public function testAttachesProductDocumentsAndPreservesExistingPlainArrayAttachments(): void
    {
        $media = $this->createMedia('media-id', 'manual');
        $order = $this->createOrder([
            $this->createProductLineItem([
                $this->createProductDocument('document-id', $media),
            ]),
        ]);

        $existingAttachment = ['content' => 'existing-content', 'fileName' => 'existing.pdf', 'mimeType' => 'application/pdf'];
        $documentAttachment = ['content' => 'document-content', 'fileName' => 'manual.pdf', 'mimeType' => 'application/pdf'];

        $mediaService = $this->createMock(MediaService::class);
        $mediaService
            ->expects($this->once())
            ->method('getAttachment')
            ->with($media, static::isInstanceOf(Context::class))
            ->willReturn($documentAttachment);

        $dataBag = new class extends DataBag {
            /**
             * @var array{content: string, fileName: string, mimeType: string}
             */
            public array $existingAttachment;

            public function get(string $key, mixed $default = null): mixed
            {
                if ($key === 'binAttachments') {
                    return [$this->existingAttachment];
                }

                return parent::get($key, $default);
            }
        };
        $dataBag->existingAttachment = $existingAttachment;

        $event = new FlowSendMailActionEvent(
            $dataBag,
            $this->createOrderConfirmationMailTemplate(),
            new StorableFlow(CheckoutOrderPlacedEvent::EVENT_NAME, Context::createDefaultContext(), [], [OrderAware::ORDER => $order]),
        );

        $this->createSubscriber($mediaService)->attachProductDocumentsToOrderConfirmationMail($event);

        static::assertSame([$existingAttachment, $documentAttachment], $event->getDataBag()->all('binAttachments'));
    }

    public function testAttachesProductDocumentsFromNestedLineItems(): void
    {
        $media = $this->createMedia('media-id', 'manual');
        $parentLineItem = new OrderLineItemEntity();
        $parentLineItem->setId(Uuid::randomHex());
        $parentLineItem->setType(LineItem::CUSTOM_LINE_ITEM_TYPE);
        $parentLineItem->setChildren(new OrderLineItemCollection([
            $this->createProductLineItem([
                $this->createProductDocument('document-id', $media),
            ]),
        ]));

        $attachment = ['content' => 'content', 'fileName' => 'manual.pdf', 'mimeType' => 'application/pdf'];

        $mediaService = $this->createMock(MediaService::class);
        $mediaService
            ->expects($this->once())
            ->method('getAttachment')
            ->willReturn($attachment);

        $event = $this->createFlowSendMailActionEvent(
            $this->createOrder([$parentLineItem]),
            $this->createOrderConfirmationMailTemplate(),
        );

        $this->createSubscriber($mediaService)->attachProductDocumentsToOrderConfirmationMail($event);

        static::assertSame([$attachment], $event->getDataBag()->all('binAttachments'));
    }

    public function testLoadsMailTemplateTypeWhenAssociationIsMissing(): void
    {
        $media = $this->createMedia('media-id', 'manual');
        $order = $this->createOrder([
            $this->createProductLineItem([
                $this->createProductDocument('document-id', $media),
            ]),
        ]);

        $mailTemplateType = new MailTemplateTypeEntity();
        $mailTemplateType->setId('mail-template-type-id');
        $mailTemplateType->setTechnicalName(MailTemplateTypes::MAILTYPE_ORDER_CONFIRM);

        $mailTemplate = new MailTemplateEntity();
        $mailTemplate->setMailTemplateTypeId('mail-template-type-id');

        $attachment = ['content' => 'content', 'fileName' => 'manual.pdf', 'mimeType' => 'application/pdf'];

        $mediaService = $this->createMock(MediaService::class);
        $mediaService
            ->expects($this->once())
            ->method('getAttachment')
            ->willReturn($attachment);

        $event = $this->createFlowSendMailActionEvent($order, $mailTemplate);

        /** @var StaticEntityRepository<MailTemplateTypeCollection> $mailTemplateTypeRepository */
        $mailTemplateTypeRepository = new StaticEntityRepository([
            new MailTemplateTypeCollection([$mailTemplateType]),
        ]);

        $this->createSubscriber($mediaService, $mailTemplateTypeRepository)->attachProductDocumentsToOrderConfirmationMail($event);

        static::assertSame([$attachment], $event->getDataBag()->all('binAttachments'));
    }

    public function testSkipsMissingMediaAndLogsWarning(): void
    {
        $order = $this->createOrder([
            $this->createProductLineItem([
                $this->createProductDocument('document-id'),
            ]),
        ]);

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->never())->method('getAttachment');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                'Product document is missing media for order confirmation mail attachment.',
                static::arrayHasKey('productDocumentId'),
            );

        $event = $this->createFlowSendMailActionEvent($order, $this->createOrderConfirmationMailTemplate());

        $this->createSubscriber($mediaService, null, $logger)->attachProductDocumentsToOrderConfirmationMail($event);

        static::assertNull($event->getDataBag()->get('binAttachments'));
    }

    public function testAttachmentErrorsAreLoggedAndSkipped(): void
    {
        $media = $this->createMedia('media-id', 'manual');
        $order = $this->createOrder([
            $this->createProductLineItem([
                $this->createProductDocument('document-id', $media),
            ]),
        ]);

        $mediaService = $this->createMock(MediaService::class);
        $mediaService
            ->expects($this->once())
            ->method('getAttachment')
            ->willThrowException(new \RuntimeException('Could not load file'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                'Could not attach product document to order confirmation mail.',
                static::arrayHasKey('exception'),
            );

        $event = $this->createFlowSendMailActionEvent($order, $this->createOrderConfirmationMailTemplate());

        $this->createSubscriber($mediaService, null, $logger)->attachProductDocumentsToOrderConfirmationMail($event);

        static::assertSame([], $event->getDataBag()->all('binAttachments'));
    }

    private function assertProductDocumentCriteria(Criteria $criteria): void
    {
        static::assertTrue($criteria->hasAssociation('lineItems'));

        $productDocumentsCriteria = $criteria->getAssociation('lineItems.product.productDocuments');
        static::assertTrue($productDocumentsCriteria->hasAssociation('media'));

        $sorting = $productDocumentsCriteria->getSorting();
        static::assertCount(1, $sorting);
        static::assertSame('position', $sorting[0]->getField());
    }

    /**
     * @param StaticEntityRepository<MailTemplateTypeCollection>|null $mailTemplateTypeRepository
     */
    private function createSubscriber(
        ?MediaService $mediaService = null,
        ?StaticEntityRepository $mailTemplateTypeRepository = null,
        ?LoggerInterface $logger = null
    ): ProductDocumentOrderMailSubscriber {
        /** @var StaticEntityRepository<MailTemplateTypeCollection> $mailTemplateTypeRepository */
        $mailTemplateTypeRepository ??= new StaticEntityRepository([]);

        return new ProductDocumentOrderMailSubscriber(
            $mediaService ?? $this->createMock(MediaService::class),
            $mailTemplateTypeRepository,
            $logger ?? $this->createMock(LoggerInterface::class),
        );
    }

    private function createFlowSendMailActionEvent(
        OrderEntity $order,
        MailTemplateEntity $mailTemplate,
        string $flowName = CheckoutOrderPlacedEvent::EVENT_NAME
    ): FlowSendMailActionEvent {
        return new FlowSendMailActionEvent(
            new DataBag(),
            $mailTemplate,
            new StorableFlow($flowName, Context::createDefaultContext(), [], [OrderAware::ORDER => $order]),
        );
    }

    private function createOrderConfirmationMailTemplate(): MailTemplateEntity
    {
        return $this->createMailTemplate(MailTemplateTypes::MAILTYPE_ORDER_CONFIRM);
    }

    private function createMailTemplate(string $technicalName): MailTemplateEntity
    {
        $mailTemplateType = new MailTemplateTypeEntity();
        $mailTemplateType->setTechnicalName($technicalName);

        $mailTemplate = new MailTemplateEntity();
        $mailTemplate->setMailTemplateType($mailTemplateType);

        return $mailTemplate;
    }

    /**
     * @param list<OrderLineItemEntity> $lineItems
     */
    private function createOrder(array $lineItems): OrderEntity
    {
        $order = new OrderEntity();
        $order->setLineItems(new OrderLineItemCollection($lineItems));

        return $order;
    }

    /**
     * @param list<ProductDocumentEntity> $productDocuments
     */
    private function createProductLineItem(array $productDocuments): OrderLineItemEntity
    {
        $product = new ProductEntity();
        $product->setProductDocuments(new ProductDocumentCollection($productDocuments));

        $lineItem = new OrderLineItemEntity();
        $lineItem->setId(Uuid::randomHex());
        $lineItem->setType(LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItem->setProduct($product);

        return $lineItem;
    }

    private function createProductDocument(string $id, ?MediaEntity $media = null): ProductDocumentEntity
    {
        $productDocument = new ProductDocumentEntity();
        $productDocument->setId($id);
        $productDocument->setMediaId($media?->getId() ?? 'missing-media-id');

        if ($media instanceof MediaEntity) {
            $productDocument->setMedia($media);
        }

        return $productDocument;
    }

    private function createMedia(string $id, string $fileName): MediaEntity
    {
        $media = new MediaEntity();
        $media->setId($id);
        $media->setFileName($fileName);
        $media->setFileExtension('pdf');
        $media->setMimeType('application/pdf');

        return $media;
    }
}
