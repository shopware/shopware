<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Event;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Fires for every live order insert — Store API checkout, Admin API, sync, and import
 * alike. This closes the gap of checkout.order.placed, which only fires for Store API
 * checkout (CartOrderRoute) and keeps that narrower meaning. For the Store API path both
 * events fire by design (placed and created co-emit) — consumers must not assume they
 * are mutually exclusive. The order entity is loaded lazily — only when a webhook
 * payload is encoded; flows reload the order by id via OrderStorer.
 */
#[Package('checkout')]
class OrderCreatedEvent extends Event implements OrderAware, ScalarValuesAware, FlowEventAware, MailAware
{
    final public const EVENT_NAME = 'checkout.order.created';

    private ?OrderEntity $order = null;

    /**
     * @param \Closure(): OrderEntity $orderLoader
     */
    public function __construct(
        private readonly Context $context,
        private readonly string $orderId,
        private readonly \Closure $orderLoader,
        private readonly ?string $salesChannelId = null
    ) {
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add(OrderAware::ORDER, new EntityType(OrderDefinition::class))
            ->add(OrderAware::ORDER_ID, new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getOrder(): OrderEntity
    {
        return $this->order ??= ($this->orderLoader)();
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
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

    /**
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getValues(): array
    {
        return [
            OrderAware::ORDER_ID => $this->orderId,
        ];
    }
}
