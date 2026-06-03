<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Event;

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
 * Fires when a transaction-capture refund is requested — after PaymentRefundProcessor
 * validates the refund and hands it to the payment handler, before the refund settles.
 * It is the single chokepoint every refund request traverses. The eventual outcome
 * (completed/failed) is tracked by the refund state machine, which has no state_enter.*
 * coverage today.
 */
#[Package('checkout')]
class OrderRefundRequestedEvent extends Event implements OrderAware, ScalarValuesAware, FlowEventAware, MailAware
{
    final public const EVENT_NAME = 'checkout.order.refund.requested';

    public function __construct(
        private readonly Context $context,
        private readonly string $refundId,
        private readonly string $orderTransactionId,
        private readonly string $orderId
    ) {
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('refundId', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('orderTransactionId', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add(OrderAware::ORDER_ID, new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getRefundId(): string
    {
        return $this->refundId;
    }

    public function getOrderTransactionId(): string
    {
        return $this->orderTransactionId;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        // No recipient is resolved here. Throwing MailEventConfigurationException — the
        // trunk idiom for an event without an inherent recipient — would route through
        // MailStorer's OrderAware fallback, which lazy-loads the order during dispatch.
        // Because this event fires on every order write, that load fails in transient
        // contexts (order recalculation; a migration writing orders before the schema is
        // complete). A flow's send-mail action configures the recipient explicitly.
        return new MailRecipientStruct([]);
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
            'refundId' => $this->refundId,
            'orderTransactionId' => $this->orderTransactionId,
            OrderAware::ORDER_ID => $this->orderId,
        ];
    }
}
