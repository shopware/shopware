<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Subscriber;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedCriteriaEvent;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Events\FlowSendMailActionEvent;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeCollection;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentEntity;
use Shopware\Core\Content\Shared\MailFlow\Event\MailFlowDataCriteriaEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('inventory')]
class ProductDocumentOrderMailSubscriber implements EventSubscriberInterface
{
    private const ORDER_MAIL_FLOW_DATA_CRITERIA_EVENT = 'mail-flow.data.' . OrderDefinition::ENTITY_NAME . '.criteria.event';

    /**
     * @internal
     *
     * @param EntityRepository<MailTemplateTypeCollection> $mailTemplateTypeRepository
     */
    public function __construct(
        private readonly MediaService $mediaService,
        private readonly EntityRepository $mailTemplateTypeRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutOrderPlacedCriteriaEvent::class => 'addProductDocumentAssociationsToCheckoutOrderCriteria',
            MailFlowDataCriteriaEvent::class => 'addProductDocumentAssociationsToMailFlowCriteria',
            self::ORDER_MAIL_FLOW_DATA_CRITERIA_EVENT => 'addProductDocumentAssociationsToMailFlowCriteria',
            FlowSendMailActionEvent::class => 'attachProductDocumentsToOrderConfirmationMail',
        ];
    }

    public function addProductDocumentAssociationsToCheckoutOrderCriteria(CheckoutOrderPlacedCriteriaEvent $event): void
    {
        $this->addProductDocumentAssociations($event->getCriteria());
    }

    public function addProductDocumentAssociationsToMailFlowCriteria(MailFlowDataCriteriaEvent $event): void
    {
        if ($event->entityName !== OrderDefinition::ENTITY_NAME) {
            return;
        }

        $this->addProductDocumentAssociations($event->criteria);
    }

    public function attachProductDocumentsToOrderConfirmationMail(FlowSendMailActionEvent $event): void
    {
        if ($event->getStorableFlow()->getName() !== CheckoutOrderPlacedEvent::EVENT_NAME) {
            return;
        }

        if (!$this->isOrderConfirmationMailTemplate($event->getMailTemplate(), $event->getContext())) {
            return;
        }

        if (!$event->getStorableFlow()->hasData(OrderAware::ORDER)) {
            return;
        }

        $order = $event->getStorableFlow()->getData(OrderAware::ORDER);
        if (!$order instanceof OrderEntity) {
            return;
        }

        $productDocumentMedia = $this->getProductDocumentMedia($order);
        if ($productDocumentMedia === []) {
            return;
        }

        $attachments = $this->getExistingBinAttachments($event);
        foreach ($productDocumentMedia as $productDocumentId => $media) {
            try {
                $attachments[] = $this->mediaService->getAttachment($media, $event->getContext());
            } catch (\Throwable $exception) {
                $this->logger->warning('Could not attach product document to order confirmation mail.', [
                    'productDocumentId' => $productDocumentId,
                    'mediaId' => $media->getId(),
                    'exception' => $exception,
                ]);
            }
        }

        $event->getDataBag()->set('binAttachments', $attachments);
    }

    private function addProductDocumentAssociations(Criteria $criteria): void
    {
        $criteria->addAssociation('lineItems.product.productDocuments.media');
        $criteria->getAssociation('lineItems.product.productDocuments')->addSorting(new FieldSorting('position'));
    }

    private function isOrderConfirmationMailTemplate(MailTemplateEntity $mailTemplate, Context $context): bool
    {
        $mailTemplateType = $mailTemplate->getMailTemplateType();
        if ($mailTemplateType instanceof MailTemplateTypeEntity) {
            return $mailTemplateType->getTechnicalName() === MailTemplateTypes::MAILTYPE_ORDER_CONFIRM;
        }

        $mailTemplateTypeId = $mailTemplate->getMailTemplateTypeId();
        if ($mailTemplateTypeId === null) {
            return false;
        }

        $criteria = (new Criteria([$mailTemplateTypeId]))
            ->setTitle('product-document-order-mail::load-mail-template-type')
            ->setLimit(1);

        $mailTemplateType = $this->mailTemplateTypeRepository->search($criteria, $context)->getEntities()->first();

        return $mailTemplateType instanceof MailTemplateTypeEntity
            && $mailTemplateType->getTechnicalName() === MailTemplateTypes::MAILTYPE_ORDER_CONFIRM;
    }

    /**
     * @return list<array{content: resource|string, fileName: string|null, mimeType: string|null}>
     */
    private function getExistingBinAttachments(FlowSendMailActionEvent $event): array
    {
        $attachments = $event->getDataBag()->get('binAttachments');

        if (!\is_array($attachments)) {
            return [];
        }

        return array_values($attachments);
    }

    /**
     * @return array<string, MediaEntity>
     */
    private function getProductDocumentMedia(OrderEntity $order): array
    {
        $lineItems = $order->getLineItems();
        if ($lineItems === null) {
            return [];
        }

        $media = [];
        $seenMediaIds = [];

        foreach ($this->getLineItems($lineItems) as $lineItem) {
            if ($lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                continue;
            }

            $product = $lineItem->getProduct();
            if ($product === null || $product->getProductDocuments() === null) {
                continue;
            }

            foreach ($product->getProductDocuments() as $productDocument) {
                $documentMedia = $this->getDocumentMedia($productDocument);
                if ($documentMedia === null) {
                    continue;
                }

                if (isset($seenMediaIds[$documentMedia->getId()])) {
                    continue;
                }

                $seenMediaIds[$documentMedia->getId()] = true;
                $media[$productDocument->getId()] = $documentMedia;
            }
        }

        return $media;
    }

    /**
     * @return \Generator<OrderLineItemEntity>
     */
    private function getLineItems(OrderLineItemCollection $lineItems): \Generator
    {
        foreach ($lineItems as $lineItem) {
            yield $lineItem;

            $children = $lineItem->getChildren();
            if ($children === null) {
                continue;
            }

            yield from $this->getLineItems($children);
        }
    }

    private function getDocumentMedia(ProductDocumentEntity $productDocument): ?MediaEntity
    {
        $media = $productDocument->getMedia();
        if (!$media instanceof MediaEntity) {
            $this->logger->warning('Product document is missing media for order confirmation mail attachment.', [
                'productDocumentId' => $productDocument->getId(),
                'mediaId' => $productDocument->getMediaId(),
            ]);

            return null;
        }

        return $media;
    }
}
