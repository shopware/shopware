<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Event;

use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Deliberately not OrderAware: the document is gone and the order may be deleted in the
 * same cascade, so flow storers must not lazy-load entity state from this event. The
 * orderId is still exposed as a scalar (getValues / webhook payload), but no `order`
 * association is hydrated for this event.
 */
#[Package('after-sales')]
class DocumentDeletedEvent extends Event implements MailAware, FlowEventAware, ScalarValuesAware
{
    final public const EVENT_NAME = 'document.generation.deleted';

    public function __construct(
        public readonly string $documentId,
        public readonly string $orderId,
        public readonly string $orderVersionId,
        public readonly string $documentNumber,
        public readonly string $deletedAt,
        public readonly Context $context,
    ) {
    }

    public function getDocumentId(): string
    {
        return $this->documentId;
    }

    /**
     * Exposes the scalar id for the webhook payload only; this event stays non-OrderAware,
     * so no `order` association is hydrated from it (see class docblock).
     */
    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getOrderVersionId(): string
    {
        return $this->orderVersionId;
    }

    public function getDocumentNumber(): string
    {
        return $this->documentNumber;
    }

    public function getDeletedAt(): string
    {
        return $this->deletedAt;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        // No recipient is resolved here. This event is deliberately not OrderAware (see class
        // docblock), so MailStorer has no fallback to populate MAIL_STRUCT/SALES_CHANNEL_ID from.
        // Throwing MailEventConfigurationException would leave both entirely unset, and
        // SendMailAction treats that as a hard error unconditionally — even when a flow
        // configures an explicit recipient. Returning an empty struct keeps the data present so
        // a flow's send-mail action can still configure the recipient explicitly.
        return new MailRecipientStruct([]);
    }

    public function getSalesChannelId(): ?string
    {
        return null;
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('documentId', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('documentNumber', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('deletedAt', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add(OrderAware::ORDER_ID, new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('orderVersionId', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getValues(): array
    {
        return [
            'documentId' => $this->documentId,
            'documentNumber' => $this->documentNumber,
            'deletedAt' => $this->deletedAt,
            OrderAware::ORDER_ID => $this->orderId,
            'orderVersionId' => $this->orderVersionId,
        ];
    }
}
