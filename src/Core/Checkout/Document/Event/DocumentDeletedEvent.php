<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Event;

use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Content\MailTemplate\Exception\MailEventConfigurationException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Deliberately not OrderAware: the document is gone and the order may be deleted in the
 * same cascade, so flow storers must not lazy-load entity state from this event. The
 * orderId is still exposed as a scalar (getValues / webhook payload), but no `order`
 * association is hydrated for this event.
 */
#[Package('after-sales')]
class DocumentDeletedEvent extends Event implements ScalarValuesAware, FlowEventAware, MailAware
{
    final public const EVENT_NAME = 'after_sales.document.deleted';

    public function __construct(
        private readonly Context $context,
        private readonly string $documentId,
        private readonly string $orderId,
        private readonly string $deletedAt,
        private readonly ?string $documentNumber = null
    ) {
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('documentId', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('orderId', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('documentNumber', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('deletedAt', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getDocumentId(): string
    {
        return $this->documentId;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getDeletedAt(): string
    {
        return $this->deletedAt;
    }

    public function getDocumentNumber(): ?string
    {
        return $this->documentNumber;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        throw new MailEventConfigurationException('Data for mailRecipientStruct not available.', self::class);
    }

    public function getSalesChannelId(): ?string
    {
        return null;
    }

    /**
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getValues(): array
    {
        return [
            'documentId' => $this->documentId,
            'orderId' => $this->orderId,
            'documentNumber' => $this->documentNumber,
            'deletedAt' => $this->deletedAt,
        ];
    }
}
