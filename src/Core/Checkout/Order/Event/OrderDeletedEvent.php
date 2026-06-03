<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Event;

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
 * Deliberately not OrderAware: the row is gone, so flow storers must not lazy-load
 * entity state from this event. The payload is a pre-delete snapshot.
 */
#[Package('checkout')]
class OrderDeletedEvent extends Event implements ScalarValuesAware, FlowEventAware, MailAware
{
    final public const EVENT_NAME = 'checkout.order.deleted';

    public function __construct(
        private readonly Context $context,
        private readonly string $orderId,
        private readonly string $deletedAt,
        private readonly ?string $orderNumber = null
    ) {
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('orderId', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('orderNumber', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('deletedAt', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getOrderNumber(): ?string
    {
        return $this->orderNumber;
    }

    public function getDeletedAt(): string
    {
        return $this->deletedAt;
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
            'orderId' => $this->orderId,
            'orderNumber' => $this->orderNumber,
            'deletedAt' => $this->deletedAt,
        ];
    }
}
