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

#[Package('after-sales')]
class DocumentGeneratedEvent extends Event implements OrderAware, MailAware, FlowEventAware, ScalarValuesAware
{
    final public const EVENT_NAME = 'document.generation.completed';

    public function __construct(
        public readonly string $documentId,
        public readonly string $orderId,
        public readonly string $orderVersionId,
        public readonly string $documentType,
        public readonly string $documentNumber,
        public readonly Context $context,
    ) {
    }

    public function getDocumentId(): string
    {
        return $this->documentId;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getOrderVersionId(): string
    {
        return $this->orderVersionId;
    }

    public function getDocumentType(): string
    {
        return $this->documentType;
    }

    public function getDocumentNumber(): string
    {
        return $this->documentNumber;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        // No recipient is resolved here. Throwing MailEventConfigurationException — the
        // trunk idiom for an event without an inherent recipient — would route through
        // MailStorer's OrderAware fallback, which lazy-loads the order during dispatch.
        // Because this event fires whenever a document is generated, that load fails in
        // transient contexts (order recalculation; a migration writing orders before the
        // schema is complete). A flow's send-mail action configures the recipient explicitly.
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
            ->add('documentType', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('documentNumber', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add(OrderAware::ORDER_ID, new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('orderVersionId', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getValues(): array
    {
        return [
            'documentId' => $this->documentId,
            'documentType' => $this->documentType,
            'documentNumber' => $this->documentNumber,
            OrderAware::ORDER_ID => $this->orderId,
            'orderVersionId' => $this->orderVersionId,
        ];
    }
}
